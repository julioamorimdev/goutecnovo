<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Minha Conta';
$active = 'my_account';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = $_SESSION['admin_user_id'] ?? null;
if (!$adminId) {
    header('Location: /admin/login.php');
    exit;
}

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $signature = trim($_POST['signature'] ?? '');
            $ticket_notifications = isset($_POST['ticket_notifications']) ? 1 : 0;
            
            // Verificar se email já existe em outro admin
            if ($email) {
                $stmt = db()->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $adminId]);
                if ($stmt->fetch()) {
                    throw new Exception('Este email já está em uso por outro administrador.');
                }
            }
            
            // Verificar quais colunas existem e construir UPDATE dinamicamente
            $stmt = db()->query("SHOW COLUMNS FROM admin_users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $updateFields = [];
            $updateValues = [];
            
            if (in_array('name', $columns)) {
                $updateFields[] = "name = ?";
                $updateValues[] = $name ?: null;
            }
            if (in_array('email', $columns)) {
                $updateFields[] = "email = ?";
                $updateValues[] = $email ?: null;
            }
            if (in_array('signature', $columns)) {
                $updateFields[] = "signature = ?";
                $updateValues[] = $signature ?: null;
            }
            if (in_array('ticket_notifications', $columns)) {
                $updateFields[] = "ticket_notifications = ?";
                $updateValues[] = $ticket_notifications;
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $adminId;
                $updateSql = "UPDATE admin_users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = db()->prepare($updateSql);
                $stmt->execute($updateValues);
            }
            
            // Atualizar username na sessão se necessário
            if ($name) {
                $_SESSION['admin_username'] = $name;
            }
            
            $_SESSION['success'] = 'Perfil atualizado com sucesso.';
        } elseif ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('Todos os campos de senha são obrigatórios.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('As senhas não coincidem.');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('A nova senha deve ter pelo menos 8 caracteres.');
            }
            
            // Verificar senha atual
            $stmt = db()->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($current_password, $admin['password_hash'])) {
                throw new Exception('Senha atual incorreta.');
            }
            
            // Atualizar senha
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $adminId]);
            
            $_SESSION['success'] = 'Senha alterada com sucesso.';
        } elseif ($action === 'update_photo') {
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_photo'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.');
                }
                
                if ($file['size'] > $maxSize) {
                    throw new Exception('Arquivo muito grande. Tamanho máximo: 2MB.');
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'admin_' . $adminId . '_' . time() . '.' . $ext;
                $uploadDir = '/var/www/goutecnovo/uploads/admin_photos/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Verificar se coluna profile_photo existe
                    $stmt = db()->query("SHOW COLUMNS FROM admin_users LIKE 'profile_photo'");
                    if ($stmt->rowCount() > 0) {
                        // Remover foto antiga se existir
                        $stmt = db()->prepare("SELECT profile_photo FROM admin_users WHERE id = ?");
                        $stmt->execute([$adminId]);
                        $oldPhoto = $stmt->fetchColumn();
                        if ($oldPhoto && file_exists('/var/www/goutecnovo' . $oldPhoto)) {
                            unlink('/var/www/goutecnovo' . $oldPhoto);
                        }
                        
                        $photoPath = '/uploads/admin_photos/' . $filename;
                        $stmt = db()->prepare("UPDATE admin_users SET profile_photo = ? WHERE id = ?");
                        $stmt->execute([$photoPath, $adminId]);
                    } else {
                        // Se coluna não existe, apenas salvar o arquivo
                        $photoPath = '/uploads/admin_photos/' . $filename;
                    }
                    
                    $_SESSION['success'] = 'Foto de perfil atualizada com sucesso.';
                } else {
                    throw new Exception('Erro ao fazer upload da foto.');
                }
            }
        }
        
        db()->commit();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar dados do admin
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Verificar quais colunas existem
    $stmt = db()->query("SHOW COLUMNS FROM admin_users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Construir SELECT apenas com colunas existentes
    $selectFields = ['id', 'username'];
    $optionalFields = ['name', 'email', 'signature', 'ticket_notifications', 'profile_photo'];
    
    foreach ($optionalFields as $field) {
        if (in_array($field, $columns)) {
            $selectFields[] = $field;
        }
    }
    
    $selectSql = "SELECT " . implode(', ', $selectFields) . " FROM admin_users WHERE id = ?";
    $stmt = db()->prepare($selectSql);
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        header('Location: /admin/login.php');
        exit;
    }
    
    // Garantir que todas as chaves existam
    foreach ($optionalFields as $field) {
        if (!isset($admin[$field])) {
            $admin[$field] = null;
        }
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Erro ao carregar dados do perfil: ' . $e->getMessage();
    $admin = ['id' => $adminId, 'username' => $_SESSION['admin_username'] ?? '', 'name' => null, 'email' => null, 'signature' => null, 'ticket_notifications' => 1, 'profile_photo' => null];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Minha Conta</h1>
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

    <div class="row">
        <!-- Perfil -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações do Perfil</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" name="name" value="<?= h($admin['name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= h($admin['email'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" value="<?= h($admin['username'] ?? '') ?>" disabled>
                            <small class="text-muted">O nome de usuário não pode ser alterado.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assinatura para Tickets</label>
                            <textarea class="form-control" name="signature" rows="4"><?= h($admin['signature'] ?? '') ?></textarea>
                            <small class="text-muted">Esta assinatura será adicionada automaticamente aos tickets que você responder.</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ticket_notifications" value="1" <?= ($admin['ticket_notifications'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Receber notificações de novos tickets</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </form>
                </div>
            </div>
            
            <!-- Foto de Perfil -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Foto de Perfil</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4 mb-3">
                        <?php if (!empty($admin['profile_photo'])): ?>
                            <img src="<?= h($admin['profile_photo']) ?>" alt="Foto de Perfil" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded bg-primary d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="las la-user text-white" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="mb-1"><strong>Foto Atual</strong></p>
                            <p class="text-muted small mb-0">Formatos aceitos: JPG, PNG, GIF, WEBP<br>Tamanho máximo: 2MB</p>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_photo">
                        
                        <div class="mb-3">
                            <input type="file" class="form-control" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Atualizar Foto</button>
                    </form>
                </div>
            </div>
            
            <!-- Alterar Senha -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Alterar Senha</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Senha Atual *</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nova Senha *</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8">
                            <small class="text-muted">Mínimo de 8 caracteres.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nova Senha *</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Alterar Senha</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da Conta</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>ID do Administrador:</strong><br>
                        <span class="text-muted">#<?= $adminId ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Usuário:</strong><br>
                        <span class="text-muted"><?= h($admin['username'] ?? '') ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-success">Ativo</span>
                    </div>
                    
                    <hr>
                    
                    <div class="small text-muted">
                        <p class="mb-1"><strong>Dicas de Segurança:</strong></p>
                        <ul class="mb-0">
                            <li>Use uma senha forte e única</li>
                            <li>Não compartilhe suas credenciais</li>
                            <li>Altere sua senha regularmente</li>
                            <li>Ative notificações de tickets se necessário</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

