<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$page_title = 'Editar administrador';
$active = 'admins';
require_once __DIR__ . '/partials/layout_start.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_new = $id <= 0;

$admin = [
    'username' => '',
    'is_active' => 1,
];

if (!$is_new) {
    $stmt = db()->prepare("SELECT id, username, is_active FROM admin_users WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Admin não encontrado.');
    }
    $admin = array_merge($admin, $row);
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $username = trim((string)($_POST['username'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = (string)($_POST['new_password'] ?? '');
    $new_password2 = (string)($_POST['new_password2'] ?? '');

    if ($username === '') $error = 'Usuário é obrigatório.';
    if ($is_new && $new_password === '') $error = $error ?: 'Defina uma senha para o novo admin.';
    if ($new_password !== '' && strlen($new_password) < 6) $error = $error ?: 'A senha deve ter pelo menos 6 caracteres.';
    if ($new_password !== '' && $new_password !== $new_password2) $error = $error ?: 'As senhas não conferem.';

    if (!$is_new && $id === (int)($_SESSION['admin_user_id'] ?? 0) && $is_active === 0) {
        $error = $error ?: 'Você não pode desativar o usuário logado.';
    }

    if (!$error) {
        try {
            if ($is_new) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = db()->prepare("INSERT INTO admin_users (username, password_hash, is_active) VALUES (?,?,?)");
                $stmt->execute([$username, $hash, $is_active]);
                $success = 'Administrador criado com sucesso.';
                $id = (int)db()->lastInsertId();
                $is_new = false;
            } else {
                // username + status
                $stmt = db()->prepare("UPDATE admin_users SET username=?, is_active=? WHERE id=?");
                $stmt->execute([$username, $is_active, $id]);

                // senha (opcional)
                if ($new_password !== '') {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = db()->prepare("UPDATE admin_users SET password_hash=? WHERE id=?");
                    $stmt->execute([$hash, $id]);
                }
                $success = 'Alterações salvas.';
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $error = 'Esse usuário já existe.';
            } else {
                $error = 'Erro ao salvar. Verifique os dados.';
            }
        }
    }

    $admin['username'] = $username;
    $admin['is_active'] = $is_active;
}

$page_title = $is_new ? 'Novo administrador' : 'Editar administrador';
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Usuário</label>
                    <input class="form-control" name="username" value="<?= h($admin['username']) ?>" required>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ((int)$admin['is_active'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Ativo</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><?= $is_new ? 'Senha' : 'Nova senha (opcional)' ?></label>
                    <input class="form-control" type="password" name="new_password" autocomplete="new-password" placeholder="<?= $is_new ? '' : 'Deixe em branco para manter' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmar senha</label>
                    <input class="form-control" type="password" name="new_password2" autocomplete="new-password">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/admins.php">Voltar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


