<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Criar/Editar Promoção';
$active = 'promotions';
require_once __DIR__ . '/partials/layout_start.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$promotion = null;

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        $promotion = $stmt->fetch();
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao buscar promoção.';
        header('Location: /admin/promotions.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $minPurchase = !empty($_POST['min_purchase']) ? (float)$_POST['min_purchase'] : null;
    $applicableTo = $_POST['applicable_to'] ?? 'all';
    $applicableIds = isset($_POST['applicable_ids']) && is_array($_POST['applicable_ids']) 
        ? array_map('intval', $_POST['applicable_ids']) 
        : [];
    $startDate = $_POST['start_date'] ?? '';
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $usageLimit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    
    if (empty($name) || empty($startDate)) {
        $_SESSION['error'] = 'Nome e data de início são obrigatórios.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $applicableIdsJson = !empty($applicableIds) ? json_encode($applicableIds) : null;
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE promotions SET name=?, description=?, discount_type=?, discount_value=?, min_purchase=?, applicable_to=?, applicable_ids=?, start_date=?, end_date=?, is_active=?, usage_limit=? WHERE id=?");
                $stmt->execute([$name, $description ?: null, $discountType, $discountValue, $minPurchase, $applicableTo, $applicableIdsJson, $startDate, $endDate, $isActive, $usageLimit, $id]);
                $_SESSION['success'] = 'Promoção atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO promotions (name, description, discount_type, discount_value, min_purchase, applicable_to, applicable_ids, start_date, end_date, is_active, usage_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $discountType, $discountValue, $minPurchase, $applicableTo, $applicableIdsJson, $startDate, $endDate, $isActive, $usageLimit]);
                $_SESSION['success'] = 'Promoção criada com sucesso.';
            }
            header('Location: /admin/promotions.php');
            exit;
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao salvar promoção: ' . $e->getMessage();
        }
    }
}

// Buscar planos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT id, name FROM plans ORDER BY name");
    $plans = $stmt->fetchAll();
    $selectedIds = $promotion && $promotion['applicable_ids'] ? json_decode($promotion['applicable_ids'], true) : [];
} catch (Throwable $e) {
    $plans = [];
    $selectedIds = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar' : 'Criar' ?> Promoção</h1>
        <a href="/admin/promotions.php" class="btn btn-secondary">
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
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= h($promotion['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($promotion['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="discount_type" class="form-label">Tipo de Desconto</label>
                                <select class="form-select" id="discount_type" name="discount_type">
                                    <option value="percentage" <?= ($promotion['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Porcentagem (%)</option>
                                    <option value="fixed" <?= ($promotion['discount_type'] ?? 'percentage') === 'fixed' ? 'selected' : '' ?>>Valor Fixo (R$)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="discount_value" class="form-label">Valor do Desconto <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="discount_value" name="discount_value" 
                                       value="<?= h($promotion['discount_value'] ?? '0') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="min_purchase" class="form-label">Valor Mínimo de Compra (opcional)</label>
                            <input type="number" step="0.01" class="form-control" id="min_purchase" name="min_purchase" 
                                   value="<?= h($promotion['min_purchase'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="applicable_to" class="form-label">Aplicável a</label>
                            <select class="form-select" id="applicable_to" name="applicable_to" onchange="toggleApplicablePlans()">
                                <option value="all" <?= ($promotion['applicable_to'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos os Produtos</option>
                                <option value="specific_plans" <?= ($promotion['applicable_to'] ?? 'all') === 'specific_plans' ? 'selected' : '' ?>>Planos Específicos</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="applicablePlansSection" style="display: <?= ($promotion['applicable_to'] ?? 'all') === 'specific_plans' ? 'block' : 'none' ?>;">
                            <label class="form-label">Selecione os Planos</label>
                            <select class="form-select" id="applicable_ids" name="applicable_ids[]" multiple size="5">
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" <?= in_array($plan['id'], $selectedIds) ? 'selected' : '' ?>>
                                        <?= h($plan['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl (ou Cmd no Mac) para selecionar múltiplos planos.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Data de Início <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                       value="<?= $promotion && $promotion['start_date'] ? date('Y-m-d\TH:i', strtotime($promotion['start_date'])) : '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">Data de Término (opcional)</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                       value="<?= $promotion && $promotion['end_date'] ? date('Y-m-d\TH:i', strtotime($promotion['end_date'])) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="usage_limit" class="form-label">Limite de Usos (opcional)</label>
                                <input type="number" class="form-control" id="usage_limit" name="usage_limit" 
                                       value="<?= h($promotion['usage_limit'] ?? '') ?>" placeholder="Deixe vazio para ilimitado">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= ($promotion['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Promoção Ativa
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="las la-save me-1"></i> Salvar Promoção
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleApplicablePlans() {
    const applicableTo = document.getElementById('applicable_to').value;
    const plansSection = document.getElementById('applicablePlansSection');
    plansSection.style.display = applicableTo === 'specific_plans' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

