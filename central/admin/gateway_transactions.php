<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Log de Transações do Gateway';
$active = 'gateway_transactions';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar transações
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $gatewayFilter = $_GET['gateway'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['success', 'failed', 'pending', 'cancelled', 'refunded'], true)) {
        $where[] = "t.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($gatewayFilter && in_array($gatewayFilter, ['stripe', 'paypal', 'pagseguro', 'mercadopago', 'other'], true)) {
        $where[] = "t.gateway = ?";
        $params[] = $gatewayFilter;
    }
    
    if ($typeFilter && in_array($typeFilter, ['payment', 'refund', 'subscription', 'webhook', 'other'], true)) {
        $where[] = "t.transaction_type = ?";
        $params[] = $typeFilter;
    }
    
    if ($dateFrom !== '') {
        $where[] = "DATE(t.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== '') {
        $where[] = "DATE(t.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search !== '') {
        $where[] = "(t.transaction_number LIKE ? OR t.gateway_transaction_id LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR i.invoice_number LIKE ? OR o.order_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT t.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name,
                   i.invoice_number,
                   o.order_number
            FROM gateway_transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN invoices i ON t.invoice_id = i.id
            LEFT JOIN orders o ON t.order_id = o.id
            {$whereClause}
            ORDER BY t.created_at DESC
            LIMIT 500";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (Throwable $e) {
    $transactions = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Log de Transações do Gateway</h1>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, ID, cliente...">
                </div>
                <div class="col-md-2">
                    <label for="gateway" class="form-label">Gateway</label>
                    <select class="form-select" id="gateway" name="gateway">
                        <option value="">Todos</option>
                        <option value="stripe" <?= $gatewayFilter === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                        <option value="paypal" <?= $gatewayFilter === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                        <option value="pagseguro" <?= $gatewayFilter === 'pagseguro' ? 'selected' : '' ?>>PagSeguro</option>
                        <option value="mercadopago" <?= $gatewayFilter === 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                        <option value="other" <?= $gatewayFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label">Tipo</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="payment" <?= $typeFilter === 'payment' ? 'selected' : '' ?>>Pagamento</option>
                        <option value="refund" <?= $typeFilter === 'refund' ? 'selected' : '' ?>>Reembolso</option>
                        <option value="subscription" <?= $typeFilter === 'subscription' ? 'selected' : '' ?>>Assinatura</option>
                        <option value="webhook" <?= $typeFilter === 'webhook' ? 'selected' : '' ?>>Webhook</option>
                        <option value="other" <?= $typeFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Sucesso</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Falhou</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                    <a href="/admin/gateway_transactions.php" class="btn btn-secondary">
                        <i class="las la-redo me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="las la-exchange-alt text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma transação encontrada.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Gateway</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th style="width: 100px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="<?= $tx['status'] === 'failed' ? 'table-danger' : ($tx['status'] === 'success' ? 'table-success' : '') ?>">
                                    <td>
                                        <strong class="text-primary"><?= h($tx['transaction_number']) ?></strong>
                                        <?php if ($tx['gateway_transaction_id']): ?>
                                            <br><small class="text-muted">Gateway ID: <?= h(substr($tx['gateway_transaction_id'], 0, 20)) ?><?= strlen($tx['gateway_transaction_id']) > 20 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $gatewayLabels = [
                                            'stripe' => 'Stripe',
                                            'paypal' => 'PayPal',
                                            'pagseguro' => 'PagSeguro',
                                            'mercadopago' => 'Mercado Pago',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-info"><?= $gatewayLabels[$tx['gateway']] ?? ucfirst($tx['gateway']) ?></span>
                                        <?php if ((int)$tx['webhook_received'] === 1): ?>
                                            <br><small class="badge bg-success">Webhook</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'payment' => 'Pagamento',
                                            'refund' => 'Reembolso',
                                            'subscription' => 'Assinatura',
                                            'webhook' => 'Webhook',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <small><?= $typeLabels[$tx['transaction_type']] ?? ucfirst($tx['transaction_type']) ?></small>
                                        <?php if ($tx['payment_method']): ?>
                                            <br><small class="text-muted"><?= h($tx['payment_method']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tx['first_name']): ?>
                                            <strong><?= h($tx['first_name'] . ' ' . $tx['last_name']) ?></strong>
                                            <br><small class="text-muted"><?= h($tx['client_email']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                        <?php if ($tx['invoice_number']): ?>
                                            <br><small class="text-muted">Fatura: <?= h($tx['invoice_number']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($tx['order_number']): ?>
                                            <br><small class="text-muted">Pedido: <?= h($tx['order_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="<?= $tx['status'] === 'success' ? 'text-success' : 'text-muted' ?>">
                                            R$ <?= number_format((float)$tx['amount'], 2, ',', '.') ?>
                                        </strong>
                                        <?php if ($tx['installments'] && (int)$tx['installments'] > 1): ?>
                                            <br><small class="text-muted"><?= (int)$tx['installments'] ?>x</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'success' => 'bg-success',
                                            'failed' => 'bg-danger',
                                            'pending' => 'bg-warning',
                                            'cancelled' => 'bg-secondary',
                                            'refunded' => 'bg-info'
                                        ];
                                        $statusLabels = [
                                            'success' => 'Sucesso',
                                            'failed' => 'Falhou',
                                            'pending' => 'Pendente',
                                            'cancelled' => 'Cancelado',
                                            'refunded' => 'Reembolsado'
                                        ];
                                        $status = $tx['status'] ?? 'pending';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                        <?php if ($tx['error_code']): ?>
                                            <br><small class="text-danger"><?= h($tx['error_code']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i:s', strtotime($tx['created_at'])) ?></small>
                                        <?php if ($tx['processing_time_ms']): ?>
                                            <br><small class="text-muted"><?= (int)$tx['processing_time_ms'] ?>ms</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/gateway_transaction_view.php?id=<?= (int)$tx['id'] ?>" class="btn btn-sm btn-primary" title="Ver Detalhes">
                                            <i class="las la-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="las la-info-circle"></i> Exibindo até 500 transações mais recentes. Use os filtros para refinar a busca.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

