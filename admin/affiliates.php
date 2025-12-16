<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

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
        $stmt = db()->prepare("SELECT status FROM affiliates WHERE id=?");
        $stmt->execute([$id]);
        $affiliate = $stmt->fetch();
        if ($affiliate) {
            $newStatus = $affiliate['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare("UPDATE affiliates SET status=? WHERE id=?")->execute([$newStatus, $id]);
        }
        header('Location: /admin/affiliates.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se há comissões vinculadas
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM affiliate_commissions WHERE affiliate_id=?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ((int)$result['cnt'] > 0) {
            $_SESSION['error'] = 'Não é possível excluir o afiliado pois existem comissões vinculadas.';
        } else {
            db()->prepare("DELETE FROM affiliates WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'Afiliado excluído com sucesso.';
        }
        header('Location: /admin/affiliates.php');
        exit;
    }
}

$page_title = 'Afiliados';
$active = 'affiliates';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar afiliados
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if ($search !== '') {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company_name LIKE ? OR code LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM affiliates {$whereClause} ORDER BY created_at DESC, id DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $affiliates = $stmt->fetchAll();
} catch (Throwable $e) {
    $affiliates = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Afiliados</h1>
        <a href="/admin/affiliate_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Afiliado
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
                <div class="col-md-8">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Nome, email, código...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($affiliates)): ?>
                <div class="text-center py-5">
                    <i class="las la-user-friends text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum afiliado encontrado.</p>
                    <a href="/admin/affiliate_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Afiliado
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Comissão</th>
                                <th>Total Ganho</th>
                                <th>Pago</th>
                                <th>Pendente</th>
                                <th>Indicações</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affiliates as $affiliate): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= h($affiliate['code']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h($affiliate['first_name'] . ' ' . $affiliate['last_name']) ?></strong>
                                        <?php if ($affiliate['company_name']): ?>
                                            <br><small class="text-muted"><?= h($affiliate['company_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= h($affiliate['email']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($affiliate['commission_type'] === 'percentage'): ?>
                                            <span class="badge bg-info"><?= number_format((float)$affiliate['commission_value'], 2, ',', '.') ?>%</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">R$ <?= number_format((float)$affiliate['commission_value'], 2, ',', '.') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$affiliate['total_earnings'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">R$ <?= number_format((float)$affiliate['paid_earnings'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-warning">R$ <?= number_format((float)$affiliate['pending_earnings'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= (int)$affiliate['total_referrals'] ?> indicações</span>
                                        <br><small class="text-muted"><?= (int)$affiliate['total_sales'] ?> vendas</small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'active' => 'bg-success',
                                            'inactive' => 'bg-secondary',
                                            'suspended' => 'bg-danger'
                                        ];
                                        $statusLabels = [
                                            'active' => 'Ativo',
                                            'inactive' => 'Inativo',
                                            'suspended' => 'Suspenso'
                                        ];
                                        $status = $affiliate['status'] ?? 'active';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/affiliate_edit.php?id=<?= (int)$affiliate['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <a href="/admin/affiliate_commissions.php?affiliate_id=<?= (int)$affiliate['id'] ?>" class="btn btn-sm btn-info" title="Comissões">
                                                <i class="las la-money-bill"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$affiliate['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $affiliate['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $affiliate['status'] === 'active' ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= $affiliate['status'] === 'active' ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este afiliado?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$affiliate['id'] ?>">
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

