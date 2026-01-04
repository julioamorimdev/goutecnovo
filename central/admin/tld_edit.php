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

$page_title = $id ? 'Editar TLD' : 'Novo TLD';
$active = 'tlds';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'tld' => '',
    'name' => '',
    'description' => '',
    'price_register' => '0.00',
    'price_renew' => '0.00',
    'price_transfer' => '0.00',
    'min_years' => 1,
    'max_years' => 10,
    'epp_code_required' => 0,
    'privacy_protection_available' => 0,
    'privacy_protection_price' => '0.00',
    'is_enabled' => 1,
    'is_featured' => 0,
    'sort_order' => 0,
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM tlds WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('TLD não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar TLD.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $tld = trim((string)($_POST['tld'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $priceRegister = (float)($_POST['price_register'] ?? 0);
    $priceRenew = (float)($_POST['price_renew'] ?? 0);
    $priceTransfer = (float)($_POST['price_transfer'] ?? 0);
    $minYears = (int)($_POST['min_years'] ?? 1);
    $maxYears = (int)($_POST['max_years'] ?? 10);
    $eppCodeRequired = isset($_POST['epp_code_required']) ? 1 : 0;
    $privacyProtectionAvailable = isset($_POST['privacy_protection_available']) ? 1 : 0;
    $privacyProtectionPrice = (float)($_POST['privacy_protection_price'] ?? 0);
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    
    if ($tld === '') $error = 'O TLD é obrigatório.';
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($minYears < 1) $error = 'Os anos mínimos devem ser pelo menos 1.';
    if ($maxYears < $minYears) $error = 'Os anos máximos devem ser maiores ou iguais aos mínimos.';
    
    // Verificar se o TLD já existe (exceto para o próprio TLD)
    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            $stmt = db()->prepare("SELECT id FROM tlds WHERE tld=? AND id != ?");
            $stmt->execute([$tld, $id]);
            if ($stmt->fetch()) {
                $error = 'Este TLD já está cadastrado.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'tld' => $tld,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'price_register' => $priceRegister,
        'price_renew' => $priceRenew,
        'price_transfer' => $priceTransfer,
        'min_years' => $minYears,
        'max_years' => $maxYears,
        'epp_code_required' => $eppCodeRequired,
        'privacy_protection_available' => $privacyProtectionAvailable,
        'privacy_protection_price' => $privacyProtectionPrice,
        'is_enabled' => $isEnabled,
        'is_featured' => $isFeatured,
        'sort_order' => $sortOrder,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE tlds SET tld=:tld, name=:name, description=:description, price_register=:price_register, price_renew=:price_renew, price_transfer=:price_transfer, min_years=:min_years, max_years=:max_years, epp_code_required=:epp_code_required, privacy_protection_available=:privacy_protection_available, privacy_protection_price=:privacy_protection_price, is_enabled=:is_enabled, is_featured=:is_featured, sort_order=:sort_order WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'TLD atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO tlds (tld, name, description, price_register, price_renew, price_transfer, min_years, max_years, epp_code_required, privacy_protection_available, privacy_protection_price, is_enabled, is_featured, sort_order) VALUES (:tld, :name, :description, :price_register, :price_renew, :price_transfer, :min_years, :max_years, :epp_code_required, :privacy_protection_available, :privacy_protection_price, :is_enabled, :is_featured, :sort_order)");
                $stmt->execute($data);
                $_SESSION['success'] = 'TLD criado com sucesso.';
            }
            
            header('Location: /admin/tlds.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar TLD: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar TLD' : 'Novo TLD' ?></h1>
        <a href="/admin/tlds.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações do TLD</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tld" class="form-label">TLD <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tld" name="tld" value="<?= h($item['tld']) ?>" placeholder=".com" required>
                                <small class="text-muted">Ex: .com, .com.br, .net</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required>
                                <small class="text-muted">Nome descritivo do TLD</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="price_register" class="form-label">Preço de Registro (1 ano) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_register" name="price_register" value="<?= h($item['price_register']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="price_renew" class="form-label">Preço de Renovação (1 ano) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_renew" name="price_renew" value="<?= h($item['price_renew']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="price_transfer" class="form-label">Preço de Transferência <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price_transfer" name="price_transfer" value="<?= h($item['price_transfer']) ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="min_years" class="form-label">Anos Mínimos <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="min_years" name="min_years" value="<?= (int)$item['min_years'] ?>" min="1" max="10" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_years" class="form-label">Anos Máximos <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="max_years" name="max_years" value="<?= (int)$item['max_years'] ?>" min="1" max="10" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Recursos Adicionais</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="epp_code_required" name="epp_code_required" value="1" <?= (int)$item['epp_code_required'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="epp_code_required">
                                    Requer código EPP para transferência
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="privacy_protection_available" name="privacy_protection_available" value="1" <?= (int)$item['privacy_protection_available'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="privacy_protection_available">
                                    Proteção de privacidade disponível
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="privacy_price_container" style="display: <?= (int)$item['privacy_protection_available'] === 1 ? 'block' : 'none' ?>;">
                            <label for="privacy_protection_price" class="form-label">Preço da Proteção de Privacidade</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="privacy_protection_price" name="privacy_protection_price" value="<?= h($item['privacy_protection_price']) ?>" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="is_enabled" class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1" <?= (int)$item['is_enabled'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_enabled">
                                    TLD Ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="is_featured" class="form-label">Destaque</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= (int)($item['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_featured">
                                    Exibir em destaque no site
                                </label>
                            </div>
                            <small class="text-muted">TLDs em destaque aparecem nas páginas de registro e transferência de domínio</small>
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
            <a href="/admin/tlds.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('privacy_protection_available').addEventListener('change', function() {
    document.getElementById('privacy_price_container').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

