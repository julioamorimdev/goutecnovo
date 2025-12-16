<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = $id ? 'Editar Resposta Predefinida' : 'Nova Resposta Predefinida';
$active = 'canned_responses';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'title' => '',
    'category' => 'general',
    'subject' => '',
    'message' => '',
    'tags' => '',
    'is_active' => 1,
    'sort_order' => 0,
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM canned_responses WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Resposta predefinida não encontrada.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar resposta predefinida.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'general'));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $tags = trim((string)($_POST['tags'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    
    if ($title === '') $error = 'O título é obrigatório.';
    if ($message === '') $error = 'A mensagem é obrigatória.';
    
    if (!in_array($category, ['general', 'technical', 'billing', 'sales', 'other'], true)) {
        $category = 'general';
    }

    $data = [
        'title' => $title,
        'category' => $category,
        'subject' => $subject !== '' ? $subject : null,
        'message' => $message,
        'tags' => $tags !== '' ? $tags : null,
        'is_active' => $isActive,
        'sort_order' => $sortOrder,
        'created_by' => $id === 0 ? (int)($_SESSION['admin_user_id'] ?? 0) : null,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE canned_responses SET title=:title, category=:category, subject=:subject, message=:message, tags=:tags, is_active=:is_active, sort_order=:sort_order WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Resposta predefinida atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO canned_responses (title, category, subject, message, tags, is_active, sort_order, created_by) VALUES (:title, :category, :subject, :message, :tags, :is_active, :sort_order, :created_by)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Resposta predefinida criada com sucesso.';
            }
            header('Location: /admin/canned_responses.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar resposta predefinida: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Resposta Predefinida' : 'Nova Resposta Predefinida' ?></h1>
        <a href="/admin/canned_responses.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações da Resposta</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" required placeholder="Ex: Problema de acesso ao painel">
                            <small class="text-muted">Nome descritivo para identificar esta resposta</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="general" <?= $item['category'] === 'general' ? 'selected' : '' ?>>Geral</option>
                                    <option value="technical" <?= $item['category'] === 'technical' ? 'selected' : '' ?>>Técnico</option>
                                    <option value="billing" <?= $item['category'] === 'billing' ? 'selected' : '' ?>>Faturamento</option>
                                    <option value="sales" <?= $item['category'] === 'sales' ? 'selected' : '' ?>>Vendas</option>
                                    <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Assunto Padrão</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?= h($item['subject']) ?>" placeholder="Assunto que será usado ao responder tickets">
                                <small class="text-muted">Opcional - será usado como assunto padrão</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Mensagem <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="12" required placeholder="Conteúdo da resposta predefinida..."><?= h($item['message']) ?></textarea>
                            <small class="text-muted">Você pode usar variáveis como {nome_cliente}, {ticket_number}, etc.</small>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags" value="<?= h($item['tags']) ?>" placeholder="acesso, senha, painel, problema">
                            <small class="text-muted">Separe as tags por vírgula para facilitar a busca</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Resposta Ativa
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0">
                            <small class="text-muted">Menor número aparece primeiro</small>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Estatísticas</label>
                                <div class="small text-muted">
                                    <div><strong>Uso:</strong> <?= (int)$item['usage_count'] ?> vezes</div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                    <?php if ($item['updated_at'] && $item['updated_at'] !== $item['created_at']): ?>
                                        <div><strong>Atualizado em:</strong> <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Variáveis Disponíveis</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <p>Você pode usar as seguintes variáveis na mensagem:</p>
                            <ul class="list-unstyled">
                                <li><code>{nome_cliente}</code> - Nome do cliente</li>
                                <li><code>{email_cliente}</code> - Email do cliente</li>
                                <li><code>{ticket_number}</code> - Número do ticket</li>
                                <li><code>{assunto_ticket}</code> - Assunto do ticket</li>
                                <li><code>{data_atual}</code> - Data atual</li>
                            </ul>
                            <p class="text-muted mb-0">As variáveis serão substituídas automaticamente ao usar a resposta.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/canned_responses.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

