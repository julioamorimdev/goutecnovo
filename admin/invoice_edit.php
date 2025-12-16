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

$page_title = $id ? 'Editar Fatura' : 'Nova Fatura';
$active = 'invoices';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'order_id' => null,
    'client_id' => null,
    'invoice_number' => '',
    'status' => 'unpaid',
    'subtotal' => '0.00',
    'tax' => '0.00',
    'total' => '0.00',
    'currency' => 'BRL',
    'due_date' => '',
    'paid_date' => '',
    'payment_method' => '',
    'notes' => '',
];

// Gerar número da fatura se for nova
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM invoices WHERE invoice_number LIKE 'INV-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['invoice_number'] = 'INV-' . date('Y') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
        $item['due_date'] = date('Y-m-d', strtotime('+30 days'));
    } catch (Throwable $e) {
        $item['invoice_number'] = 'INV-' . date('Y') . '-001';
        $item['due_date'] = date('Y-m-d', strtotime('+30 days'));
    }
}

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM invoices WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Fatura não encontrada.');
        }
        $item = array_merge($item, $row);
        if ($item['due_date']) {
            $item['due_date'] = date('Y-m-d', strtotime($item['due_date']));
        }
        if ($item['paid_date']) {
            $item['paid_date'] = date('Y-m-d', strtotime($item['paid_date']));
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar fatura.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $orderId = isset($_POST['order_id']) && $_POST['order_id'] !== '' ? (int)$_POST['order_id'] : null;
    $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'unpaid'));
    $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.00;
    $tax = isset($_POST['tax']) ? (float)$_POST['tax'] : 0.00;
    $total = isset($_POST['total']) ? (float)$_POST['total'] : 0.00;
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $dueDate = isset($_POST['due_date']) && $_POST['due_date'] !== '' ? trim((string)$_POST['due_date']) : null;
    $paidDate = isset($_POST['paid_date']) && $_POST['paid_date'] !== '' ? trim((string)$_POST['paid_date']) : null;
    $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($invoiceNumber === '') $error = 'O número da fatura é obrigatório.';
    if (!in_array($status, ['unpaid', 'paid', 'cancelled', 'refunded'], true)) {
        $status = 'unpaid';
    }
    
    // Calcular total se não fornecido
    if ($total <= 0) {
        $total = $subtotal + $tax;
    }
    
    // Se status for pago e não tiver data de pagamento, usar data atual
    if ($status === 'paid' && !$paidDate) {
        $paidDate = date('Y-m-d');
    }
    
    // Verificar se o número da fatura já existe (exceto para a própria fatura)
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM invoices WHERE invoice_number=? AND id != ?");
            $stmt->execute([$invoiceNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de fatura já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'order_id' => $orderId,
        'client_id' => $clientId,
        'invoice_number' => $invoiceNumber,
        'status' => $status,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'currency' => $currency,
        'due_date' => $dueDate,
        'paid_date' => $paidDate,
        'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
        'notes' => $notes !== '' ? $notes : null,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE invoices SET order_id=:order_id, client_id=:client_id, invoice_number=:invoice_number, status=:status, subtotal=:subtotal, tax=:tax, total=:total, currency=:currency, due_date=:due_date, paid_date=:paid_date, payment_method=:payment_method, notes=:notes WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Fatura atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO invoices (order_id, client_id, invoice_number, status, subtotal, tax, total, currency, due_date, paid_date, payment_method, notes) VALUES (:order_id, :client_id, :invoice_number, :status, :subtotal, :tax, :total, :currency, :due_date, :paid_date, :payment_method, :notes)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Fatura criada com sucesso.';
            }
            header('Location: /admin/invoices.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar fatura: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}

// Buscar clientes e pedidos para os selects
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
    $orders = db()->query("SELECT id, order_number, client_id FROM orders ORDER BY created_at DESC")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
    $orders = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Fatura' : 'Nova Fatura' ?></h1>
        <a href="/admin/invoices.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="invoiceForm">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações da Fatura</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="invoice_number" class="form-label">Número da Fatura <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?= h($item['invoice_number']) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Selecione um cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= (int)$client['id'] ?>" <?= (int)$item['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                                            <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="order_id" class="form-label">Pedido</label>
                                <select class="form-select" id="order_id" name="order_id">
                                    <option value="">Nenhum pedido</option>
                                    <?php foreach ($orders as $order): ?>
                                        <option value="<?= (int)$order['id'] ?>" <?= (int)$item['order_id'] === (int)$order['id'] ? 'selected' : '' ?>>
                                            <?= h($order['order_number']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="unpaid" <?= $item['status'] === 'unpaid' ? 'selected' : '' ?>>Não Paga</option>
                                    <option value="paid" <?= $item['status'] === 'paid' ? 'selected' : '' ?>>Paga</option>
                                    <option value="cancelled" <?= $item['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                                    <option value="refunded" <?= $item['status'] === 'refunded' ? 'selected' : '' ?>>Reembolsada</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency" class="form-label">Moeda</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="BRL" <?= $item['currency'] === 'BRL' ? 'selected' : '' ?>>BRL (R$)</option>
                                    <option value="USD" <?= $item['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                    <option value="EUR" <?= $item['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Valores</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="subtotal" class="form-label">Subtotal</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="subtotal" name="subtotal" value="<?= number_format((float)$item['subtotal'], 2, '.', '') ?>" step="0.01" min="0" onchange="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="tax" class="form-label">Impostos</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="tax" name="tax" value="<?= number_format((float)$item['tax'], 2, '.', '') ?>" step="0.01" min="0" onchange="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="total" class="form-label">Total</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="total" name="total" value="<?= number_format((float)$item['total'], 2, '.', '') ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Datas e Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Data de Vencimento</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?= h($item['due_date']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="paid_date" class="form-label">Data de Pagamento</label>
                                <input type="date" class="form-control" id="paid_date" name="paid_date" value="<?= h($item['paid_date']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_method" class="form-label">Método de Pagamento</label>
                                <input type="text" class="form-control" id="payment_method" name="payment_method" value="<?= h($item['payment_method']) ?>" placeholder="Cartão, PIX, Boleto...">
                            </div>
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
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?= h($item['notes']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">ID da Fatura</label>
                                <div class="form-control-plaintext">#<?= (int)$id ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Criada em</label>
                                <div class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                            </div>
                            <?php if ($item['updated_at'] ?? ''): ?>
                                <div class="mb-3">
                                    <label class="form-label">Última atualização</label>
                                    <div class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <small>A fatura será criada com as informações fornecidas.</small>
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
            <a href="/admin/invoices.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const total = subtotal + tax;
    document.getElementById('total').value = total.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
