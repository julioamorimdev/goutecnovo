<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Criar/Editar Grupo de Opções Configuráveis';
$active = 'configurable_options';
require_once __DIR__ . '/partials/layout_start.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$group = null;

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM configurable_option_groups WHERE id = ?");
        $stmt->execute([$id]);
        $group = $stmt->fetch();
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao buscar grupo.';
        header('Location: /admin/configurable_option_groups.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $_SESSION['error'] = 'Nome é obrigatório.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE configurable_option_groups SET name=?, description=?, sort_order=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $description ?: null, $sortOrder, $isActive, $id]);
                $_SESSION['success'] = 'Grupo atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO configurable_option_groups (name, description, sort_order, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $sortOrder, $isActive]);
                $_SESSION['success'] = 'Grupo criado com sucesso.';
            }
            header('Location: /admin/configurable_option_groups.php');
            exit;
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao salvar grupo: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar' : 'Criar' ?> Grupo de Opções Configuráveis</h1>
        <a href="/admin/configurable_option_groups.php" class="btn btn-secondary">
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
                            <label for="name" class="form-label">Nome do Grupo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= h($group['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($group['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sort_order" class="form-label">Ordem de Exibição</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?= h($group['sort_order'] ?? '0') ?>">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= ($group['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Grupo Ativo
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="las la-save me-1"></i> Salvar Grupo
                        </button>
                        
                        <?php if ($id > 0): ?>
                            <a href="/admin/configurable_options.php?group_id=<?= $id ?>" class="btn btn-info">
                                <i class="las la-cog me-1"></i> Gerenciar Opções
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

