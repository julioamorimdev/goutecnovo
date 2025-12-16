<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Criar/Editar Pacote de Produtos';
$active = 'product_packages';
require_once __DIR__ . '/partials/layout_start.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$package = null;
$packageItems = [];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM product_packages WHERE id = ?");
        $stmt->execute([$id]);
        $package = $stmt->fetch();
        
        if ($package) {
            $stmt = db()->prepare("SELECT ppi.*, p.name as plan_name, p.price as plan_price 
                                  FROM product_package_items ppi
                                  INNER JOIN plans p ON ppi.plan_id = p.id
                                  WHERE ppi.package_id = ?
                                  ORDER BY ppi.sort_order ASC");
            $stmt->execute([$id]);
            $packageItems = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao buscar pacote.';
        header('Location: /admin/product_packages.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $totalPrice = (float)($_POST['total_price'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $itemIds = isset($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
    $itemQuantities = isset($_POST['item_quantities']) ? array_map('intval', $_POST['item_quantities']) : [];
    
    // Gerar slug se não fornecido
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
    }
    
    if (empty($name) || empty($slug)) {
        $_SESSION['error'] = 'Nome é obrigatório.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->beginTransaction();
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE product_packages SET name=?, description=?, slug=?, discount_type=?, discount_value=?, total_price=?, is_active=?, sort_order=? WHERE id=?");
                $stmt->execute([$name, $description ?: null, $slug, $discountType, $discountValue, $totalPrice, $isActive, $sortOrder, $id]);
            } else {
                $stmt = db()->prepare("INSERT INTO product_packages (name, description, slug, discount_type, discount_value, total_price, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $slug, $discountType, $discountValue, $totalPrice, $isActive, $sortOrder]);
                $id = (int)db()->lastInsertId();
            }
            
            // Remover itens antigos
            db()->prepare("DELETE FROM product_package_items WHERE package_id = ?")->execute([$id]);
            
            // Adicionar novos itens
            foreach ($itemIds as $index => $planId) {
                if ($planId > 0) {
                    $quantity = isset($itemQuantities[$index]) ? (int)$itemQuantities[$index] : 1;
                    $stmt = db()->prepare("INSERT INTO product_package_items (package_id, plan_id, quantity, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $planId, $quantity, $index]);
                }
            }
            
            db()->commit();
            $_SESSION['success'] = $id > 0 ? 'Pacote atualizado com sucesso.' : 'Pacote criado com sucesso.';
            header('Location: /admin/product_packages.php');
            exit;
        } catch (Throwable $e) {
            db()->rollBack();
            $_SESSION['error'] = 'Erro ao salvar pacote: ' . $e->getMessage();
        }
    }
}

// Buscar planos disponíveis
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT id, name, price FROM plans ORDER BY name");
    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $plans = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar' : 'Criar' ?> Pacote de Produtos</h1>
        <a href="/admin/product_packages.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="POST" id="packageForm">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome do Pacote <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= h($package['name'] ?? '') ?>" required 
                                   onchange="generateSlug()">
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug (URL) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?= h($package['slug'] ?? '') ?>" required 
                                   pattern="[a-z0-9-]+" title="Apenas letras minúsculas, números e hífen">
                            <small class="text-muted">Usado na URL do pacote (ex: pacote-premium).</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($package['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="discount_type" class="form-label">Tipo de Desconto</label>
                                <select class="form-select" id="discount_type" name="discount_type" onchange="calculateTotal()">
                                    <option value="percentage" <?= ($package['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Porcentagem (%)</option>
                                    <option value="fixed" <?= ($package['discount_type'] ?? 'percentage') === 'fixed' ? 'selected' : '' ?>>Valor Fixo (R$)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="discount_value" class="form-label">Valor do Desconto</label>
                                <input type="number" step="0.01" class="form-control" id="discount_value" name="discount_value" 
                                       value="<?= h($package['discount_value'] ?? '0') ?>" onchange="calculateTotal()">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="total_price" class="form-label">Preço Total do Pacote <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="total_price" name="total_price" 
                                   value="<?= h($package['total_price'] ?? '0') ?>" required>
                            <small class="text-muted">Preço final após aplicar o desconto.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sort_order" class="form-label">Ordem de Exibição</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?= h($package['sort_order'] ?? '0') ?>">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= ($package['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Pacote Ativo
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3">Itens do Pacote</h5>
                        <div id="packageItems">
                            <?php if (!empty($packageItems)): ?>
                                <?php foreach ($packageItems as $index => $item): ?>
                                    <div class="row mb-3 item-row">
                                        <div class="col-md-6">
                                            <select class="form-select item-plan" name="item_ids[]" required onchange="calculateTotal()">
                                                <option value="">Selecione um plano...</option>
                                                <?php foreach ($plans as $plan): ?>
                                                    <option value="<?= $plan['id'] ?>" 
                                                            data-price="<?= $plan['price'] ?>"
                                                            <?= $item['plan_id'] == $plan['id'] ? 'selected' : '' ?>>
                                                        <?= h($plan['name']) ?> - R$ <?= number_format((float)$plan['price'], 2, ',', '.') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control item-quantity" name="item_quantities[]" 
                                                   value="<?= $item['quantity'] ?>" min="1" required onchange="calculateTotal()">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="row mb-3 item-row">
                                    <div class="col-md-6">
                                        <select class="form-select item-plan" name="item_ids[]" required onchange="calculateTotal()">
                                            <option value="">Selecione um plano...</option>
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>">
                                                    <?= h($plan['name']) ?> - R$ <?= number_format((float)$plan['price'], 2, ',', '.') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" class="form-control item-quantity" name="item_quantities[]" 
                                               value="1" min="1" required onchange="calculateTotal()">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-outline-primary mb-3" onclick="addItem()">
                            <i class="las la-plus me-1"></i> Adicionar Item
                        </button>
                        
                        <div class="alert alert-info">
                            <strong>Total dos Itens:</strong> R$ <span id="itemsTotal">0,00</span>
                            <br><strong>Desconto:</strong> <span id="discountDisplay">0,00</span>
                            <br><strong>Preço Final:</strong> R$ <span id="finalPrice">0,00</span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="las la-save me-1"></i> Salvar Pacote
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="las la-info-circle me-2"></i> Informações</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        <strong>Pacotes de Produtos</strong> permitem criar ofertas especiais combinando 2 ou mais produtos.
                    </p>
                    <ul class="small">
                        <li>O desconto é aplicado quando todos os itens são comprados juntos</li>
                        <li>Você pode gerar um link que adiciona automaticamente todos os itens ao carrinho</li>
                        <li>O slug será usado na URL: /package/seu-slug</li>
                        <li>O preço total deve refletir o valor após o desconto</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateSlug() {
    const name = document.getElementById('name').value;
    const slug = name.toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
}

function addItem() {
    const itemsDiv = document.getElementById('packageItems');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-3 item-row';
    newRow.innerHTML = `
        <div class="col-md-6">
            <select class="form-select item-plan" name="item_ids[]" required onchange="calculateTotal()">
                <option value="">Selecione um plano...</option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>">
                        <?= h($plan['name']) ?> - R$ <?= number_format((float)$plan['price'], 2, ',', '.') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="number" class="form-control item-quantity" name="item_quantities[]" 
                   value="1" min="1" required onchange="calculateTotal()">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                <i class="las la-trash"></i>
            </button>
        </div>
    `;
    itemsDiv.appendChild(newRow);
}

function removeItem(btn) {
    if (document.querySelectorAll('.item-row').length > 1) {
        btn.closest('.item-row').remove();
        calculateTotal();
    } else {
        alert('O pacote deve ter pelo menos um item.');
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const planSelect = row.querySelector('.item-plan');
        const quantityInput = row.querySelector('.item-quantity');
        if (planSelect && planSelect.value) {
            const price = parseFloat(planSelect.options[planSelect.selectedIndex].dataset.price || 0);
            const quantity = parseInt(quantityInput.value || 1);
            total += price * quantity;
        }
    });
    
    document.getElementById('itemsTotal').textContent = total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const discountType = document.getElementById('discount_type').value;
    const discountValue = parseFloat(document.getElementById('discount_value').value || 0);
    let finalPrice = total;
    
    if (discountType === 'percentage') {
        const discount = total * (discountValue / 100);
        finalPrice = total - discount;
        document.getElementById('discountDisplay').textContent = discountValue + '% (R$ ' + discount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ')';
    } else {
        finalPrice = total - discountValue;
        document.getElementById('discountDisplay').textContent = 'R$ ' + discountValue.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    if (finalPrice < 0) finalPrice = 0;
    document.getElementById('finalPrice').textContent = finalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('total_price').value = finalPrice.toFixed(2);
}

// Calcular ao carregar
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

