<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE tlds SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/tlds.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se há domínios registrados com este TLD
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM domain_registrations WHERE tld_id=?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ((int)$result['cnt'] > 0) {
            $_SESSION['error'] = 'Não é possível excluir o TLD pois existem domínios registrados com ele.';
        } else {
            db()->prepare("DELETE FROM tlds WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'TLD excluído com sucesso.';
        }
        header('Location: /admin/tlds.php');
        exit;
    }
    
    if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
        $stmt = db()->prepare("SELECT id, sort_order FROM tlds WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if ($cur) {
            $sort = (int)$cur['sort_order'];
            if ($action === 'move_up') {
                $q = "SELECT id, sort_order FROM tlds
                      WHERE sort_order < :sort OR (sort_order = :sort AND id < :id)
                      ORDER BY sort_order DESC, id DESC LIMIT 1";
            } else {
                $q = "SELECT id, sort_order FROM tlds
                      WHERE sort_order > :sort OR (sort_order = :sort AND id > :id)
                      ORDER BY sort_order ASC, id ASC LIMIT 1";
            }
            $stmt2 = db()->prepare($q);
            $stmt2->execute([':sort' => $sort, ':id' => $id]);
            $other = $stmt2->fetch();
            if ($other) {
                db()->beginTransaction();
                try {
                    db()->prepare("UPDATE tlds SET sort_order=? WHERE id=?")->execute([$sort, (int)$other['id']]);
                    db()->prepare("UPDATE tlds SET sort_order=? WHERE id=?")->execute([(int)$other['sort_order'], $id]);
                    db()->commit();
                } catch (Throwable $e) {
                    db()->rollBack();
                }
            }
        }
        header('Location: /admin/tlds.php');
        exit;
    }
}

$page_title = 'TLDs';
$active = 'tlds';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar TLDs
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $tlds = db()->query("SELECT * FROM tlds ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Throwable $e) {
    $tlds = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">TLDs (Top Level Domains)</h1>
        <a href="/admin/tld_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo TLD
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($tlds)): ?>
                <div class="text-center py-5">
                    <i class="las la-globe text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum TLD cadastrado ainda.</p>
                    <a href="/admin/tld_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro TLD
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 100px;">TLD</th>
                                <th>Nome</th>
                                <th>Preço Registro</th>
                                <th>Preço Renovação</th>
                                <th>Preço Transferência</th>
                                <th>Anos (Min/Max)</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 200px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tlds as $tld): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= h($tld['tld']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h($tld['name']) ?></strong>
                                        <?php if ($tld['description']): ?>
                                            <br><small class="text-muted"><?= h($tld['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">R$ <?= number_format((float)$tld['price_register'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">R$ <?= number_format((float)$tld['price_renew'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">R$ <?= number_format((float)$tld['price_transfer'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <?= (int)$tld['min_years'] ?> / <?= (int)$tld['max_years'] ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$tld['is_enabled'] === 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/tld_edit.php?id=<?= (int)$tld['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$tld['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$tld['is_enabled'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$tld['is_enabled'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$tld['is_enabled'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este TLD?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$tld['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

