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

$page_title = $id ? 'Editar Pedido' : 'Novo Pedido';
$active = 'orders';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'client_id' => null,
    'plan_id' => null,
    'order_number' => '',
    'status' => 'pending',
    'billing_cycle' => 'monthly',
    'amount' => '0.00',
    'setup_fee' => '0.00',
    'currency' => 'BRL',
    'notes' => '',
];

// Gerar número do pedido se for novo
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM orders WHERE order_number LIKE 'ORD-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['order_number'] = 'ORD-' . date('Y') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $item['order_number'] = 'ORD-' . date('Y') . '-001';
    }
}

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Pedido não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar pedido.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $planId = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
    $orderNumber = trim((string)($_POST['order_number'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'pending'));
    $billingCycle = trim((string)($_POST['billing_cycle'] ?? 'monthly'));
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;
    $setupFee = isset($_POST['setup_fee']) ? (float)$_POST['setup_fee'] : 0.00;
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($orderNumber === '') $error = 'O número do pedido é obrigatório.';
    if (!in_array($status, ['pending', 'active', 'suspended', 'cancelled', 'fraud'], true)) {
        $status = 'pending';
    }
    if (!in_array($billingCycle, ['monthly', 'quarterly', 'semiannual', 'annual', 'biennial', 'triennal'], true)) {
        $billingCycle = 'monthly';
    }
    
    // Verificar se o número do pedido já existe (exceto para o próprio pedido)
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM orders WHERE order_number=? AND id != ?");
            $stmt->execute([$orderNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de pedido já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'client_id' => $clientId,
        'plan_id' => $planId,
        'order_number' => $orderNumber,
        'status' => $status,
        'billing_cycle' => $billingCycle,
        'amount' => $amount,
        'setup_fee' => $setupFee,
        'currency' => $currency,
        'notes' => $notes !== '' ? $notes : null,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE orders SET client_id=:client_id, plan_id=:plan_id, order_number=:order_number, status=:status, billing_cycle=:billing_cycle, amount=:amount, setup_fee=:setup_fee, currency=:currency, notes=:notes WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Pedido atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO orders (client_id, plan_id, order_number, status, billing_cycle, amount, setup_fee, currency, notes) VALUES (:client_id, :plan_id, :order_number, :status, :billing_cycle, :amount, :setup_fee, :currency, :notes)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Pedido criado com sucesso.';
            }
            header('Location: /admin/orders.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar pedido: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}

// Buscar clientes e planos para os selects
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
    $plans = db()->query("SELECT id, name, category_id FROM plans WHERE is_enabled=1 ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
    $plans = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Pedido' : 'Novo Pedido' ?></h1>
        <a href="/admin/orders.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="order_number" class="form-label">Número do Pedido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="order_number" name="order_number" value="<?= h($item['order_number']) ?>" required>
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
                                <label for="plan_id" class="form-label">Plano</label>
                                <select class="form-select" id="plan_id" name="plan_id">
                                    <option value="">Nenhum plano</option>
                                    <?php foreach ($plans as $plan): ?>
                                        <option value="<?= (int)$plan['id'] ?>" <?= (int)$item['plan_id'] === (int)$plan['id'] ? 'selected' : '' ?>>
                                            <?= h($plan['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="billing_cycle" class="form-label">Ciclo de Cobrança</label>
                                <select class="form-select" id="billing_cycle" name="billing_cycle">
                                    <option value="monthly" <?= $item['billing_cycle'] === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    <option value="quarterly" <?= $item['billing_cycle'] === 'quarterly' ? 'selected' : '' ?>>Trimestral</option>
                                    <option value="semiannual" <?= $item['billing_cycle'] === 'semiannual' ? 'selected' : '' ?>>Semestral</option>
                                    <option value="annual" <?= $item['billing_cycle'] === 'annual' ? 'selected' : '' ?>>Anual</option>
                                    <option value="biennial" <?= $item['billing_cycle'] === 'biennial' ? 'selected' : '' ?>>Bienal</option>
                                    <option value="triennal" <?= $item['billing_cycle'] === 'triennal' ? 'selected' : '' ?>>Trienal</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="suspended" <?= $item['status'] === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                                    <option value="cancelled" <?= $item['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                    <option value="fraud" <?= $item['status'] === 'fraud' ? 'selected' : '' ?>>Fraude</option>
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
                                <label for="amount" class="form-label">Valor <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" value="<?= number_format((float)$item['amount'], 2, '.', '') ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="setup_fee" class="form-label">Taxa de Instalação</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="setup_fee" name="setup_fee" value="<?= number_format((float)$item['setup_fee'], 2, '.', '') ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
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
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">ID do Pedido</label>
                                <div class="form-control-plaintext">#<?= (int)$id ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Criado em</label>
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
                                <small>O pedido será criado com as informações fornecidas.</small>
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
            <a href="/admin/orders.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
