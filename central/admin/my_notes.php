<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Minhas Notas';
$active = 'my_notes';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = $_SESSION['admin_user_id'] ?? null;
if (!$adminId) {
    header('Location: /admin/login.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $color = $_POST['color'] ?? '#ffffff';
            $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
            
            if (empty($title) || empty($content)) {
                throw new Exception('Título e conteúdo são obrigatórios.');
            }
            
            if ($id) {
                $stmt = db()->prepare("UPDATE admin_notes SET title = ?, content = ?, color = ?, is_pinned = ? WHERE id = ? AND admin_id = ?");
                $stmt->execute([$title, $content, $color, $is_pinned, $id, $adminId]);
            } else {
                $stmt = db()->prepare("INSERT INTO admin_notes (admin_id, title, content, color, is_pinned) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$adminId, $title, $content, $color, $is_pinned]);
            }
            
            $_SESSION['success'] = 'Nota salva com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM admin_notes WHERE id = ? AND admin_id = ?");
            $stmt->execute([$id, $adminId]);
            $_SESSION['success'] = 'Nota excluída com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar notas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->prepare("SELECT * FROM admin_notes WHERE admin_id = ? ORDER BY is_pinned DESC, created_at DESC");
    $stmt->execute([$adminId]);
    $notes = $stmt->fetchAll();
} catch (Throwable $e) {
    $notes = [];
}

$editingNote = null;
if (isset($_GET['edit'])) {
    foreach ($notes as $note) {
        if ($note['id'] == $_GET['edit']) {
            $editingNote = $note;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Minhas Notas</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
            <i class="las la-plus me-1"></i> Nova Nota
        </button>
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

    <?php if (empty($notes)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="las la-sticky-note text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3">Você ainda não tem notas. Crie sua primeira nota!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($notes as $note): ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100" style="border-left: 4px solid <?= h($note['color']) ?>;">
                        <?php if ($note['is_pinned']): ?>
                            <div class="card-header bg-light">
                                <i class="las la-thumbtack text-warning"></i> Fixada
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= h($note['title']) ?></h5>
                            <p class="card-text"><?= nl2br(h($note['content'])) ?></p>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?></small>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="?edit=<?= $note['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="las la-edit"></i> Editar
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta nota?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $note['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="las la-trash"></i> Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Nova/Editar Nota -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingNote['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingNote ? 'Editar' : 'Nova' ?> Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="title" required value="<?= h($editingNote['title'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conteúdo *</label>
                        <textarea class="form-control" name="content" rows="6" required><?= h($editingNote['content'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" name="color" value="<?= h($editingNote['color'] ?? '#ffffff') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Opções</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_pinned" value="1" <?= ($editingNote['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Fixar nota</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Nota</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editingNote): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('noteModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

