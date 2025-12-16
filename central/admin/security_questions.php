<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Questões de Segurança';
$active = 'security_questions';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $question = trim($_POST['question'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order'] ?? 0);
            
            if (empty($question)) {
                throw new Exception('Pergunta é obrigatória.');
            }
            
            if ($id) {
                $stmt = db()->prepare("UPDATE security_questions SET question = ?, is_active = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$question, $is_active, $sort_order, $id]);
            } else {
                $stmt = db()->prepare("INSERT INTO security_questions (question, is_active, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$question, $is_active, $sort_order]);
            }
            
            $_SESSION['success'] = 'Questão de segurança salva com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM security_questions WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Questão excluída com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar questões
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM security_questions ORDER BY sort_order, question");
    $questions = $stmt->fetchAll();
} catch (Throwable $e) {
    $questions = [];
}

$editingQuestion = null;
if (isset($_GET['edit'])) {
    foreach ($questions as $q) {
        if ($q['id'] == $_GET['edit']) {
            $editingQuestion = $q;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Questões de Segurança</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#questionModal">
            <i class="las la-plus me-1"></i> Nova Questão
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Pergunta</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nenhuma questão cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td><?= $q['sort_order'] ?></td>
                                    <td><?= h($q['question']) ?></td>
                                    <td>
                                        <?php if ($q['is_active']): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta questão?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="las la-trash"></i> Excluir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nova/Editar Questão -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingQuestion['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingQuestion ? 'Editar' : 'Nova' ?> Questão de Segurança</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pergunta *</label>
                        <textarea class="form-control" name="question" rows="3" required placeholder="Ex: Qual era o nome do seu primeiro animal de estimação?"><?= h($editingQuestion['question'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= h($editingQuestion['sort_order'] ?? '0') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingQuestion['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Questão ativa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Questão</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editingQuestion): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('questionModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

