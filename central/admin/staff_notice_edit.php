<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Criar/Editar Aviso';
$active = 'staff_notices';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = (int)($_SESSION['admin_user_id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$notice = null;

// Buscar aviso se estiver editando
if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM staff_notices WHERE id = ? AND admin_id = ?");
        $stmt->execute([$id, $adminId]);
        $notice = $stmt->fetch();
        
        if (!$notice) {
            $_SESSION['error'] = 'Aviso não encontrado ou você não tem permissão para editá-lo.';
            header('Location: /admin/staff_notices.php');
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao buscar aviso.';
        header('Location: /admin/staff_notices.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $deleteId = (int)$_POST['id'];
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("DELETE FROM staff_notices WHERE id = ? AND admin_id = ?");
            $stmt->execute([$deleteId, $adminId]);
            $_SESSION['success'] = 'Aviso excluído com sucesso.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao excluir aviso: ' . $e->getMessage();
        }
        header('Location: /admin/staff_notices.php');
        exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $targetAdminIds = isset($_POST['target_admin_ids']) && is_array($_POST['target_admin_ids']) 
        ? array_map('intval', $_POST['target_admin_ids']) 
        : [];
    $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = 'Título e conteúdo são obrigatórios.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $targetIdsJson = !empty($targetAdminIds) ? json_encode($targetAdminIds) : null;
            
            if ($id > 0) {
                // Atualizar
                $stmt = db()->prepare("UPDATE staff_notices SET title=?, content=?, priority=?, is_pinned=?, is_public=?, target_admin_ids=?, expires_at=? WHERE id=? AND admin_id=?");
                $stmt->execute([$title, $content, $priority, $isPinned, $isPublic, $targetIdsJson, $expiresAt, $id, $adminId]);
                $_SESSION['success'] = 'Aviso atualizado com sucesso.';
            } else {
                // Criar
                $stmt = db()->prepare("INSERT INTO staff_notices (admin_id, title, content, priority, is_pinned, is_public, target_admin_ids, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$adminId, $title, $content, $priority, $isPinned, $isPublic, $targetIdsJson, $expiresAt]);
                $_SESSION['success'] = 'Aviso criado com sucesso.';
            }
            
            header('Location: /admin/staff_notices.php');
            exit;
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao salvar aviso: ' . $e->getMessage();
        }
    }
}

// Buscar administradores para seleção
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT id, username, email FROM admin_users ORDER BY username");
    $admins = $stmt->fetchAll();
} catch (Throwable $e) {
    $admins = [];
}

// Parsear target_admin_ids se existir
$selectedAdminIds = [];
if ($notice && $notice['target_admin_ids']) {
    $selectedAdminIds = json_decode($notice['target_admin_ids'], true) ?? [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id > 0 ? 'Editar' : 'Criar' ?> Aviso</h1>
        <a href="/admin/staff_notices.php" class="btn btn-secondary">
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
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= h($notice['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="10" required><?= h($notice['content'] ?? '') ?></textarea>
                            <small class="text-muted">Use quebras de linha para formatar o texto.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Prioridade</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?= ($notice['priority'] ?? 'normal') === 'low' ? 'selected' : '' ?>>Baixa</option>
                                    <option value="normal" <?= ($notice['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="high" <?= ($notice['priority'] ?? 'normal') === 'high' ? 'selected' : '' ?>>Alta</option>
                                    <option value="urgent" <?= ($notice['priority'] ?? 'normal') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expires_at" class="form-label">Data de Expiração (opcional)</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" 
                                       value="<?= $notice && $notice['expires_at'] ? date('Y-m-d\TH:i', strtotime($notice['expires_at'])) : '' ?>">
                                <small class="text-muted">O aviso será automaticamente ocultado após esta data.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" 
                                       <?= ($notice['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_pinned">
                                    Fixar no topo
                                </label>
                                <small class="text-muted d-block">Avisos fixados aparecem primeiro na lista.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                       <?= ($notice['is_public'] ?? 1) ? 'checked' : '' ?> onchange="toggleTargetAdmins()">
                                <label class="form-check-label" for="is_public">
                                    Aviso público (visível para todos)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="targetAdminsSection" style="display: <?= ($notice['is_public'] ?? 1) ? 'none' : 'block' ?>;">
                            <label class="form-label">Destinatários (se não for público)</label>
                            <select class="form-select" id="target_admin_ids" name="target_admin_ids[]" multiple size="5">
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?= $admin['id'] ?>" 
                                            <?= in_array($admin['id'], $selectedAdminIds) ? 'selected' : '' ?>>
                                        <?= h($admin['username']) ?> (<?= h($admin['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl (ou Cmd no Mac) para selecionar múltiplos administradores.</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="las la-save me-1"></i> Salvar Aviso
                            </button>
                            <?php if ($id > 0): ?>
                                <button type="button" class="btn btn-danger" onclick="deleteNotice()">
                                    <i class="las la-trash me-1"></i> Excluir
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <?php if ($id > 0): ?>
                        <form method="POST" id="deleteForm" style="display: none;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $id ?>">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="las la-info-circle me-2"></i> Informações</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        <strong>Prioridades:</strong>
                    </p>
                    <ul class="small">
                        <li><span class="badge bg-secondary">Baixa</span> - Informações gerais</li>
                        <li><span class="badge bg-info">Normal</span> - Avisos padrão</li>
                        <li><span class="badge bg-warning">Alta</span> - Importante</li>
                        <li><span class="badge bg-danger">Urgente</span> - Ação imediata necessária</li>
                    </ul>
                    
                    <hr>
                    
                    <p class="small text-muted">
                        <strong>Dicas:</strong>
                    </p>
                    <ul class="small">
                        <li>Avisos fixados aparecem sempre no topo</li>
                        <li>Avisos públicos são visíveis para todos os administradores</li>
                        <li>Avisos privados podem ser direcionados a administradores específicos</li>
                        <li>Avisos expirados são automaticamente ocultados</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTargetAdmins() {
    const isPublic = document.getElementById('is_public').checked;
    const targetSection = document.getElementById('targetAdminsSection');
    targetSection.style.display = isPublic ? 'none' : 'block';
}

function deleteNotice() {
    if (confirm('Tem certeza que deseja excluir este aviso?')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

