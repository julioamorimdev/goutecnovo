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
        $stmt = db()->prepare("SELECT status FROM clients WHERE id=?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if ($client) {
            $newStatus = $client['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare("UPDATE clients SET status=? WHERE id=?")->execute([$newStatus, $id]);
        }
        header('Location: /admin/clients.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Cliente excluído com sucesso.';
        header('Location: /admin/clients.php');
        exit;
    }
}

$page_title = 'Clientes';
$active = 'clients';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar clientes
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
    
    if ($statusFilter && in_array($statusFilter, ['active', 'inactive', 'closed'], true)) {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if ($search !== '') {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM clients {$whereClause} ORDER BY created_at DESC, id DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

// Contar por status
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $stats = [
        'total' => db()->query("SELECT COUNT(*) as cnt FROM clients")->fetch()['cnt'],
        'active' => db()->query("SELECT COUNT(*) as cnt FROM clients WHERE status='active'")->fetch()['cnt'],
        'inactive' => db()->query("SELECT COUNT(*) as cnt FROM clients WHERE status='inactive'")->fetch()['cnt'],
        'closed' => db()->query("SELECT COUNT(*) as cnt FROM clients WHERE status='closed'")->fetch()['cnt'],
    ];
} catch (Throwable $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'closed' => 0];
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">Ativo</span>';
        case 'inactive':
            return '<span class="badge bg-warning text-dark">Inativo</span>';
        case 'closed':
            return '<span class="badge bg-danger text-white">Fechado</span>';
        default:
            return '<span class="badge bg-secondary">' . h($status) . '</span>';
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Clientes</h1>
        <a href="/admin/client_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Cliente
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

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total</h6>
                            <h3 class="mb-0"><?= number_format((int)$stats['total']) ?></h3>
                        </div>
                        <div class="text-primary fs-2">
                            <i class="las la-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Ativos</h6>
                            <h3 class="mb-0 text-success"><?= number_format((int)$stats['active']) ?></h3>
                        </div>
                        <div class="text-success fs-2">
                            <i class="las la-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Inativos</h6>
                            <h3 class="mb-0 text-warning"><?= number_format((int)$stats['inactive']) ?></h3>
                        </div>
                        <div class="text-warning fs-2">
                            <i class="las la-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Fechados</h6>
                            <h3 class="mb-0 text-danger"><?= number_format((int)$stats['closed']) ?></h3>
                        </div>
                        <div class="text-danger fs-2">
                            <i class="las la-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Nome, email ou empresa...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Fechado</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                    <a href="/admin/clients.php" class="btn btn-secondary">
                        <i class="las la-times me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de clientes -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Cidade/Estado</th>
                            <th>Status</th>
                            <th>Data de Registro</th>
                            <th style="width: 150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhum cliente encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td>#<?= (int)$client['id'] ?></td>
                                    <td>
                                        <strong><?= h($client['first_name'] . ' ' . $client['last_name']) ?></strong>
                                        <?php if ($client['company_name']): ?>
                                            <br><small class="text-muted"><?= h($client['company_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= h($client['email']) ?>" class="text-decoration-none">
                                            <?= h($client['email']) ?>
                                        </a>
                                    </td>
                                    <td><?= $client['phone'] ? h($client['phone']) : '<span class="text-muted">-</span>' ?></td>
                                    <td>
                                        <?php if ($client['city'] || $client['state']): ?>
                                            <?= h(trim(($client['city'] ?? '') . ' / ' . ($client['state'] ?? ''), ' / ')) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($client['status']) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($client['created_at'])) ?>
                                        <br><small class="text-muted"><?= date('H:i', strtotime($client['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $client['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $client['status'] === 'active' ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= $client['status'] === 'active' ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
