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
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$page_title = $id ? 'Editar Plano' : 'Novo Plano';
$active = 'plans';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'category_id' => $categoryId ?: null,
    'name' => '',
    'slug' => '',
    'description' => '',
    'short_description' => '',
    'price_monthly' => null,
    'price_quarterly' => null,
    'price_semiannual' => null,
    'price_annual' => null,
    'price_biennial' => null,
    'price_triennal' => null,
    'setup_fee' => '0.00',
    'currency' => 'BRL',
    'features' => null,
    'disk_space' => '',
    'bandwidth' => '',
    'domains' => null,
    'email_accounts' => null,
    'databases' => null,
    'is_featured' => 0,
    'is_popular' => 0,
    'sort_order' => 0,
    'is_enabled' => 1,
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM plans WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Plano não encontrado.');
        }
        $item = array_merge($item, $row);
        // Decodificar JSON de features
        if ($item['features']) {
            $item['features'] = is_string($item['features']) ? json_decode($item['features'], true) : $item['features'];
        }
        if (!is_array($item['features'])) {
            $item['features'] = [];
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar plano.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $categoryIdPost = (int)($_POST['category_id'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $shortDescription = trim((string)($_POST['short_description'] ?? ''));
    
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($categoryIdPost <= 0) $error = 'A categoria é obrigatória.';
    if ($slug === '') {
        // Gerar slug automaticamente se não fornecido
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    // Verificar se o slug já existe (exceto para o próprio item)
    if (!$error) {
        $stmt = db()->prepare("SELECT id FROM plans WHERE slug=? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $error = 'Este slug já está em uso.';
        }
    }

    // Processar features (JSON)
    $features = [];
    if (isset($_POST['features']) && is_array($_POST['features'])) {
        foreach ($_POST['features'] as $feature) {
            $feature = trim((string)$feature);
            if ($feature !== '') {
                $features[] = $feature;
            }
        }
    }

    // Processar domínios, emails e databases (NULL se vazio ou 0)
    $domains = isset($_POST['domains']) && $_POST['domains'] !== '' ? (int)$_POST['domains'] : null;
    $emailAccounts = isset($_POST['email_accounts']) && $_POST['email_accounts'] !== '' ? (int)$_POST['email_accounts'] : null;
    $databases = isset($_POST['databases']) && $_POST['databases'] !== '' ? (int)$_POST['databases'] : null;
    
    // Se marcar como featured, desmarcar os outros
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    if ($isFeatured) {
        if ($id > 0) {
            db()->prepare("UPDATE plans SET is_featured=0 WHERE is_featured=1 AND id != ?")->execute([$id]);
        } else {
            db()->prepare("UPDATE plans SET is_featured=0 WHERE is_featured=1")->execute();
        }
    }

    $data = [
        'category_id' => $categoryIdPost,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'short_description' => $shortDescription,
        'price_monthly' => isset($_POST['price_monthly']) && $_POST['price_monthly'] !== '' ? (float)$_POST['price_monthly'] : null,
        'price_quarterly' => isset($_POST['price_quarterly']) && $_POST['price_quarterly'] !== '' ? (float)$_POST['price_quarterly'] : null,
        'price_semiannual' => isset($_POST['price_semiannual']) && $_POST['price_semiannual'] !== '' ? (float)$_POST['price_semiannual'] : null,
        'price_annual' => isset($_POST['price_annual']) && $_POST['price_annual'] !== '' ? (float)$_POST['price_annual'] : null,
        'price_biennial' => isset($_POST['price_biennial']) && $_POST['price_biennial'] !== '' ? (float)$_POST['price_biennial'] : null,
        'price_triennal' => isset($_POST['price_triennal']) && $_POST['price_triennal'] !== '' ? (float)$_POST['price_triennal'] : null,
        'setup_fee' => isset($_POST['setup_fee']) ? (float)$_POST['setup_fee'] : 0.00,
        'currency' => trim((string)($_POST['currency'] ?? 'BRL')),
        'features' => !empty($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : null,
        'disk_space' => trim((string)($_POST['disk_space'] ?? '')),
        'bandwidth' => trim((string)($_POST['bandwidth'] ?? '')),
        'domains' => $domains,
        'email_accounts' => $emailAccounts,
        'databases' => $databases,
        'is_featured' => $isFeatured,
        'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE plans SET category_id=:category_id, name=:name, slug=:slug, description=:description, short_description=:short_description, price_monthly=:price_monthly, price_quarterly=:price_quarterly, price_semiannual=:price_semiannual, price_annual=:price_annual, price_biennial=:price_biennial, price_triennal=:price_triennal, setup_fee=:setup_fee, currency=:currency, features=:features, disk_space=:disk_space, bandwidth=:bandwidth, domains=:domains, email_accounts=:email_accounts, `databases`=:databases, is_featured=:is_featured, is_popular=:is_popular, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Plano atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO plans (category_id, name, slug, description, short_description, price_monthly, price_quarterly, price_semiannual, price_annual, price_biennial, price_triennal, setup_fee, currency, features, disk_space, bandwidth, domains, email_accounts, `databases`, is_featured, is_popular, sort_order, is_enabled) VALUES (:category_id, :name, :slug, :description, :short_description, :price_monthly, :price_quarterly, :price_semiannual, :price_annual, :price_biennial, :price_triennal, :setup_fee, :currency, :features, :disk_space, :bandwidth, :domains, :email_accounts, :databases, :is_featured, :is_popular, :sort_order, :is_enabled)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Plano criado com sucesso.';
            }
            header('Location: /admin/plans.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar plano: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}

// Buscar categorias para o select
$categories = db()->query("SELECT * FROM plan_categories ORDER BY sort_order ASC, name ASC")->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Plano' : 'Novo Plano' ?></h1>
        <a href="/admin/plans.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações Básicas</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$item['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= h($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome do Plano <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= h($item['slug']) ?>" placeholder="Será gerado automaticamente se deixado em branco">
                        </div>

                        <div class="mb-3">
                            <label for="short_description" class="form-label">Descrição Curta</label>
                            <input type="text" class="form-control" id="short_description" name="short_description" value="<?= h($item['short_description']) ?>" maxlength="500">
                            <small class="text-muted">Descrição breve que aparece em cards e listagens</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição Completa</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= h($item['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Preços</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price_monthly" class="form-label">Preço Mensal</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_monthly" name="price_monthly" value="<?= $item['price_monthly'] !== null ? number_format((float)$item['price_monthly'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price_quarterly" class="form-label">Preço Trimestral</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_quarterly" name="price_quarterly" value="<?= $item['price_quarterly'] !== null ? number_format((float)$item['price_quarterly'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price_semiannual" class="form-label">Preço Semestral</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_semiannual" name="price_semiannual" value="<?= $item['price_semiannual'] !== null ? number_format((float)$item['price_semiannual'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price_annual" class="form-label">Preço Anual</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_annual" name="price_annual" value="<?= $item['price_annual'] !== null ? number_format((float)$item['price_annual'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price_biennial" class="form-label">Preço Bienal</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_biennial" name="price_biennial" value="<?= $item['price_biennial'] !== null ? number_format((float)$item['price_biennial'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price_triennal" class="form-label">Preço Trienal</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_triennal" name="price_triennal" value="<?= $item['price_triennal'] !== null ? number_format((float)$item['price_triennal'], 2, '.', '') : '' ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="setup_fee" class="form-label">Taxa de Instalação</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="setup_fee" name="setup_fee" value="<?= number_format((float)$item['setup_fee'], 2, '.', '') ?>" step="0.01" min="0">
                                </div>
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
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Recursos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="disk_space" class="form-label">Espaço em Disco</label>
                                <input type="text" class="form-control" id="disk_space" name="disk_space" value="<?= h($item['disk_space']) ?>" placeholder="ex: 10GB, 50GB, Ilimitado">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bandwidth" class="form-label">Largura de Banda</label>
                                <input type="text" class="form-control" id="bandwidth" name="bandwidth" value="<?= h($item['bandwidth']) ?>" placeholder="ex: 100GB, 500GB, Ilimitado">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="domains" class="form-label">Domínios</label>
                                <input type="number" class="form-control" id="domains" name="domains" value="<?= $item['domains'] !== null ? (int)$item['domains'] : '' ?>" min="0" placeholder="Deixe vazio para ilimitado">
                                <small class="text-muted">Deixe vazio para ilimitado</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="email_accounts" class="form-label">Contas de Email</label>
                                <input type="number" class="form-control" id="email_accounts" name="email_accounts" value="<?= $item['email_accounts'] !== null ? (int)$item['email_accounts'] : '' ?>" min="0" placeholder="Deixe vazio para ilimitado">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="databases" class="form-label">Bancos de Dados</label>
                                <input type="number" class="form-control" id="databases" name="databases" value="<?= $item['databases'] !== null ? (int)$item['databases'] : '' ?>" min="0" placeholder="Deixe vazio para ilimitado">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Recursos Adicionais (Features)</label>
                            <div id="features-container">
                                <?php 
                                $featuresList = is_array($item['features']) ? $item['features'] : [];
                                if (empty($featuresList)) {
                                    $featuresList = [''];
                                }
                                foreach ($featuresList as $index => $feature): 
                                ?>
                                    <div class="input-group mb-2 feature-item">
                                        <input type="text" class="form-control" name="features[]" value="<?= h($feature) ?>" placeholder="Ex: SSL Grátis, Backup Diário, Suporte 24/7">
                                        <button type="button" class="btn btn-outline-danger remove-feature" <?= count($featuresList) === 1 ? 'disabled' : '' ?>>
                                            <i class="las la-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-feature">
                                <i class="las la-plus me-1"></i> Adicionar Recurso
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?= (int)$item['is_featured'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_featured">
                                Plano em Destaque
                            </label>
                            <small class="d-block text-muted">Apenas um plano pode estar em destaque</small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular" <?= (int)$item['is_popular'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_popular">
                                Plano Popular
                            </label>
                            <small class="d-block text-muted">Marcar como recomendado/popular</small>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?= (int)$item['is_enabled'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_enabled">
                                Ativo
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/plans.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
// Gerar slug automaticamente a partir do nome
document.getElementById('name').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
        const slug = this.value.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugInput.value = slug;
        slugInput.dataset.autoGenerated = 'true';
    }
});

document.getElementById('slug').addEventListener('input', function() {
    this.dataset.autoGenerated = 'false';
});

// Gerenciar features
document.getElementById('add-feature').addEventListener('click', function() {
    const container = document.getElementById('features-container');
    const newItem = document.createElement('div');
    newItem.className = 'input-group mb-2 feature-item';
    newItem.innerHTML = `
        <input type="text" class="form-control" name="features[]" placeholder="Ex: SSL Grátis, Backup Diário, Suporte 24/7">
        <button type="button" class="btn btn-outline-danger remove-feature">
            <i class="las la-times"></i>
        </button>
    `;
    container.appendChild(newItem);
    updateRemoveButtons();
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-feature')) {
        const item = e.target.closest('.feature-item');
        const container = document.getElementById('features-container');
        if (container.children.length > 1) {
            item.remove();
            updateRemoveButtons();
        }
    }
});

function updateRemoveButtons() {
    const items = document.querySelectorAll('.feature-item');
    items.forEach(item => {
        const btn = item.querySelector('.remove-feature');
        if (btn) {
            btn.disabled = items.length === 1;
        }
    });
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
