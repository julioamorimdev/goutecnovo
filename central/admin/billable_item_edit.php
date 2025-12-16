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

$page_title = $id ? 'Editar Item Faturável' : 'Novo Item Faturável';
$active = 'billable_items';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'code' => '',
    'name' => '',
    'description' => '',
    'category' => 'service',
    'unit' => 'unit',
    'price' => '0.00',
    'tax_rate' => '0.00',
    'currency' => 'BRL',
    'is_recurring' => 0,
    'billing_cycle' => '',
    'is_enabled' => 1,
    'sort_order' => 0,
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM billable_items WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Item não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar item.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $code = trim((string)($_POST['code'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'service'));
    $unit = trim((string)($_POST['unit'] ?? 'unit'));
    $price = (float)($_POST['price'] ?? 0);
    $taxRate = (float)($_POST['tax_rate'] ?? 0);
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
    $billingCycle = trim((string)($_POST['billing_cycle'] ?? ''));
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    
    if ($code === '') $error = 'O código é obrigatório.';
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($price < 0) $error = 'O preço não pode ser negativo.';
    
    if (!in_array($category, ['service', 'product', 'license', 'other'], true)) {
        $category = 'service';
    }
    
    // Verificar se o código já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM billable_items WHERE code=? AND id != ?");
            $stmt->execute([$code, $id]);
            if ($stmt->fetch()) {
                $error = 'Este código já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'code' => $code,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'category' => $category,
        'unit' => $unit,
        'price' => $price,
        'tax_rate' => $taxRate,
        'currency' => $currency,
        'is_recurring' => $isRecurring,
        'billing_cycle' => $billingCycle !== '' ? $billingCycle : null,
        'is_enabled' => $isEnabled,
        'sort_order' => $sortOrder,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE billable_items SET code=:code, name=:name, description=:description, category=:category, unit=:unit, price=:price, tax_rate=:tax_rate, currency=:currency, is_recurring=:is_recurring, billing_cycle=:billing_cycle, is_enabled=:is_enabled, sort_order=:sort_order WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Item atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO billable_items (code, name, description, category, unit, price, tax_rate, currency, is_recurring, billing_cycle, is_enabled, sort_order) VALUES (:code, :name, :description, :category, :unit, :price, :tax_rate, :currency, :is_recurring, :billing_cycle, :is_enabled, :sort_order)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Item criado com sucesso.';
            }
            header('Location: /admin/billable_items.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar item: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Item Faturável' : 'Novo Item Faturável' ?></h1>
        <a href="/admin/billable_items.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações do Item</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" value="<?= h($item['code']) ?>" required>
                                <small class="text-muted">Código único do item (ex: SVC-001)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="service" <?= $item['category'] === 'service' ? 'selected' : '' ?>>Serviço</option>
                                    <option value="product" <?= $item['category'] === 'product' ? 'selected' : '' ?>>Produto</option>
                                    <option value="license" <?= $item['category'] === 'license' ? 'selected' : '' ?>>Licença</option>
                                    <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="unit" class="form-label">Unidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="unit" <?= $item['unit'] === 'unit' ? 'selected' : '' ?>>Unidade</option>
                                    <option value="hour" <?= $item['unit'] === 'hour' ? 'selected' : '' ?>>Hora</option>
                                    <option value="day" <?= $item['unit'] === 'day' ? 'selected' : '' ?>>Dia</option>
                                    <option value="month" <?= $item['unit'] === 'month' ? 'selected' : '' ?>>Mês</option>
                                    <option value="year" <?= $item['unit'] === 'year' ? 'selected' : '' ?>>Ano</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Preço Unitário <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price" name="price" value="<?= h($item['price']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tax_rate" class="form-label">Taxa de Imposto (%)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="<?= h($item['tax_rate']) ?>" step="0.01" min="0" max="100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações de Cobrança</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1" <?= (int)$item['is_recurring'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_recurring">
                                    Item Recorrente
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="billing_cycle_container" style="display: <?= (int)$item['is_recurring'] === 1 ? 'block' : 'none' ?>;">
                            <label for="billing_cycle" class="form-label">Ciclo de Cobrança</label>
                            <select class="form-select" id="billing_cycle" name="billing_cycle">
                                <option value="">Selecione...</option>
                                <option value="monthly" <?= $item['billing_cycle'] === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                <option value="quarterly" <?= $item['billing_cycle'] === 'quarterly' ? 'selected' : '' ?>>Trimestral</option>
                                <option value="semiannual" <?= $item['billing_cycle'] === 'semiannual' ? 'selected' : '' ?>>Semestral</option>
                                <option value="annual" <?= $item['billing_cycle'] === 'annual' ? 'selected' : '' ?>>Anual</option>
                            </select>
                        </div>

                        <div class="mb-3">
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

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1" <?= (int)$item['is_enabled'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_enabled">
                                    Item Ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0">
                            <small class="text-muted">Menor número aparece primeiro</small>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="small text-muted">
                                    <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                    <?php if ($item['updated_at'] ?? ''): ?>
                                        <div><strong>Última atualização:</strong> <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></div>
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
            <a href="/admin/billable_items.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('is_recurring').addEventListener('change', function() {
    document.getElementById('billing_cycle_container').style.display = this.checked ? 'block' : 'none';
    if (!this.checked) {
        document.getElementById('billing_cycle').value = '';
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

