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
        db()->prepare("UPDATE addons SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/addons.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se há pedidos vinculados
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM order_addons WHERE addon_id=?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ((int)$result['cnt'] > 0) {
            $_SESSION['error'] = 'Não é possível excluir o addon pois existem pedidos vinculados a ele.';
        } else {
            db()->prepare("DELETE FROM addons WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'Addon excluído com sucesso.';
        }
        header('Location: /admin/addons.php');
        exit;
    }
    
    if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
        $stmt = db()->prepare("SELECT id, sort_order FROM addons WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if ($cur) {
            $sort = (int)$cur['sort_order'];
            if ($action === 'move_up') {
                $q = "SELECT id, sort_order FROM addons
                      WHERE sort_order < :sort OR (sort_order = :sort AND id < :id)
                      ORDER BY sort_order DESC, id DESC LIMIT 1";
            } else {
                $q = "SELECT id, sort_order FROM addons
                      WHERE sort_order > :sort OR (sort_order = :sort AND id > :id)
                      ORDER BY sort_order ASC, id ASC LIMIT 1";
            }
            $stmt2 = db()->prepare($q);
            $stmt2->execute([':sort' => $sort, ':id' => $id]);
            $other = $stmt2->fetch();
            if ($other) {
                db()->beginTransaction();
                try {
                    db()->prepare("UPDATE addons SET sort_order=? WHERE id=?")->execute([$sort, (int)$other['id']]);
                    db()->prepare("UPDATE addons SET sort_order=? WHERE id=?")->execute([(int)$other['sort_order'], $id]);
                    db()->commit();
                } catch (Throwable $e) {
                    db()->rollBack();
                }
            }
        }
        header('Location: /admin/addons.php');
        exit;
    }
}

$page_title = 'Serviços Addons';
$active = 'addons';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar addons
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $categoryFilter = $_GET['category'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($categoryFilter && in_array($categoryFilter, ['ssl', 'backup', 'security', 'email', 'domain', 'other'], true)) {
        $where[] = "category = ?";
        $params[] = $categoryFilter;
    }
    
    if ($statusFilter === 'enabled') {
        $where[] = "is_enabled = 1";
    } elseif ($statusFilter === 'disabled') {
        $where[] = "is_enabled = 0";
    }
    
    if ($search !== '') {
        $where[] = "(name LIKE ? OR slug LIKE ? OR description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM addons {$whereClause} ORDER BY sort_order ASC, id ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $addons = $stmt->fetchAll();
} catch (Throwable $e) {
    $addons = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Serviços Addons</h1>
        <a href="/admin/addon_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Addon
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

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Nome, descrição...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Categoria</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Todas</option>
                        <option value="ssl" <?= $categoryFilter === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="backup" <?= $categoryFilter === 'backup' ? 'selected' : '' ?>>Backup</option>
                        <option value="security" <?= $categoryFilter === 'security' ? 'selected' : '' ?>>Segurança</option>
                        <option value="email" <?= $categoryFilter === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="domain" <?= $categoryFilter === 'domain' ? 'selected' : '' ?>>Domínio</option>
                        <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="enabled" <?= $statusFilter === 'enabled' ? 'selected' : '' ?>>Ativos</option>
                        <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($addons)): ?>
                <div class="text-center py-5">
                    <i class="las la-puzzle-piece text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum addon cadastrado ainda.</p>
                    <a href="/admin/addon_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Addon
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Ícone</th>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Tipo de Preço</th>
                                <th>Preço</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($addons as $addon): ?>
                                <tr>
                                    <td>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <?php if ($addon['icon_class']): ?>
                                                <i class="<?= h($addon['icon_class']) ?> text-primary"></i>
                                            <?php else: ?>
                                                <i class="las la-puzzle-piece text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= h($addon['name']) ?></strong>
                                        <?php if ((int)$addon['is_featured'] === 1): ?>
                                            <span class="badge bg-warning text-dark ms-1">Destaque</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= h($addon['slug']) ?></small>
                                        <?php if ($addon['short_description']): ?>
                                            <br><small class="text-muted"><?= h($addon['short_description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $categoryLabels = [
                                            'ssl' => 'SSL',
                                            'backup' => 'Backup',
                                            'security' => 'Segurança',
                                            'email' => 'Email',
                                            'domain' => 'Domínio',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $categoryLabels[$addon['category']] ?? ucfirst($addon['category']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $priceTypeLabels = [
                                            'one_time' => 'Pagamento Único',
                                            'monthly' => 'Mensal',
                                            'annual' => 'Anual'
                                        ];
                                        ?>
                                        <small><?= $priceTypeLabels[$addon['price_type']] ?? ucfirst($addon['price_type']) ?></small>
                                        <?php if ($addon['billing_cycle']): ?>
                                            <br><small class="text-muted">Ciclo: <?= h($addon['billing_cycle']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$addon['price'], 2, ',', '.') ?></strong>
                                        <?php if ((float)$addon['setup_fee'] > 0): ?>
                                            <br><small class="text-muted">Instalação: R$ <?= number_format((float)$addon['setup_fee'], 2, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$addon['is_enabled'] === 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/addon_edit.php?id=<?= (int)$addon['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$addon['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$addon['is_enabled'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$addon['is_enabled'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$addon['is_enabled'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este addon?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$addon['id'] ?>">
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

