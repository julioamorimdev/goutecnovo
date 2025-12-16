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
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se há faturas vinculadas
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE order_id=?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ((int)$result['cnt'] > 0) {
            $_SESSION['error'] = 'Não é possível excluir o pedido pois existem faturas vinculadas.';
        } else {
            db()->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'Pedido excluído com sucesso.';
        }
        header('Location: /admin/orders.php');
        exit;
    }
    
    if ($id > 0 && in_array($action, ['pending', 'active', 'suspended', 'cancelled', 'fraud'])) {
        db()->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$action, $id]);
        header('Location: /admin/orders.php');
        exit;
    }
}

$page_title = 'Pedidos';
$active = 'orders';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar pedidos
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $clientFilter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['pending', 'active', 'suspended', 'cancelled', 'fraud'], true)) {
        $where[] = "o.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($clientFilter > 0) {
        $where[] = "o.client_id = ?";
        $params[] = $clientFilter;
    }
    
    if ($search !== '') {
        $where[] = "(o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT o.*, 
                   c.first_name, c.last_name, c.email as client_email,
                   p.name as plan_name
            FROM orders o
            LEFT JOIN clients c ON o.client_id = c.id
            LEFT JOIN plans p ON o.plan_id = p.id
            {$whereClause}
            ORDER BY o.created_at DESC, o.id DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $orders = [];
}

// Contar por status
try {
    $stats = [
        'total' => db()->query("SELECT COUNT(*) as cnt FROM orders")->fetch()['cnt'],
        'pending' => db()->query("SELECT COUNT(*) as cnt FROM orders WHERE status='pending'")->fetch()['cnt'],
        'active' => db()->query("SELECT COUNT(*) as cnt FROM orders WHERE status='active'")->fetch()['cnt'],
        'suspended' => db()->query("SELECT COUNT(*) as cnt FROM orders WHERE status='suspended'")->fetch()['cnt'],
        'cancelled' => db()->query("SELECT COUNT(*) as cnt FROM orders WHERE status='cancelled'")->fetch()['cnt'],
    ];
} catch (Throwable $e) {
    $stats = ['total' => 0, 'pending' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0];
}

// Buscar clientes para o filtro
try {
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pendente</span>';
        case 'active':
            return '<span class="badge bg-success">Ativo</span>';
        case 'suspended':
            return '<span class="badge bg-danger text-white">Suspenso</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary text-white">Cancelado</span>';
        case 'fraud':
            return '<span class="badge bg-dark text-white">Fraude</span>';
        default:
            return '<span class="badge bg-secondary">' . h($status) . '</span>';
    }
}

function getBillingCycleLabel(string $cycle): string {
    $labels = [
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
        'biennial' => 'Bienal',
        'triennal' => 'Trienal',
    ];
    return $labels[$cycle] ?? $cycle;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Pedidos</h1>
        <a href="/admin/order_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Pedido
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
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total</h6>
                    <h4 class="mb-0"><?= number_format((int)$stats['total']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Pendentes</h6>
                    <h4 class="mb-0 text-warning"><?= number_format((int)$stats['pending']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Ativos</h6>
                    <h4 class="mb-0 text-success"><?= number_format((int)$stats['active']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Suspensos</h6>
                    <h4 class="mb-0 text-danger"><?= number_format((int)$stats['suspended']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Cancelados</h6>
                    <h4 class="mb-0 text-secondary"><?= number_format((int)$stats['cancelled']) ?></h4>
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número do pedido, nome ou email...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="fraud" <?= $statusFilter === 'fraud' ? 'selected' : '' ?>>Fraude</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="client_id" class="form-label">Cliente</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>" <?= $clientFilter === (int)$client['id'] ? 'selected' : '' ?>>
                                <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de pedidos -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Plano</th>
                            <th>Ciclo</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th style="width: 200px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhum pedido encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?= h($order['order_number']) ?></strong></td>
                                    <td>
                                        <?= h($order['first_name'] . ' ' . $order['last_name']) ?>
                                        <br><small class="text-muted"><?= h($order['client_email']) ?></small>
                                    </td>
                                    <td><?= $order['plan_name'] ? h($order['plan_name']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= getBillingCycleLabel($order['billing_cycle']) ?></td>
                                    <td>
                                        <strong><?= h($order['currency']) ?> <?= number_format((float)$order['amount'], 2, ',', '.') ?></strong>
                                        <?php if ((float)$order['setup_fee'] > 0): ?>
                                            <br><small class="text-muted">+ Taxa: <?= h($order['currency']) ?> <?= number_format((float)$order['setup_fee'], 2, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($order['status']) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                        <br><small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/order_edit.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="las la-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$order['id'] ?>, 'pending')">Marcar como Pendente</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$order['id'] ?>, 'active')">Marcar como Ativo</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$order['id'] ?>, 'suspended')">Marcar como Suspenso</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$order['id'] ?>, 'cancelled')">Marcar como Cancelado</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteOrder(<?= (int)$order['id'] ?>)">Excluir</a></li>
                                                </ul>
                                            </div>
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

<form id="statusForm" method="POST" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="statusAction">
    <input type="hidden" name="id" id="statusId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function changeStatus(id, status) {
    if (confirm('Tem certeza que deseja alterar o status deste pedido?')) {
        document.getElementById('statusId').value = id;
        document.getElementById('statusAction').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteOrder(id) {
    if (confirm('Tem certeza que deseja excluir este pedido?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
