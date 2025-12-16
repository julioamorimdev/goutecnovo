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

$page_title = $id ? 'Editar Artigo' : 'Novo Artigo';
$active = 'kb_articles';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'category_id' => 0,
    'status' => 'draft',
    'is_featured' => 0,
    'is_pinned' => 0,
    'tags' => '',
    'meta_keywords' => '',
    'meta_description' => '',
    'sort_order' => 0,
    'published_at' => '',
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM knowledge_base_articles WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Artigo não encontrado.');
        }
        $item = array_merge($item, $row);
        if ($item['published_at']) {
            $item['published_at'] = date('Y-m-d\TH:i', strtotime($item['published_at']));
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar artigo.');
    }
}

// Buscar categorias
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $categories = db()->query("SELECT id, name FROM knowledge_base_categories WHERE is_active=1 ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $title = trim((string)($_POST['title'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'draft'));
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $tags = trim((string)($_POST['tags'] ?? ''));
    $metaKeywords = trim((string)($_POST['meta_keywords'] ?? ''));
    $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $publishedAt = trim((string)($_POST['published_at'] ?? ''));
    
    if ($title === '') $error = 'O título é obrigatório.';
    if ($content === '') $error = 'O conteúdo é obrigatório.';
    if ($categoryId <= 0) $error = 'A categoria é obrigatória.';
    
    // Gerar slug se não fornecido
    if ($slug === '' && $name !== '') {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'draft';
    }
    
    // Se status for published e não tiver data de publicação, usar agora
    if ($status === 'published' && $publishedAt === '') {
        $publishedAt = date('Y-m-d H:i:s');
    } elseif ($status !== 'published') {
        $publishedAt = null;
    } else {
        $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAt));
    }
    
    // Verificar se o slug já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM knowledge_base_articles WHERE slug=? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $error = 'Este slug já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'excerpt' => $excerpt !== '' ? $excerpt : null,
        'category_id' => $categoryId,
        'status' => $status,
        'is_featured' => $isFeatured,
        'is_pinned' => $isPinned,
        'tags' => $tags !== '' ? $tags : null,
        'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
        'meta_description' => $metaDescription !== '' ? $metaDescription : null,
        'sort_order' => $sortOrder,
        'published_at' => $publishedAt,
        'author_id' => (int)($_SESSION['admin_user_id'] ?? 0),
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE knowledge_base_articles SET title=:title, slug=:slug, content=:content, excerpt=:excerpt, category_id=:category_id, status=:status, is_featured=:is_featured, is_pinned=:is_pinned, tags=:tags, meta_keywords=:meta_keywords, meta_description=:meta_description, sort_order=:sort_order, published_at=:published_at WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Artigo atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO knowledge_base_articles (title, slug, content, excerpt, category_id, status, is_featured, is_pinned, tags, meta_keywords, meta_description, sort_order, published_at, author_id) VALUES (:title, :slug, :content, :excerpt, :category_id, :status, :is_featured, :is_pinned, :tags, :meta_keywords, :meta_description, :sort_order, :published_at, :author_id)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Artigo criado com sucesso.';
            }
            header('Location: /admin/kb_articles.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar artigo: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    if ($publishedAt) {
        $item['published_at'] = date('Y-m-d\TH:i', strtotime($publishedAt));
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Artigo' : 'Novo Artigo' ?></h1>
        <a href="/admin/kb_articles.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Conteúdo do Artigo</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" required onkeyup="generateSlug()">
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= h($item['slug']) ?>" required>
                            <small class="text-muted">URL-friendly (gerado automaticamente a partir do título)</small>
                        </div>

                        <div class="mb-3">
                            <label for="excerpt" class="form-label">Resumo</label>
                            <textarea class="form-control" id="excerpt" name="excerpt" rows="2"><?= h($item['excerpt']) ?></textarea>
                            <small class="text-muted">Breve descrição do artigo (opcional)</small>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="15" required><?= h($item['content']) ?></textarea>
                            <small class="text-muted">HTML permitido</small>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags" value="<?= h($item['tags']) ?>" placeholder="tag1, tag2, tag3">
                            <small class="text-muted">Separe as tags por vírgula</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Categoria e Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Selecione uma categoria...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$item['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= h($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required onchange="togglePublishedDate()">
                                <option value="draft" <?= $item['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                                <option value="published" <?= $item['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
                                <option value="archived" <?= $item['status'] === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                            </select>
                        </div>

                        <div class="mb-3" id="publishedDateContainer" style="display: <?= $item['status'] === 'published' ? 'block' : 'none' ?>;">
                            <label for="published_at" class="form-label">Data/Hora de Publicação</label>
                            <input type="datetime-local" class="form-control" id="published_at" name="published_at" value="<?= h($item['published_at']) ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= (int)$item['is_featured'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_featured">
                                    Artigo em Destaque
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" value="1" <?= (int)$item['is_pinned'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_pinned">
                                    Artigo Fixado
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0">
                            <small class="text-muted">Menor número aparece primeiro</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">SEO</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="meta_keywords" class="form-label">Palavras-chave</label>
                            <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?= h($item['meta_keywords']) ?>" placeholder="palavra1, palavra2, palavra3">
                        </div>

                        <div class="mb-3">
                            <label for="meta_description" class="form-label">Meta Descrição</label>
                            <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?= h($item['meta_description']) ?></textarea>
                            <small class="text-muted">Descrição para mecanismos de busca (recomendado: 150-160 caracteres)</small>
                        </div>
                    </div>
                </div>

                <?php if ($id > 0): ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Estatísticas</h5>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted">
                                <div><strong>Visualizações:</strong> <?= number_format((int)$item['view_count']) ?></div>
                                <div><strong>Útil:</strong> <?= (int)$item['helpful_count'] ?></div>
                                <div><strong>Não útil:</strong> <?= (int)$item['not_helpful_count'] ?></div>
                                <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/kb_articles.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function generateSlug() {
    const title = document.getElementById('title').value;
    const slug = title.toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
}

function togglePublishedDate() {
    const status = document.getElementById('status').value;
    const container = document.getElementById('publishedDateContainer');
    container.style.display = status === 'published' ? 'block' : 'none';
    if (status === 'published' && !document.getElementById('published_at').value) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('published_at').value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

