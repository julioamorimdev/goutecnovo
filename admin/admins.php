<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$page_title = 'Administradores';
$active = 'admins';
require_once __DIR__ . '/partials/layout_start.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'toggle') {
        // Não permite desativar a si mesmo
        if ($id === (int)($_SESSION['admin_user_id'] ?? 0)) {
            header('Location: /admin/admins.php?err=self');
            exit;
        }
        db()->prepare("UPDATE admin_users SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/admins.php');
        exit;
    }

    if ($id > 0 && $action === 'delete') {
        if ($id === (int)($_SESSION['admin_user_id'] ?? 0)) {
            header('Location: /admin/admins.php?err=self');
            exit;
        }
        db()->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
        header('Location: /admin/admins.php');
        exit;
    }
}

$admins = db()->query("SELECT id, username, is_active, created_at, updated_at FROM admin_users ORDER BY id ASC")->fetchAll();
$err = $_GET['err'] ?? '';
?>

<?php if ($err === 'self'): ?>
    <div class="alert alert-warning">Você não pode desativar/excluir o usuário logado.</div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="text-body-secondary small">Gerencie usuários administradores e redefina senhas.</div>
    <a class="btn btn-primary" href="/admin/admin_edit.php"><i class="las la-user-plus me-1"></i>Novo admin</a>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Status</th>
                    <th>Criado</th>
                    <th>Atualizado</th>
                    <th class="text-end">Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($admins as $a): ?>
                    <?php
                    $id = (int)$a['id'];
                    $activeBadge = ((int)$a['is_active'] === 1)
                        ? '<span class="badge bg-success">Ativo</span>'
                        : '<span class="badge bg-secondary">Inativo</span>';
                    ?>
                    <tr>
                        <td><?= $id ?></td>
                        <td class="fw-semibold"><?= h($a['username']) ?></td>
                        <td><?= $activeBadge ?></td>
                        <td><span class="text-body-secondary small"><?= h((string)$a['created_at']) ?></span></td>
                        <td><span class="text-body-secondary small"><?= h((string)$a['updated_at']) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/admin_edit.php?id=<?= $id ?>">Editar / senha</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="btn btn-sm btn-outline-warning" name="action" value="toggle" type="submit">
                                    <?= ((int)$a['is_active'] === 1) ? 'Desativar' : 'Ativar' ?>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit"
                                        onclick="return confirm('Excluir este admin?')">
                                    Excluir
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

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


