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
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Fatura excluída com sucesso.';
        header('Location: /admin/invoices.php');
        exit;
    }
    
    if ($id > 0 && in_array($action, ['unpaid', 'paid', 'cancelled', 'refunded'])) {
        $paidDate = null;
        if ($action === 'paid') {
            $paidDate = date('Y-m-d');
        }
        db()->prepare("UPDATE invoices SET status=?, paid_date=? WHERE id=?")->execute([$action, $paidDate, $id]);
        header('Location: /admin/invoices.php');
        exit;
    }
}

$page_title = 'Faturas';
$active = 'invoices';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar faturas
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
    
    if ($statusFilter && in_array($statusFilter, ['unpaid', 'paid', 'cancelled', 'refunded'], true)) {
        $where[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($clientFilter > 0) {
        $where[] = "i.client_id = ?";
        $params[] = $clientFilter;
    }
    
    if ($search !== '') {
        $where[] = "(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT i.*, 
                   c.first_name, c.last_name, c.email as client_email,
                   o.order_number
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN orders o ON i.order_id = o.id
            {$whereClause}
            ORDER BY i.created_at DESC, i.id DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
} catch (Throwable $e) {
    $invoices = [];
}

// Contar por status e calcular totais
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stats = [
        'total' => db()->query("SELECT COUNT(*) as cnt FROM invoices")->fetch()['cnt'],
        'unpaid' => db()->query("SELECT COUNT(*) as cnt FROM invoices WHERE status='unpaid'")->fetch()['cnt'],
        'paid' => db()->query("SELECT COUNT(*) as cnt FROM invoices WHERE status='paid'")->fetch()['cnt'],
        'cancelled' => db()->query("SELECT COUNT(*) as cnt FROM invoices WHERE status='cancelled'")->fetch()['cnt'],
        'total_unpaid' => db()->query("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE status='unpaid'")->fetch()['total'],
        'total_paid' => db()->query("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE status='paid'")->fetch()['total'],
    ];
} catch (Throwable $e) {
    $stats = ['total' => 0, 'unpaid' => 0, 'paid' => 0, 'cancelled' => 0, 'total_unpaid' => 0, 'total_paid' => 0];
}

// Buscar clientes para o filtro
try {
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'unpaid':
            return '<span class="badge bg-danger text-white">Não Paga</span>';
        case 'paid':
            return '<span class="badge bg-success">Paga</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary text-white">Cancelada</span>';
        case 'refunded':
            return '<span class="badge bg-warning text-dark">Reembolsada</span>';
        default:
            return '<span class="badge bg-secondary">' . h($status) . '</span>';
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Faturas</h1>
        <a href="/admin/invoice_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Nova Fatura
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
                    <h6 class="text-muted mb-1">Não Pagas</h6>
                    <h4 class="mb-0 text-danger"><?= number_format((int)$stats['unpaid']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Pagas</h6>
                    <h4 class="mb-0 text-success"><?= number_format((int)$stats['paid']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Não Pago</h6>
                    <h4 class="mb-0 text-danger">R$ <?= number_format((float)$stats['total_unpaid'], 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Pago</h6>
                    <h4 class="mb-0 text-success">R$ <?= number_format((float)$stats['total_paid'], 2, ',', '.') ?></h4>
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número da fatura, nome ou email...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Não Paga</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paga</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsada</option>
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
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de faturas -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Pedido</th>
                            <th>Subtotal</th>
                            <th>Impostos</th>
                            <th>Total</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th style="width: 200px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Nenhuma fatura encontrada.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><strong><?= h($invoice['invoice_number']) ?></strong></td>
                                    <td>
                                        <?= h($invoice['first_name'] . ' ' . $invoice['last_name']) ?>
                                        <br><small class="text-muted"><?= h($invoice['client_email']) ?></small>
                                    </td>
                                    <td><?= $invoice['order_number'] ? h($invoice['order_number']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= h($invoice['currency']) ?> <?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?></td>
                                    <td><?= h($invoice['currency']) ?> <?= number_format((float)$invoice['tax'], 2, ',', '.') ?></td>
                                    <td><strong><?= h($invoice['currency']) ?> <?= number_format((float)$invoice['total'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <?php if ($invoice['due_date']): ?>
                                            <?= date('d/m/Y', strtotime($invoice['due_date'])) ?>
                                            <?php if ($invoice['status'] === 'unpaid' && strtotime($invoice['due_date']) < time()): ?>
                                                <br><small class="text-danger">Vencida</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($invoice['status']) ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/invoice_edit.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="las la-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$invoice['id'] ?>, 'unpaid')">Marcar como Não Paga</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$invoice['id'] ?>, 'paid')">Marcar como Paga</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$invoice['id'] ?>, 'cancelled')">Cancelar</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$invoice['id'] ?>, 'refunded')">Reembolsar</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteInvoice(<?= (int)$invoice['id'] ?>)">Excluir</a></li>
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
    if (confirm('Tem certeza que deseja alterar o status desta fatura?')) {
        document.getElementById('statusId').value = id;
        document.getElementById('statusAction').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteInvoice(id) {
    if (confirm('Tem certeza que deseja excluir esta fatura?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
