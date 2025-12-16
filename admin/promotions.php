<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Promoções e Cupons';
$active = 'promotions';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_promotion'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = 'Promoção excluída com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir promoção.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = 'Cupom excluído com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir cupom.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar promoções e cupons
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $stmt = db()->query("SELECT * FROM promotions ORDER BY created_at DESC");
    $promotions = $stmt->fetchAll();
    
    $stmt = db()->query("SELECT * FROM coupons ORDER BY created_at DESC");
    $coupons = $stmt->fetchAll();
} catch (Throwable $e) {
    $promotions = [];
    $coupons = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Promoções e Cupons</h1>
        <div>
            <a href="/admin/promotion_edit.php" class="btn btn-primary me-2">
                <i class="las la-plus me-1"></i> Nova Promoção
            </a>
            <a href="/admin/coupon_edit.php" class="btn btn-success">
                <i class="las la-plus me-1"></i> Novo Cupom
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Promoções -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="las la-tags me-2"></i> Promoções</h5>
        </div>
        <div class="card-body">
            <?php if (empty($promotions)): ?>
                <p class="text-muted text-center mb-0">Nenhuma promoção cadastrada.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Desconto</th>
                                <th>Período</th>
                                <th>Usos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): 
                                $isActive = (bool)$promo['is_active'];
                                $now = time();
                                $start = strtotime($promo['start_date']);
                                $end = $promo['end_date'] ? strtotime($promo['end_date']) : null;
                                $isValid = $isActive && $now >= $start && ($end === null || $now <= $end);
                            ?>
                                <tr>
                                    <td><strong><?= h($promo['name']) ?></strong></td>
                                    <td>
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <?= number_format((float)$promo['discount_value'], 0) ?>%
                                        <?php else: ?>
                                            R$ <?= number_format((float)$promo['discount_value'], 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($promo['start_date'])) ?>
                                        <?php if ($promo['end_date']): ?>
                                            até <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                                        <?php else: ?>
                                            (sem término)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= number_format((int)$promo['usage_count']) ?>
                                        <?php if ($promo['usage_limit']): ?>
                                            / <?= number_format((int)$promo['usage_limit']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isValid): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php elseif (!$isActive): ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Fora do Período</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/promotion_edit.php?id=<?= $promo['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="las la-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Excluir esta promoção?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_promotion" value="1">
                                            <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cupons -->
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="las la-ticket-alt me-2"></i> Cupons de Desconto</h5>
        </div>
        <div class="card-body">
            <?php if (empty($coupons)): ?>
                <p class="text-muted text-center mb-0">Nenhum cupom cadastrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Desconto</th>
                                <th>Período</th>
                                <th>Usos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): 
                                $isActive = (bool)$coupon['is_active'];
                                $now = time();
                                $start = strtotime($coupon['start_date']);
                                $end = $coupon['end_date'] ? strtotime($coupon['end_date']) : null;
                                $isValid = $isActive && $now >= $start && ($end === null || $now <= $end);
                            ?>
                                <tr>
                                    <td><strong class="text-success"><?= h(strtoupper($coupon['code'])) ?></strong></td>
                                    <td><?= h($coupon['name']) ?></td>
                                    <td>
                                        <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                            <?= number_format((float)$coupon['discount_value'], 0) ?>%
                                        <?php else: ?>
                                            R$ <?= number_format((float)$coupon['discount_value'], 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($coupon['start_date'])) ?>
                                        <?php if ($coupon['end_date']): ?>
                                            até <?= date('d/m/Y', strtotime($coupon['end_date'])) ?>
                                        <?php else: ?>
                                            (sem término)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= number_format((int)$coupon['usage_count']) ?>
                                        <?php if ($coupon['usage_limit']): ?>
                                            / <?= number_format((int)$coupon['usage_limit']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isValid): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php elseif (!$isActive): ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Fora do Período</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/coupon_edit.php?id=<?= $coupon['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="las la-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este cupom?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_coupon" value="1">
                                            <input type="hidden" name="id" value="<?= $coupon['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

