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

$page_title = $id ? 'Editar Addon' : 'Novo Addon';
$active = 'addons';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'short_description' => '',
    'category' => 'other',
    'icon_class' => '',
    'price_type' => 'one_time',
    'price' => '0.00',
    'setup_fee' => '0.00',
    'currency' => 'BRL',
    'billing_cycle' => '',
    'features' => '[]',
    'compatible_plans' => null,
    'requires_order' => 0,
    'auto_setup' => 0,
    'is_featured' => 0,
    'sort_order' => 0,
    'is_enabled' => 1,
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM addons WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Addon não encontrado.');
        }
        $item = array_merge($item, $row);
        // Converter JSON para string
        if (is_string($item['features'])) {
            $featuresArray = json_decode($item['features'], true) ?: [];
        } else {
            $featuresArray = $item['features'] ?: [];
        }
        $item['features'] = is_array($featuresArray) ? implode("\n", $featuresArray) : '';
        
        if (is_string($item['compatible_plans'])) {
            $plansArray = json_decode($item['compatible_plans'], true) ?: [];
        } else {
            $plansArray = $item['compatible_plans'] ?: [];
        }
        $item['compatible_plans'] = is_array($plansArray) ? $plansArray : [];
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar addon.');
    }
}

// Buscar planos para compatibilidade
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $plans = db()->query("SELECT id, name, category_id FROM plans WHERE is_enabled=1 ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $plans = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $shortDescription = trim((string)($_POST['short_description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'other'));
    $iconClass = trim((string)($_POST['icon_class'] ?? ''));
    $priceType = trim((string)($_POST['price_type'] ?? 'one_time'));
    $price = (float)($_POST['price'] ?? 0);
    $setupFee = (float)($_POST['setup_fee'] ?? 0);
    $currency = trim((string)($_POST['currency'] ?? 'BRL'));
    $billingCycle = trim((string)($_POST['billing_cycle'] ?? ''));
    $features = trim((string)($_POST['features'] ?? ''));
    $compatiblePlans = $_POST['compatible_plans'] ?? [];
    $requiresOrder = isset($_POST['requires_order']) ? 1 : 0;
    $autoSetup = isset($_POST['auto_setup']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($slug === '') {
        // Gerar slug automaticamente
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }
    if ($price < 0) $error = 'O preço não pode ser negativo.';
    
    if (!in_array($category, ['ssl', 'backup', 'security', 'email', 'domain', 'other'], true)) {
        $category = 'other';
    }
    
    if (!in_array($priceType, ['one_time', 'monthly', 'annual'], true)) {
        $priceType = 'one_time';
    }
    
    // Verificar se o slug já existe (exceto para o próprio addon)
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM addons WHERE slug=? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $error = 'Este slug já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }
    
    // Processar features
    $featuresArray = [];
    if ($features !== '') {
        $featureLines = explode("\n", $features);
        foreach ($featureLines as $feature) {
            $feature = trim($feature);
            if ($feature !== '') {
                $featuresArray[] = $feature;
            }
        }
    }
    
    // Processar planos compatíveis
    $compatiblePlansArray = [];
    if (is_array($compatiblePlans)) {
        foreach ($compatiblePlans as $planId) {
            $planId = (int)$planId;
            if ($planId > 0) {
                $compatiblePlansArray[] = $planId;
            }
        }
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description !== '' ? $description : null,
        'short_description' => $shortDescription !== '' ? $shortDescription : null,
        'category' => $category,
        'icon_class' => $iconClass !== '' ? $iconClass : null,
        'price_type' => $priceType,
        'price' => $price,
        'setup_fee' => $setupFee,
        'currency' => $currency,
        'billing_cycle' => $billingCycle !== '' ? $billingCycle : null,
        'features' => json_encode($featuresArray),
        'compatible_plans' => !empty($compatiblePlansArray) ? json_encode($compatiblePlansArray) : null,
        'requires_order' => $requiresOrder,
        'auto_setup' => $autoSetup,
        'is_featured' => $isFeatured,
        'is_enabled' => $isEnabled,
        'sort_order' => $sortOrder,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE addons SET name=:name, slug=:slug, description=:description, short_description=:short_description, category=:category, icon_class=:icon_class, price_type=:price_type, price=:price, setup_fee=:setup_fee, currency=:currency, billing_cycle=:billing_cycle, features=:features, compatible_plans=:compatible_plans, requires_order=:requires_order, auto_setup=:auto_setup, is_featured=:is_featured, is_enabled=:is_enabled, sort_order=:sort_order WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Addon atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO addons (name, slug, description, short_description, category, icon_class, price_type, price, setup_fee, currency, billing_cycle, features, compatible_plans, requires_order, auto_setup, is_featured, is_enabled, sort_order) VALUES (:name, :slug, :description, :short_description, :category, :icon_class, :price_type, :price, :setup_fee, :currency, :billing_cycle, :features, :compatible_plans, :requires_order, :auto_setup, :is_featured, :is_enabled, :sort_order)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Addon criado com sucesso.';
            }
            
            header('Location: /admin/addons.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar addon: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    $item['features'] = $features;
    $item['compatible_plans'] = $compatiblePlansArray;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Addon' : 'Novo Addon' ?></h1>
        <a href="/admin/addons.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações do Addon</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= h($item['slug']) ?>" placeholder="sera-gerado-automaticamente">
                            <small class="text-muted">Deixe em branco para gerar automaticamente a partir do nome</small>
                        </div>

                        <div class="mb-3">
                            <label for="short_description" class="form-label">Descrição Curta</label>
                            <input type="text" class="form-control" id="short_description" name="short_description" value="<?= h($item['short_description']) ?>" maxlength="500">
                            <small class="text-muted">Descrição breve (máximo 500 caracteres)</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição Completa</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="ssl" <?= $item['category'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="backup" <?= $item['category'] === 'backup' ? 'selected' : '' ?>>Backup</option>
                                    <option value="security" <?= $item['category'] === 'security' ? 'selected' : '' ?>>Segurança</option>
                                    <option value="email" <?= $item['category'] === 'email' ? 'selected' : '' ?>>Email</option>
                                    <option value="domain" <?= $item['category'] === 'domain' ? 'selected' : '' ?>>Domínio</option>
                                    <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="icon_class" class="form-label">Classe do Ícone</label>
                                <input type="text" class="form-control" id="icon_class" name="icon_class" value="<?= h($item['icon_class']) ?>" placeholder="las la-shield-alt">
                                <small class="text-muted">Ex: las la-shield-alt, las la-database</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="features" class="form-label">Recursos</label>
                            <textarea class="form-control" id="features" name="features" rows="4" placeholder="Um recurso por linha"><?= h($item['features']) ?></textarea>
                            <small class="text-muted">Liste os recursos do addon, um por linha</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Preços e Cobrança</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price_type" class="form-label">Tipo de Preço <span class="text-danger">*</span></label>
                                <select class="form-select" id="price_type" name="price_type" required>
                                    <option value="one_time" <?= $item['price_type'] === 'one_time' ? 'selected' : '' ?>>Pagamento Único</option>
                                    <option value="monthly" <?= $item['price_type'] === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    <option value="annual" <?= $item['price_type'] === 'annual' ? 'selected' : '' ?>>Anual</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="billing_cycle" class="form-label">Ciclo de Cobrança</label>
                                <select class="form-select" id="billing_cycle" name="billing_cycle">
                                    <option value="">N/A</option>
                                    <option value="monthly" <?= $item['billing_cycle'] === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    <option value="quarterly" <?= $item['billing_cycle'] === 'quarterly' ? 'selected' : '' ?>>Trimestral</option>
                                    <option value="semiannual" <?= $item['billing_cycle'] === 'semiannual' ? 'selected' : '' ?>>Semestral</option>
                                    <option value="annual" <?= $item['billing_cycle'] === 'annual' ? 'selected' : '' ?>>Anual</option>
                                </select>
                                <small class="text-muted">Aplicável apenas para preços recorrentes</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Preço <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price" name="price" value="<?= h($item['price']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="setup_fee" class="form-label">Taxa de Instalação</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="setup_fee" name="setup_fee" value="<?= h($item['setup_fee']) ?>" step="0.01" min="0">
                                </div>
                            </div>
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

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Compatibilidade</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Planos Compatíveis</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_plans" <?= empty($item['compatible_plans']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="all_plans">
                                    Compatível com todos os planos
                                </label>
                            </div>
                            <hr>
                            <div id="plans_list" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($plans as $plan): ?>
                                    <div class="form-check">
                                        <input class="form-check-input compatible-plan" type="checkbox" name="compatible_plans[]" value="<?= (int)$plan['id'] ?>" id="plan_<?= (int)$plan['id'] ?>" <?= in_array((int)$plan['id'], $item['compatible_plans']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="plan_<?= (int)$plan['id'] ?>">
                                            <?= h($plan['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Selecione os planos compatíveis com este addon</small>
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
                                    Addon Ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= (int)$item['is_featured'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_featured">
                                    Em Destaque
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="requires_order" name="requires_order" value="1" <?= (int)$item['requires_order'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="requires_order">
                                    Requer Pedido Ativo
                                </label>
                                <small class="text-muted d-block">O cliente precisa ter um pedido ativo para comprar este addon</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_setup" name="auto_setup" value="1" <?= (int)$item['auto_setup'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_setup">
                                    Configuração Automática
                                </label>
                                <small class="text-muted d-block">O addon será configurado automaticamente após a compra</small>
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
            <a href="/admin/addons.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('all_plans').addEventListener('change', function() {
    const planCheckboxes = document.querySelectorAll('.compatible-plan');
    planCheckboxes.forEach(cb => {
        cb.checked = !this.checked;
        cb.disabled = this.checked;
    });
});

document.querySelectorAll('.compatible-plan').forEach(cb => {
    cb.addEventListener('change', function() {
        const allChecked = Array.from(document.querySelectorAll('.compatible-plan')).every(c => c.checked || c.disabled);
        const noneChecked = Array.from(document.querySelectorAll('.compatible-plan')).every(c => !c.checked || c.disabled);
        if (allChecked || noneChecked) {
            document.getElementById('all_plans').checked = true;
            document.querySelectorAll('.compatible-plan').forEach(c => c.disabled = true);
        } else {
            document.getElementById('all_plans').checked = false;
            document.querySelectorAll('.compatible-plan').forEach(c => c.disabled = false);
        }
    });
});

// Gerar slug automaticamente
document.getElementById('name').addEventListener('blur', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value || slugInput.value === '') {
        const slug = this.value.toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugInput.value = slug;
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

