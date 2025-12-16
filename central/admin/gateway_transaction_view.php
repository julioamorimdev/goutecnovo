<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('Transação não encontrada.');
}

$page_title = 'Detalhes da Transação';
$active = 'gateway_transactions';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar transação
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $sql = "SELECT t.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name, c.phone,
                   i.invoice_number, i.total as invoice_total, i.status as invoice_status,
                   o.order_number, o.amount as order_amount, o.status as order_status
            FROM gateway_transactions t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN invoices i ON t.invoice_id = i.id
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE t.id=?";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $tx = $stmt->fetch();
    
    if (!$tx) {
        http_response_code(404);
        exit('Transação não encontrada.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro ao buscar transação.');
}

// Decodificar JSONs
$requestData = $tx['request_data'] ? json_decode($tx['request_data'], true) : null;
$responseData = $tx['response_data'] ? json_decode($tx['response_data'], true) : null;
$webhookData = $tx['webhook_data'] ? json_decode($tx['webhook_data'], true) : null;

$gatewayLabels = [
    'stripe' => 'Stripe',
    'paypal' => 'PayPal',
    'pagseguro' => 'PagSeguro',
    'mercadopago' => 'Mercado Pago',
    'other' => 'Outros'
];

$typeLabels = [
    'payment' => 'Pagamento',
    'refund' => 'Reembolso',
    'subscription' => 'Assinatura',
    'webhook' => 'Webhook',
    'other' => 'Outros'
];

$statusLabels = [
    'success' => 'Sucesso',
    'failed' => 'Falhou',
    'pending' => 'Pendente',
    'cancelled' => 'Cancelado',
    'refunded' => 'Reembolsado'
];

$statusBadges = [
    'success' => 'bg-success',
    'failed' => 'bg-danger',
    'pending' => 'bg-warning',
    'cancelled' => 'bg-secondary',
    'refunded' => 'bg-info'
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Detalhes da Transação #<?= h($tx['transaction_number']) ?></h1>
        <a href="/admin/gateway_transactions.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Informações Gerais -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Informações da Transação</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Número:</strong><br>
                            <span class="text-primary"><?= h($tx['transaction_number']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
                            <span class="badge <?= $statusBadges[$tx['status']] ?? 'bg-secondary' ?>">
                                <?= $statusLabels[$tx['status']] ?? ucfirst($tx['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Gateway:</strong><br>
                            <span class="badge bg-info"><?= $gatewayLabels[$tx['gateway']] ?? ucfirst($tx['gateway']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Tipo:</strong><br>
                            <?= $typeLabels[$tx['transaction_type']] ?? ucfirst($tx['transaction_type']) ?>
                        </div>
                    </div>

                    <?php if ($tx['gateway_transaction_id']): ?>
                        <div class="mb-3">
                            <strong>ID no Gateway:</strong><br>
                            <code><?= h($tx['gateway_transaction_id']) ?></code>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Valor:</strong><br>
                            <span class="h5 <?= $tx['status'] === 'success' ? 'text-success' : '' ?>">
                                R$ <?= number_format((float)$tx['amount'], 2, ',', '.') ?>
                            </span>
                            <small class="text-muted"><?= h($tx['currency']) ?></small>
                        </div>
                        <div class="col-md-6">
                            <strong>Data/Hora:</strong><br>
                            <?= date('d/m/Y H:i:s', strtotime($tx['created_at'])) ?>
                            <?php if ($tx['processing_time_ms']): ?>
                                <br><small class="text-muted">Tempo de processamento: <?= (int)$tx['processing_time_ms'] ?>ms</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($tx['payment_method']): ?>
                        <div class="mb-3">
                            <strong>Método de Pagamento:</strong><br>
                            <?= h($tx['payment_method']) ?>
                            <?php if ($tx['card_type']): ?>
                                - <?= h(ucfirst($tx['card_type'])) ?>
                            <?php endif; ?>
                            <?php if ($tx['card_last_four']): ?>
                                (**** <?= h($tx['card_last_four']) ?>)
                            <?php endif; ?>
                            <?php if ($tx['installments'] && (int)$tx['installments'] > 1): ?>
                                - <?= (int)$tx['installments'] ?>x
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tx['error_code'] || $tx['error_message']): ?>
                        <div class="alert alert-danger">
                            <strong>Erro:</strong><br>
                            <?php if ($tx['error_code']): ?>
                                <strong>Código:</strong> <?= h($tx['error_code']) ?><br>
                            <?php endif; ?>
                            <?php if ($tx['error_message']): ?>
                                <strong>Mensagem:</strong> <?= h($tx['error_message']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tx['ip_address'] || $tx['user_agent']): ?>
                        <div class="mb-3">
                            <strong>Informações da Requisição:</strong><br>
                            <?php if ($tx['ip_address']): ?>
                                <small><strong>IP:</strong> <?= h($tx['ip_address']) ?></small><br>
                            <?php endif; ?>
                            <?php if ($tx['user_agent']): ?>
                                <small><strong>User Agent:</strong> <?= h(substr($tx['user_agent'], 0, 100)) ?><?= strlen($tx['user_agent']) > 100 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$tx['webhook_received'] === 1): ?>
                        <div class="alert alert-info">
                            <i class="las la-bell"></i> <strong>Webhook recebido</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dados da Requisição -->
            <?php if ($requestData): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Dados da Requisição</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?= h(json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dados da Resposta -->
            <?php if ($responseData): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Dados da Resposta</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?= h(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dados do Webhook -->
            <?php if ($webhookData): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Dados do Webhook</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?= h(json_encode($webhookData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Informações do Cliente -->
            <?php if ($tx['first_name']): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Nome:</strong><br>
                            <?= h($tx['first_name'] . ' ' . $tx['last_name']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Email:</strong><br>
                            <a href="mailto:<?= h($tx['client_email']) ?>"><?= h($tx['client_email']) ?></a>
                        </div>
                        <?php if ($tx['company_name']): ?>
                            <div class="mb-2">
                                <strong>Empresa:</strong><br>
                                <?= h($tx['company_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($tx['phone']): ?>
                            <div class="mb-2">
                                <strong>Telefone:</strong><br>
                                <?= h($tx['phone']) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <a href="/admin/client_edit.php?id=<?= (int)$tx['client_id'] ?>" class="btn btn-sm btn-primary">
                                <i class="las la-user me-1"></i> Ver Cliente
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Fatura Vinculada -->
            <?php if ($tx['invoice_number']): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Fatura</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Número:</strong><br>
                            <a href="/admin/invoice_edit.php?id=<?= (int)$tx['invoice_id'] ?>" class="text-decoration-none">
                                <?= h($tx['invoice_number']) ?>
                            </a>
                        </div>
                        <div class="mb-2">
                            <strong>Valor:</strong><br>
                            R$ <?= number_format((float)$tx['invoice_total'], 2, ',', '.') ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?= $tx['invoice_status'] === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($tx['invoice_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pedido Vinculado -->
            <?php if ($tx['order_number']): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Número:</strong><br>
                            <a href="/admin/order_edit.php?id=<?= (int)$tx['order_id'] ?>" class="text-decoration-none">
                                <?= h($tx['order_number']) ?>
                            </a>
                        </div>
                        <div class="mb-2">
                            <strong>Valor:</strong><br>
                            R$ <?= number_format((float)$tx['order_amount'], 2, ',', '.') ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong><br>
                            <span class="badge bg-info"><?= ucfirst($tx['order_status']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Informações do Sistema -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Informações do Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <div><strong>ID:</strong> #<?= (int)$id ?></div>
                        <div><strong>Criado em:</strong> <?= date('d/m/Y H:i:s', strtotime($tx['created_at'])) ?></div>
                        <?php if ($tx['updated_at'] && $tx['updated_at'] !== $tx['created_at']): ?>
                            <div><strong>Atualizado em:</strong> <?= date('d/m/Y H:i:s', strtotime($tx['updated_at'])) ?></div>
                        <?php endif; ?>
                        <?php if ($tx['notes']): ?>
                            <hr>
                            <div><strong>Notas:</strong><br><?= nl2br(h($tx['notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

