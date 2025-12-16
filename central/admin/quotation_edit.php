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

$page_title = $id ? 'Editar Orçamento' : 'Novo Orçamento';
$active = 'quotations';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'client_id' => 0,
    'quotation_number' => '',
    'title' => '',
    'description' => '',
    'status' => 'draft',
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'subtotal' => '0.00',
    'discount' => '0.00',
    'discount_type' => '',
    'tax' => '0.00',
    'total' => '0.00',
    'currency' => 'BRL',
    'notes' => '',
    'terms' => '',
];

// Gerar número do orçamento se for novo
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM quotations WHERE quotation_number LIKE 'QUO-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['quotation_number'] = 'QUO-' . date('Y') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $item['quotation_number'] = 'QUO-' . date('Y') . '-001';
    }
}

$quotationItems = [];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM quotations WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Orçamento não encontrado.');
        }
        $item = array_merge($item, $row);
        if ($item['valid_until']) {
            $item['valid_until'] = date('Y-m-d', strtotime($item['valid_until']));
        }
        
        // Buscar itens do orçamento
        $stmt = db()->prepare("SELECT qi.*, bi.code as billable_code, bi.name as billable_name FROM quotation_items qi LEFT JOIN billable_items bi ON qi.billable_item_id = bi.id WHERE qi.quotation_id=? ORDER BY qi.sort_order ASC, qi.id ASC");
        $stmt->execute([$id]);
        $quotationItems = $stmt->fetchAll();
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar orçamento.');
    }
}

// Buscar clientes e itens faturáveis
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email, company_name FROM clients WHERE status='active' ORDER BY first_name, last_name")->fetchAll();
    $billableItems = db()->query("SELECT id, code, name, unit, price, tax_rate FROM billable_items WHERE is_enabled=1 ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
    $billableItems = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = (int)($_POST['client_id'] ?? 0);
    $quotationNumber = trim((string)($_POST['quotation_number'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'draft'));
    $validUntil = trim((string)($_POST['valid_until'] ?? ''));
    $discount = (float)($_POST['discount'] ?? 0);
    $discountType = trim((string)($_POST['discount_type'] ?? ''));
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $terms = trim((string)($_POST['terms'] ?? ''));
    
    // Processar itens
    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $itemData) {
            if (!empty($itemData['description'])) {
                $items[] = [
                    'billable_item_id' => !empty($itemData['billable_item_id']) ? (int)$itemData['billable_item_id'] : null,
                    'description' => trim($itemData['description']),
                    'quantity' => (float)($itemData['quantity'] ?? 1),
                    'unit' => trim($itemData['unit'] ?? 'unit'),
                    'unit_price' => (float)($itemData['unit_price'] ?? 0),
                    'tax_rate' => (float)($itemData['tax_rate'] ?? 0),
                ];
            }
        }
    }
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($quotationNumber === '') $error = 'O número do orçamento é obrigatório.';
    if (empty($items)) $error = 'Adicione pelo menos um item ao orçamento.';
    
    if (!in_array($status, ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'], true)) {
        $status = 'draft';
    }
    
    // Calcular totais
    $subtotal = 0;
    $tax = 0;
    foreach ($items as &$it) {
        $it['subtotal'] = $it['quantity'] * $it['unit_price'];
        $it['tax_amount'] = $it['subtotal'] * ($it['tax_rate'] / 100);
        $it['total'] = $it['subtotal'] + $it['tax_amount'];
        $subtotal += $it['subtotal'];
        $tax += $it['tax_amount'];
    }
    unset($it);
    
    // Aplicar desconto
    $discountAmount = 0;
    if ($discount > 0) {
        if ($discountType === 'percentage') {
            $discountAmount = $subtotal * ($discount / 100);
        } else {
            $discountAmount = $discount;
        }
    }
    
    $total = $subtotal - $discountAmount + $tax;
    
    // Verificar se o número já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM quotations WHERE quotation_number=? AND id != ?");
            $stmt->execute([$quotationNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de orçamento já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'client_id' => $clientId,
        'quotation_number' => $quotationNumber,
        'title' => $title !== '' ? $title : null,
        'description' => $description !== '' ? $description : null,
        'status' => $status,
        'valid_until' => $validUntil !== '' ? $validUntil : null,
        'subtotal' => $subtotal,
        'discount' => $discountAmount,
        'discount_type' => $discountType !== '' ? $discountType : null,
        'tax' => $tax,
        'total' => $total,
        'currency' => $currency,
        'notes' => $notes !== '' ? $notes : null,
        'terms' => $terms !== '' ? $terms : null,
        'created_by' => (int)($_SESSION['admin_user_id'] ?? 0),
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->beginTransaction();
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE quotations SET client_id=:client_id, quotation_number=:quotation_number, title=:title, description=:description, status=:status, valid_until=:valid_until, subtotal=:subtotal, discount=:discount, discount_type=:discount_type, tax=:tax, total=:total, currency=:currency, notes=:notes, terms=:terms WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                
                // Remover itens antigos
                db()->prepare("DELETE FROM quotation_items WHERE quotation_id=?")->execute([$id]);
            } else {
                $stmt = db()->prepare("INSERT INTO quotations (client_id, quotation_number, title, description, status, valid_until, subtotal, discount, discount_type, tax, total, currency, notes, terms, created_by) VALUES (:client_id, :quotation_number, :title, :description, :status, :valid_until, :subtotal, :discount, :discount_type, :tax, :total, :currency, :notes, :terms, :created_by)");
                $stmt->execute($data);
                $id = (int)db()->lastInsertId();
            }
            
            // Inserir itens
            foreach ($items as $index => $it) {
                $stmt = db()->prepare("INSERT INTO quotation_items (quotation_id, billable_item_id, description, quantity, unit, unit_price, tax_rate, subtotal, tax_amount, total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id,
                    $it['billable_item_id'],
                    $it['description'],
                    $it['quantity'],
                    $it['unit'],
                    $it['unit_price'],
                    $it['tax_rate'],
                    $it['subtotal'],
                    $it['tax_amount'],
                    $it['total'],
                    $index
                ]);
            }
            
            db()->commit();
            $_SESSION['success'] = 'Orçamento ' . ($id > 0 ? 'atualizado' : 'criado') . ' com sucesso.';
            header('Location: /admin/quotations.php');
            exit;
        } catch (Throwable $e) {
            db()->rollBack();
            $error = 'Erro ao salvar orçamento: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    $quotationItems = $items;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Orçamento' : 'Novo Orçamento' ?></h1>
        <a href="/admin/quotations.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="quotationForm">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações do Orçamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quotation_number" class="form-label">Número do Orçamento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quotation_number" name="quotation_number" value="<?= h($item['quotation_number']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select" id="client_id" name="client_id" required>
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

                        <div class="mb-3">
                            <label for="title" class="form-label">Título</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" placeholder="Título do orçamento">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="valid_until" class="form-label">Válido Até</label>
                                <input type="date" class="form-control" id="valid_until" name="valid_until" value="<?= h($item['valid_until']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft" <?= $item['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                                    <option value="sent" <?= $item['status'] === 'sent' ? 'selected' : '' ?>>Enviado</option>
                                    <option value="accepted" <?= $item['status'] === 'accepted' ? 'selected' : '' ?>>Aceito</option>
                                    <option value="rejected" <?= $item['status'] === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                                    <option value="expired" <?= $item['status'] === 'expired' ? 'selected' : '' ?>>Expirado</option>
                                    <option value="converted" <?= $item['status'] === 'converted' ? 'selected' : '' ?>>Convertido</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itens do Orçamento -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Itens do Orçamento</h5>
                        <button type="button" class="btn btn-sm btn-light" onclick="addItem()">
                            <i class="las la-plus me-1"></i> Adicionar Item
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="itemsContainer">
                            <?php if (empty($quotationItems)): ?>
                                <div class="text-center py-3 text-muted">
                                    <p>Nenhum item adicionado. Clique em "Adicionar Item" para começar.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($quotationItems as $index => $it): ?>
                                    <div class="item-row border rounded p-3 mb-3" data-index="<?= $index ?>">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Item Faturável (opcional)</label>
                                                <select class="form-select billable-item-select" name="items[<?= $index ?>][billable_item_id]" onchange="fillFromBillableItem(this, <?= $index ?>)">
                                                    <option value="">Selecione um item...</option>
                                                    <?php foreach ($billableItems as $bi): ?>
                                                        <option value="<?= (int)$bi['id'] ?>" 
                                                                data-code="<?= h($bi['code']) ?>"
                                                                data-name="<?= h($bi['name']) ?>"
                                                                data-unit="<?= h($bi['unit']) ?>"
                                                                data-price="<?= h($bi['price']) ?>"
                                                                data-tax-rate="<?= h($bi['tax_rate']) ?>"
                                                                <?= isset($it['billable_item_id']) && (int)$it['billable_item_id'] === (int)$bi['id'] ? 'selected' : '' ?>>
                                                            <?= h($bi['code']) ?> - <?= h($bi['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control item-description" name="items[<?= $index ?>][description]" value="<?= h($it['description'] ?? $it['billable_name'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Quantidade</label>
                                                <input type="number" class="form-control item-quantity" name="items[<?= $index ?>][quantity]" value="<?= h($it['quantity'] ?? 1) ?>" step="0.01" min="0" onchange="calculateItem(<?= $index ?>)" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Unidade</label>
                                                <input type="text" class="form-control item-unit" name="items[<?= $index ?>][unit]" value="<?= h($it['unit'] ?? 'unit') ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Preço Unitário</label>
                                                <input type="number" class="form-control item-unit-price" name="items[<?= $index ?>][unit_price]" value="<?= h($it['unit_price'] ?? 0) ?>" step="0.01" min="0" onchange="calculateItem(<?= $index ?>)" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Taxa Imposto (%)</label>
                                                <input type="number" class="form-control item-tax-rate" name="items[<?= $index ?>][tax_rate]" value="<?= h($it['tax_rate'] ?? 0) ?>" step="0.01" min="0" max="100" onchange="calculateItem(<?= $index ?>)">
                                            </div>
                                            <div class="col-md-12">
                                                <div class="d-flex justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(<?= $index ?>)">
                                                        <i class="las la-trash"></i> Remover
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Termos e Condições</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="terms" class="form-label">Termos e Condições</label>
                            <textarea class="form-control" id="terms" name="terms" rows="4"><?= h($item['terms']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas Internas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= h($item['notes']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Resumo Financeiro</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="currency" class="form-label">Moeda</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="BRL" <?= $item['currency'] === 'BRL' ? 'selected' : '' ?>>BRL (Real)</option>
                                <option value="USD" <?= $item['currency'] === 'USD' ? 'selected' : '' ?>>USD (Dólar)</option>
                                <option value="EUR" <?= $item['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subtotal</label>
                            <div class="h5" id="subtotalDisplay">R$ 0,00</div>
                            <input type="hidden" id="subtotal" name="subtotal" value="0">
                        </div>

                        <div class="mb-3">
                            <label for="discount_type" class="form-label">Tipo de Desconto</label>
                            <select class="form-select" id="discount_type" name="discount_type" onchange="updateDiscountDisplay()">
                                <option value="">Sem desconto</option>
                                <option value="percentage" <?= $item['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentual (%)</option>
                                <option value="fixed" <?= $item['discount_type'] === 'fixed' ? 'selected' : '' ?>>Valor Fixo (R$)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="discountContainer" style="display: none;">
                            <label for="discount" class="form-label">Desconto</label>
                            <input type="number" class="form-control" id="discount" name="discount" value="<?= h($item['discount']) ?>" step="0.01" min="0" onchange="calculateTotal()">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Impostos</label>
                            <div class="h6" id="taxDisplay">R$ 0,00</div>
                            <input type="hidden" id="tax" name="tax" value="0">
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label"><strong>Total</strong></label>
                            <div class="h4 text-success" id="totalDisplay">R$ 0,00</div>
                            <input type="hidden" id="total" name="total" value="0">
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="small text-muted">
                                    <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
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
            <a href="/admin/quotations.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
let itemIndex = <?= count($quotationItems) ?>;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const emptyMsg = container.querySelector('.text-center');
    if (emptyMsg) emptyMsg.remove();
    
    const itemHtml = `
        <div class="item-row border rounded p-3 mb-3" data-index="${itemIndex}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Item Faturável (opcional)</label>
                    <select class="form-select billable-item-select" name="items[${itemIndex}][billable_item_id]" onchange="fillFromBillableItem(this, ${itemIndex})">
                        <option value="">Selecione um item...</option>
                        <?php foreach ($billableItems as $bi): ?>
                            <option value="<?= (int)$bi['id'] ?>" 
                                    data-code="<?= h($bi['code']) ?>"
                                    data-name="<?= h($bi['name']) ?>"
                                    data-unit="<?= h($bi['unit']) ?>"
                                    data-price="<?= h($bi['price']) ?>"
                                    data-tax-rate="<?= h($bi['tax_rate']) ?>">
                                <?= h($bi['code']) ?> - <?= h($bi['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição <span class="text-danger">*</span></label>
                    <input type="text" class="form-control item-description" name="items[${itemIndex}][description]" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade</label>
                    <input type="number" class="form-control item-quantity" name="items[${itemIndex}][quantity]" value="1" step="0.01" min="0" onchange="calculateItem(${itemIndex})" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unidade</label>
                    <input type="text" class="form-control item-unit" name="items[${itemIndex}][unit]" value="unit" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Preço Unitário</label>
                    <input type="number" class="form-control item-unit-price" name="items[${itemIndex}][unit_price]" value="0" step="0.01" min="0" onchange="calculateItem(${itemIndex})" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Taxa Imposto (%)</label>
                    <input type="number" class="form-control item-tax-rate" name="items[${itemIndex}][tax_rate]" value="0" step="0.01" min="0" max="100" onchange="calculateItem(${itemIndex})">
                </div>
                <div class="col-md-12">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${itemIndex})">
                            <i class="las la-trash"></i> Remover
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
}

function removeItem(index) {
    const row = document.querySelector(`.item-row[data-index="${index}"]`);
    if (row) {
        row.remove();
        calculateTotal();
        if (document.querySelectorAll('.item-row').length === 0) {
            document.getElementById('itemsContainer').innerHTML = '<div class="text-center py-3 text-muted"><p>Nenhum item adicionado. Clique em "Adicionar Item" para começar.</p></div>';
        }
    }
}

function fillFromBillableItem(select, index) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        const row = document.querySelector(`.item-row[data-index="${index}"]`);
        row.querySelector('.item-description').value = option.dataset.name || '';
        row.querySelector('.item-unit').value = option.dataset.unit || 'unit';
        row.querySelector('.item-unit-price').value = option.dataset.price || '0';
        row.querySelector('.item-tax-rate').value = option.dataset.taxRate || '0';
        calculateItem(index);
    }
}

function calculateItem(index) {
    const row = document.querySelector(`.item-row[data-index="${index}"]`);
    if (!row) return;
    
    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
    const taxRate = parseFloat(row.querySelector('.item-tax-rate').value) || 0;
    
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    let tax = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
        const taxRate = parseFloat(row.querySelector('.item-tax-rate').value) || 0;
        
        const itemSubtotal = quantity * unitPrice;
        const itemTax = itemSubtotal * (taxRate / 100);
        
        subtotal += itemSubtotal;
        tax += itemTax;
    });
    
    // Aplicar desconto
    const discountType = document.getElementById('discount_type').value;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    let discountAmount = 0;
    
    if (discountType === 'percentage') {
        discountAmount = subtotal * (discount / 100);
    } else if (discountType === 'fixed') {
        discountAmount = discount;
    }
    
    const total = subtotal - discountAmount + tax;
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('tax').value = tax.toFixed(2);
    document.getElementById('total').value = total.toFixed(2);
    
    document.getElementById('subtotalDisplay').textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('taxDisplay').textContent = 'R$ ' + tax.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('totalDisplay').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function updateDiscountDisplay() {
    const discountType = document.getElementById('discount_type').value;
    const container = document.getElementById('discountContainer');
    container.style.display = discountType ? 'block' : 'none';
    if (!discountType) {
        document.getElementById('discount').value = '0';
    }
    calculateTotal();
}

// Calcular total ao carregar
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
    updateDiscountDisplay();
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

