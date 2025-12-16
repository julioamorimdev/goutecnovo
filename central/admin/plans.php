<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'category' ou 'plan'
    
    if ($type === 'category' && $id > 0) {
        if ($action === 'toggle') {
            db()->prepare("UPDATE plan_categories SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
            header('Location: /admin/plans.php');
            exit;
        }
        if ($action === 'delete') {
            // Verificar se há planos nesta categoria
            $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM plans WHERE category_id=?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            if ((int)$result['cnt'] > 0) {
                $_SESSION['error'] = 'Não é possível excluir a categoria pois existem planos vinculados a ela.';
            } else {
                db()->prepare("DELETE FROM plan_categories WHERE id=?")->execute([$id]);
                $_SESSION['success'] = 'Categoria excluída com sucesso.';
            }
            header('Location: /admin/plans.php');
            exit;
        }
        if ($action === 'move_up' || $action === 'move_down') {
            $stmt = db()->prepare("SELECT id, sort_order FROM plan_categories WHERE id=?");
            $stmt->execute([$id]);
            $cur = $stmt->fetch();
            if ($cur) {
                $sort = (int)$cur['sort_order'];
                if ($action === 'move_up') {
                    $q = "SELECT id, sort_order FROM plan_categories
                          WHERE sort_order < :sort OR (sort_order = :sort AND id < :id)
                          ORDER BY sort_order DESC, id DESC LIMIT 1";
                } else {
                    $q = "SELECT id, sort_order FROM plan_categories
                          WHERE sort_order > :sort OR (sort_order = :sort AND id > :id)
                          ORDER BY sort_order ASC, id ASC LIMIT 1";
                }
                $stmt2 = db()->prepare($q);
                $stmt2->execute([':sort' => $sort, ':id' => $id]);
                $other = $stmt2->fetch();
                if ($other) {
                    db()->beginTransaction();
                    try {
                        db()->prepare("UPDATE plan_categories SET sort_order=? WHERE id=?")->execute([$sort, (int)$other['id']]);
                        db()->prepare("UPDATE plan_categories SET sort_order=? WHERE id=?")->execute([(int)$other['sort_order'], $id]);
                        db()->commit();
                    } catch (Throwable $e) {
                        db()->rollBack();
                    }
                }
            }
            header('Location: /admin/plans.php');
            exit;
        }
    }
    
    if ($type === 'plan' && $id > 0) {
        if ($action === 'toggle') {
            db()->prepare("UPDATE plans SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
            header('Location: /admin/plans.php');
            exit;
        }
        if ($action === 'delete') {
            db()->prepare("DELETE FROM plans WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'Plano excluído com sucesso.';
            header('Location: /admin/plans.php');
            exit;
        }
        if ($action === 'move_up' || $action === 'move_down') {
            $stmt = db()->prepare("SELECT id, category_id, sort_order FROM plans WHERE id=?");
            $stmt->execute([$id]);
            $cur = $stmt->fetch();
            if ($cur) {
                $categoryId = (int)$cur['category_id'];
                $sort = (int)$cur['sort_order'];
                if ($action === 'move_up') {
                    $q = "SELECT id, sort_order FROM plans
                          WHERE category_id=? AND (sort_order < ? OR (sort_order = ? AND id < ?))
                          ORDER BY sort_order DESC, id DESC LIMIT 1";
                } else {
                    $q = "SELECT id, sort_order FROM plans
                          WHERE category_id=? AND (sort_order > ? OR (sort_order = ? AND id > ?))
                          ORDER BY sort_order ASC, id ASC LIMIT 1";
                }
                $stmt2 = db()->prepare($q);
                $stmt2->execute([$categoryId, $sort, $sort, $id]);
                $other = $stmt2->fetch();
                if ($other) {
                    db()->beginTransaction();
                    try {
                        db()->prepare("UPDATE plans SET sort_order=? WHERE id=?")->execute([$sort, (int)$other['id']]);
                        db()->prepare("UPDATE plans SET sort_order=? WHERE id=?")->execute([(int)$other['sort_order'], $id]);
                        db()->commit();
                    } catch (Throwable $e) {
                        db()->rollBack();
                    }
                }
            }
            header('Location: /admin/plans.php');
            exit;
        }
    }
}

$page_title = 'Planos';
$active = 'plans';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar categorias e planos
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $categories = db()->query("SELECT * FROM plan_categories ORDER BY sort_order ASC, id ASC")->fetchAll();
    $plansByCategory = [];
    foreach ($categories as $cat) {
        $stmt = db()->prepare("SELECT p.*, pc.name as category_name FROM plans p 
                              LEFT JOIN plan_categories pc ON p.category_id = pc.id 
                              WHERE p.category_id = ? 
                              ORDER BY p.sort_order ASC, p.id ASC");
        $stmt->execute([(int)$cat['id']]);
        $plansByCategory[(int)$cat['id']] = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $categories = [];
    $plansByCategory = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Planos</h1>
        <div class="d-flex gap-2">
            <a href="/admin/plan_category_edit.php" class="btn btn-primary">
                <i class="las la-plus me-1"></i> Nova Categoria
            </a>
            <a href="/admin/plan_edit.php" class="btn btn-success">
                <i class="las la-plus me-1"></i> Novo Plano
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($categories as $cat): ?>
            <div class="col-12 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($cat['icon_class']): ?>
                                <i class="<?= h($cat['icon_class']) ?>"></i>
                            <?php endif; ?>
                            <h5 class="mb-0"><?= h($cat['name']) ?></h5>
                            <?php if ((int)$cat['is_enabled'] === 0): ?>
                                <span class="badge bg-danger text-white">Inativo</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="/admin/plan_category_edit.php?id=<?= (int)$cat['id'] ?>" class="btn btn-sm btn-light">
                                <i class="las la-edit"></i> Editar
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="type" value="category">
                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-<?= (int)$cat['is_enabled'] === 1 ? 'warning' : 'success' ?>">
                                    <i class="las la-<?= (int)$cat['is_enabled'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="category">
                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="las la-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($plansByCategory[(int)$cat['id']])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Imagem</th>
                                            <th>Nome</th>
                                            <th>Preço Mensal</th>
                                            <th>Preço Anual</th>
                                            <th>Recursos</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 200px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($plansByCategory[(int)$cat['id']] as $plan): ?>
                                            <tr>
                                                <td>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                        <i class="las la-server text-muted"></i>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= h($plan['name']) ?></strong>
                                                    <?php if ((int)$plan['is_featured'] === 1): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Destaque</span>
                                                    <?php endif; ?>
                                                    <?php if ((int)$plan['is_popular'] === 1): ?>
                                                        <span class="badge bg-info ms-1">Popular</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted"><?= h($plan['slug']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($plan['price_monthly']): ?>
                                                        <strong>R$ <?= number_format((float)$plan['price_monthly'], 2, ',', '.') ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($plan['price_annual']): ?>
                                                        <strong>R$ <?= number_format((float)$plan['price_annual'], 2, ',', '.') ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php if ($plan['disk_space']): ?>
                                                            <span class="badge bg-dark text-white"><?= h($plan['disk_space']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($plan['domains'] !== null): ?>
                                                            <span class="badge bg-dark text-white"><?= (int)$plan['domains'] === 0 ? 'Ilimitado' : (int)$plan['domains'] ?> domínios</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ((int)$plan['is_enabled'] === 1): ?>
                                                        <span class="badge bg-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger text-white">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="/admin/plan_edit.php?id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                            <i class="las la-edit"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="type" value="plan">
                                                            <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-<?= (int)$plan['is_enabled'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$plan['is_enabled'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                                <i class="las la-<?= (int)$plan['is_enabled'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="type" value="plan">
                                                            <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                                <i class="las la-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nenhum plano cadastrado nesta categoria.</p>
                            <a href="/admin/plan_edit.php?category_id=<?= (int)$cat['id'] ?>" class="btn btn-sm btn-success mt-2">
                                <i class="las la-plus me-1"></i> Adicionar Plano
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
