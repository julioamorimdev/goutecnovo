<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Grupos de Opções Configuráveis';
$active = 'configurable_options';
require_once __DIR__ . '/partials/layout_start.php';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->prepare("DELETE FROM configurable_option_groups WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = 'Grupo excluído com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir grupo.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar grupos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $stmt = db()->query("SELECT cog.*, 
                                COUNT(co.id) as option_count
                         FROM configurable_option_groups cog
                         LEFT JOIN configurable_options co ON cog.id = co.group_id
                         GROUP BY cog.id
                         ORDER BY cog.sort_order ASC, cog.name ASC");
    $groups = $stmt->fetchAll();
} catch (Throwable $e) {
    $groups = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Grupos de Opções Configuráveis</h1>
        <a href="/admin/configurable_option_group_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Grupo
        </a>
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

    <div class="alert alert-info">
        <i class="las la-info-circle me-2"></i>
        <strong>Opções Configuráveis</strong> permitem oferecer complementos e opções de personalização com seus produtos. 
        As opções são atribuídas aos grupos e os grupos podem então ser aplicados aos produtos.
    </div>

    <?php if (empty($groups)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="las la-list text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3 mb-0">Nenhum grupo cadastrado.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Opções</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td><strong><?= h($group['name']) ?></strong></td>
                                    <td><?= h($group['description'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= number_format((int)$group['option_count']) ?> opções</span>
                                    </td>
                                    <td>
                                        <?php if ($group['is_active']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/configurable_option_group_edit.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <a href="/admin/configurable_options.php?group_id=<?= $group['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="las la-cog"></i> Opções
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este grupo?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_group" value="1">
                                            <input type="hidden" name="id" value="<?= $group['id'] ?>">
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
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

