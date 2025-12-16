<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = $id ? 'Editar Processamento' : 'Novo Processamento de Cartão Off-line';
$active = 'offline_credit_card';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'client_id' => 0,
    'invoice_id' => null,
    'order_id' => null,
    'processing_number' => '',
    'amount' => '0.00',
    'currency' => 'BRL',
    'card_type' => '',
    'card_last_four' => '',
    'card_holder_name' => '',
    'installments' => 1,
    'processing_method' => 'manual',
    'authorization_code' => '',
    'transaction_id' => '',
    'status' => 'pending',
    'cvv_verified' => 0,
    'address_verified' => 0,
    'risk_score' => null,
    'processor_response' => '',
    'notes' => '',
];

// Gerar número do processamento se for novo
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM offline_credit_card_processings WHERE processing_number LIKE 'CC-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['processing_number'] = 'CC-' . date('Y') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $item['processing_number'] = 'CC-' . date('Y') . '-0001';
    }
}

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM offline_credit_card_processings WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Processamento não encontrado.');
        }
        $item = array_merge($item, $row);
        if ($item['authorization_date']) {
            $item['authorization_date'] = date('Y-m-d\TH:i', strtotime($item['authorization_date']));
        }
        if ($item['capture_date']) {
            $item['capture_date'] = date('Y-m-d\TH:i', strtotime($item['capture_date']));
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar processamento.');
    }
}

// Buscar clientes, faturas e pedidos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email, company_name FROM clients WHERE status='active' ORDER BY first_name, last_name")->fetchAll();
    
    // Carregar faturas e pedidos se cliente selecionado
    $selectedClientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (int)$item['client_id'];
    if ($selectedClientId > 0) {
        $stmt = db()->prepare("SELECT id, invoice_number, total, status FROM invoices WHERE client_id=? AND status='unpaid' ORDER BY created_at DESC");
        $stmt->execute([$selectedClientId]);
        $invoices = $stmt->fetchAll();
        
        $stmt = db()->prepare("SELECT id, order_number, amount, status FROM orders WHERE client_id=? ORDER BY created_at DESC");
        $stmt->execute([$selectedClientId]);
        $orders = $stmt->fetchAll();
        
        // Atualizar client_id se veio via GET
        if (isset($_GET['client_id'])) {
            $item['client_id'] = $selectedClientId;
        }
    } else {
        $invoices = [];
        $orders = [];
    }
} catch (Throwable $e) {
    $clients = [];
    $invoices = [];
    $orders = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = (int)($_POST['client_id'] ?? 0);
    $invoiceId = isset($_POST['invoice_id']) && $_POST['invoice_id'] !== '' ? (int)$_POST['invoice_id'] : null;
    $orderId = isset($_POST['order_id']) && $_POST['order_id'] !== '' ? (int)$_POST['order_id'] : null;
    $processingNumber = trim((string)($_POST['processing_number'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $cardType = trim((string)($_POST['card_type'] ?? ''));
    $cardLastFour = trim((string)($_POST['card_last_four'] ?? ''));
    $cardHolderName = trim((string)($_POST['card_holder_name'] ?? ''));
    $installments = (int)($_POST['installments'] ?? 1);
    $processingMethod = trim((string)($_POST['processing_method'] ?? 'manual'));
    $authorizationCode = trim((string)($_POST['authorization_code'] ?? ''));
    $transactionId = trim((string)($_POST['transaction_id'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'pending'));
    $cvvVerified = isset($_POST['cvv_verified']) ? 1 : 0;
    $addressVerified = isset($_POST['address_verified']) ? 1 : 0;
    $riskScore = isset($_POST['risk_score']) && $_POST['risk_score'] !== '' ? (int)$_POST['risk_score'] : null;
    $processorResponse = trim((string)($_POST['processor_response'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $authorizationDate = trim((string)($_POST['authorization_date'] ?? ''));
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($processingNumber === '') $error = 'O número do processamento é obrigatório.';
    if ($amount <= 0) $error = 'O valor deve ser maior que zero.';
    if ($cardLastFour === '' || strlen($cardLastFour) !== 4) $error = 'Os últimos 4 dígitos do cartão são obrigatórios.';
    if ($installments < 1 || $installments > 12) $error = 'O número de parcelas deve ser entre 1 e 12.';
    
    if (!in_array($status, ['pending', 'authorized', 'captured', 'declined', 'cancelled', 'refunded'], true)) {
        $status = 'pending';
    }
    
    if (!in_array($processingMethod, ['manual', 'terminal', 'phone', 'email'], true)) {
        $processingMethod = 'manual';
    }
    
    // Verificar se o número já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM offline_credit_card_processings WHERE processing_number=? AND id != ?");
            $stmt->execute([$processingNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de processamento já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'client_id' => $clientId,
        'invoice_id' => $invoiceId,
        'order_id' => $orderId,
        'processing_number' => $processingNumber,
        'amount' => $amount,
        'currency' => $currency,
        'card_type' => $cardType !== '' ? $cardType : null,
        'card_last_four' => $cardLastFour,
        'card_holder_name' => $cardHolderName !== '' ? $cardHolderName : null,
        'installments' => $installments,
        'processing_method' => $processingMethod,
        'authorization_code' => $authorizationCode !== '' ? $authorizationCode : null,
        'transaction_id' => $transactionId !== '' ? $transactionId : null,
        'status' => $status,
        'cvv_verified' => $cvvVerified,
        'address_verified' => $addressVerified,
        'risk_score' => $riskScore,
        'processor_response' => $processorResponse !== '' ? $processorResponse : null,
        'notes' => $notes !== '' ? $notes : null,
        'authorization_date' => $authorizationDate !== '' ? date('Y-m-d H:i:s', strtotime($authorizationDate)) : null,
        'processed_by' => (int)($_SESSION['admin_user_id'] ?? 0),
    ];
    
    // Se status for authorized ou captured, definir data de autorização
    if (in_array($status, ['authorized', 'captured']) && !$data['authorization_date']) {
        $data['authorization_date'] = date('Y-m-d H:i:s');
    }
    
    // Se status for captured, definir data de captura
    if ($status === 'captured') {
        $data['capture_date'] = date('Y-m-d H:i:s');
    }

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->beginTransaction();
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE offline_credit_card_processings SET client_id=:client_id, invoice_id=:invoice_id, order_id=:order_id, processing_number=:processing_number, amount=:amount, currency=:currency, card_type=:card_type, card_last_four=:card_last_four, card_holder_name=:card_holder_name, installments=:installments, processing_method=:processing_method, authorization_code=:authorization_code, transaction_id=:transaction_id, status=:status, cvv_verified=:cvv_verified, address_verified=:address_verified, risk_score=:risk_score, processor_response=:processor_response, notes=:notes, authorization_date=:authorization_date, processed_by=:processed_by WHERE id=:id");
                if ($status === 'captured') {
                    $stmt = db()->prepare("UPDATE offline_credit_card_processings SET client_id=:client_id, invoice_id=:invoice_id, order_id=:order_id, processing_number=:processing_number, amount=:amount, currency=:currency, card_type=:card_type, card_last_four=:card_last_four, card_holder_name=:card_holder_name, installments=:installments, processing_method=:processing_method, authorization_code=:authorization_code, transaction_id=:transaction_id, status=:status, cvv_verified=:cvv_verified, address_verified=:address_verified, risk_score=:risk_score, processor_response=:processor_response, notes=:notes, authorization_date=:authorization_date, capture_date=:capture_date, processed_by=:processed_by WHERE id=:id");
                    $data['capture_date'] = date('Y-m-d H:i:s');
                }
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Processamento atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO offline_credit_card_processings (client_id, invoice_id, order_id, processing_number, amount, currency, card_type, card_last_four, card_holder_name, installments, processing_method, authorization_code, transaction_id, status, cvv_verified, address_verified, risk_score, processor_response, notes, authorization_date, capture_date, processed_by) VALUES (:client_id, :invoice_id, :order_id, :processing_number, :amount, :currency, :card_type, :card_last_four, :card_holder_name, :installments, :processing_method, :authorization_code, :transaction_id, :status, :cvv_verified, :address_verified, :risk_score, :processor_response, :notes, :authorization_date, :capture_date, :processed_by)");
                if ($status === 'captured') {
                    $data['capture_date'] = date('Y-m-d H:i:s');
                } else {
                    $data['capture_date'] = null;
                }
                $stmt->execute($data);
                $_SESSION['success'] = 'Processamento criado com sucesso.';
            }
            
            // Se capturado e houver fatura vinculada, atualizar fatura
            if ($status === 'captured' && $invoiceId) {
                db()->prepare("UPDATE invoices SET status='paid', paid_date=CURDATE(), payment_method='Cartão de Crédito Off-line' WHERE id=?")->execute([$invoiceId]);
            }
            
            db()->commit();
            header('Location: /admin/offline_credit_card.php');
            exit;
        } catch (Throwable $e) {
            db()->rollBack();
            $error = 'Erro ao salvar processamento: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Processamento' : 'Novo Processamento de Cartão Off-line' ?></h1>
        <a href="/admin/offline_credit_card.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="processingForm">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações do Processamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="processing_number" class="form-label">Número do Processamento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="processing_number" name="processing_number" value="<?= h($item['processing_number']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select" id="client_id" name="client_id" required onchange="loadClientData()">
                                    <option value="">Selecione um cliente...</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= (int)$client['id'] ?>" <?= (int)$item['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                                            <?= h($client['first_name'] . ' ' . $client['last_name']) ?>
                                            <?php if ($client['company_name']): ?>
                                                - <?= h($client['company_name']) ?>
                                            <?php endif; ?>
                                            (<?= h($client['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="invoice_id" class="form-label">Fatura (opcional)</label>
                                <select class="form-select" id="invoice_id" name="invoice_id">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <option value="<?= (int)$invoice['id'] ?>" <?= $item['invoice_id'] && (int)$item['invoice_id'] === (int)$invoice['id'] ? 'selected' : '' ?>>
                                            <?= h($invoice['invoice_number']) ?> - R$ <?= number_format((float)$invoice['total'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="order_id" class="form-label">Pedido (opcional)</label>
                                <select class="form-select" id="order_id" name="order_id">
                                    <option value="">Nenhum</option>
                                    <?php foreach ($orders as $order): ?>
                                        <option value="<?= (int)$order['id'] ?>" <?= $item['order_id'] && (int)$item['order_id'] === (int)$order['id'] ? 'selected' : '' ?>>
                                            <?= h($order['order_number']) ?> - R$ <?= number_format((float)$order['amount'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Valor <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" value="<?= h($item['amount']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency" class="form-label">Moeda</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="BRL" <?= $item['currency'] === 'BRL' ? 'selected' : '' ?>>BRL (Real)</option>
                                    <option value="USD" <?= $item['currency'] === 'USD' ? 'selected' : '' ?>>USD (Dólar)</option>
                                    <option value="EUR" <?= $item['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Informações do Cartão</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="card_type" class="form-label">Tipo de Cartão</label>
                                <select class="form-select" id="card_type" name="card_type">
                                    <option value="">Selecione...</option>
                                    <option value="visa" <?= $item['card_type'] === 'visa' ? 'selected' : '' ?>>Visa</option>
                                    <option value="mastercard" <?= $item['card_type'] === 'mastercard' ? 'selected' : '' ?>>Mastercard</option>
                                    <option value="amex" <?= $item['card_type'] === 'amex' ? 'selected' : '' ?>>American Express</option>
                                    <option value="elo" <?= $item['card_type'] === 'elo' ? 'selected' : '' ?>>Elo</option>
                                    <option value="diners" <?= $item['card_type'] === 'diners' ? 'selected' : '' ?>>Diners Club</option>
                                    <option value="other" <?= $item['card_type'] === 'other' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="card_last_four" class="form-label">Últimos 4 Dígitos <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="card_last_four" name="card_last_four" value="<?= h($item['card_last_four']) ?>" maxlength="4" pattern="[0-9]{4}" required>
                                <small class="text-muted">Apenas os últimos 4 dígitos do cartão</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="card_holder_name" class="form-label">Nome do Portador</label>
                            <input type="text" class="form-control" id="card_holder_name" name="card_holder_name" value="<?= h($item['card_holder_name']) ?>" placeholder="Nome como está no cartão">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="installments" class="form-label">Parcelas <span class="text-danger">*</span></label>
                                <select class="form-select" id="installments" name="installments" required>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int)$item['installments'] === $i ? 'selected' : '' ?>>
                                            <?= $i ?>x
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="processing_method" class="form-label">Método de Processamento <span class="text-danger">*</span></label>
                                <select class="form-select" id="processing_method" name="processing_method" required>
                                    <option value="manual" <?= $item['processing_method'] === 'manual' ? 'selected' : '' ?>>Manual</option>
                                    <option value="terminal" <?= $item['processing_method'] === 'terminal' ? 'selected' : '' ?>>Terminal</option>
                                    <option value="phone" <?= $item['processing_method'] === 'phone' ? 'selected' : '' ?>>Telefone</option>
                                    <option value="email" <?= $item['processing_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Autorização e Transação</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="authorization_code" class="form-label">Código de Autorização</label>
                                <input type="text" class="form-control" id="authorization_code" name="authorization_code" value="<?= h($item['authorization_code']) ?>" placeholder="Código retornado pelo processador">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="transaction_id" class="form-label">ID da Transação</label>
                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" value="<?= h($item['transaction_id']) ?>" placeholder="ID da transação no processador">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="authorization_date" class="form-label">Data/Hora da Autorização</label>
                            <input type="datetime-local" class="form-control" id="authorization_date" name="authorization_date" value="<?= h($item['authorization_date']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="processor_response" class="form-label">Resposta do Processador</label>
                            <textarea class="form-control" id="processor_response" name="processor_response" rows="3" placeholder="Resposta completa do processador..."><?= h($item['processor_response']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Observações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= h($item['notes']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Status e Verificações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                <option value="authorized" <?= $item['status'] === 'authorized' ? 'selected' : '' ?>>Autorizado</option>
                                <option value="captured" <?= $item['status'] === 'captured' ? 'selected' : '' ?>>Capturado</option>
                                <option value="declined" <?= $item['status'] === 'declined' ? 'selected' : '' ?>>Recusado</option>
                                <option value="cancelled" <?= $item['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                <option value="refunded" <?= $item['status'] === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="cvv_verified" name="cvv_verified" value="1" <?= (int)$item['cvv_verified'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cvv_verified">
                                    CVV Verificado
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="address_verified" name="address_verified" value="1" <?= (int)$item['address_verified'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="address_verified">
                                    Endereço Verificado
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="risk_score" class="form-label">Score de Risco (0-100)</label>
                            <input type="number" class="form-control" id="risk_score" name="risk_score" value="<?= $item['risk_score'] ?>" min="0" max="100">
                            <small class="text-muted">Score de risco da transação</small>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="small text-muted">
                                    <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                    <?php if ($item['processed_by']): ?>
                                        <div><strong>Processado por:</strong> <?= h($item['processed_by_name'] ?? 'N/A') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/offline_credit_card.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function loadClientData() {
    const clientId = document.getElementById('client_id').value;
    if (!clientId) {
        document.getElementById('invoice_id').innerHTML = '<option value="">Nenhuma</option>';
        document.getElementById('order_id').innerHTML = '<option value="">Nenhum</option>';
        return;
    }
    
    // Recarregar página com cliente selecionado para carregar faturas e pedidos
    const currentId = <?= $id ?>;
    const url = '/admin/offline_credit_card_edit.php' + (currentId ? '?id=' + currentId + '&client_id=' + clientId : '?client_id=' + clientId);
    window.location.href = url;
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

