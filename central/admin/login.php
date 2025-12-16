<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';

// Bootstrapping: cria hash do admin padrão se vier vazio no seed
function ensure_default_admin_password(): void {
    $row = db()->query("SELECT id, password_hash FROM admin_users WHERE username='admin' LIMIT 1")->fetch();
    if (!$row) return;
    if (!empty($row['password_hash'])) return;
    $pass = env('ADMIN_DEFAULT_PASS', 'admin123') ?? 'admin123';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = db()->prepare("UPDATE admin_users SET password_hash=? WHERE id=?");
    $stmt->execute([$hash, (int)$row['id']]);
}
ensure_default_admin_password();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare("SELECT id, username, password_hash, is_active FROM admin_users WHERE username=? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !(int)$user['is_active'] || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        $error = 'Usuário ou senha inválidos.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_username'] = (string)$user['username'];
        header('Location: /admin/dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GouTec Admin - Login</title>
    <link rel="stylesheet" href="/admin/assets/css/main.css">
    <style>
        .login-bg {
            min-height: 100vh;
            background: radial-gradient(1200px 500px at 20% 20%, rgba(13,110,253,.20), transparent 50%),
                        radial-gradient(900px 450px at 80% 10%, rgba(255,255,255,.14), transparent 55%),
                        linear-gradient(180deg, #0b122d 0%, #070c1f 60%, #070c1f 100%);
        }
        .login-card {
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(10px);
        }
        .login-brand {
            display:flex; align-items:center; justify-content:center; gap:12px;
        }
        .login-brand img { height: 34px; }
        .login-muted { color: rgba(255,255,255,.70); }
    </style>
</head>
<body class="login-bg">
<div class="container py-5">
    <div class="row justify-content-center align-items-center">
        <div class="col-md-7 col-lg-5">
            <div class="text-center mb-4">
                <div class="login-brand">
                    <img src="/admin/assets/img/logo-light.png" alt="GouTec">
                </div>
                <div class="login-muted mt-2">Painel administrativo</div>
            </div>
            <div class="card shadow-lg rounded-4 login-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">Entrar</h4>
                            <p class="text-body-secondary mb-0">Acesse com suas credenciais.</p>
                        </div>
                        <div class="text-primary fs-2"><i class="las la-user-shield"></i></div>
                    </div>
                    <hr class="my-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Usuário</label>
                            <input class="form-control" name="username" autocomplete="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input class="form-control" type="password" name="password" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary w-100 py-2" type="submit">Entrar</button>
                    </form>
                    <div class="mt-3 small text-body-secondary">
                        Dica: você pode trocar a senha em <b>Administradores</b> após logar.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>


