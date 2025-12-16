<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    if ($id > 0 && $action === 'toggle_featured') {
        db()->prepare("UPDATE knowledge_base_articles SET is_featured = IF(is_featured=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/kb_articles.php');
        exit;
    }
    
    if ($id > 0 && $action === 'toggle_pinned') {
        db()->prepare("UPDATE knowledge_base_articles SET is_pinned = IF(is_pinned=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/kb_articles.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM knowledge_base_articles WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Artigo excluído com sucesso.';
        header('Location: /admin/kb_articles.php');
        exit;
    }
}

$page_title = 'Artigos - Base de Conhecimento';
$active = 'kb_articles';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar artigos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['draft', 'published', 'archived'], true)) {
        $where[] = "a.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($categoryFilter > 0) {
        $where[] = "a.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    if ($search !== '') {
        $where[] = "(a.title LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT a.*, 
                   c.name as category_name,
                   u.username as author_name
            FROM knowledge_base_articles a
            LEFT JOIN knowledge_base_categories c ON a.category_id = c.id
            LEFT JOIN admin_users u ON a.author_id = u.id
            {$whereClause}
            ORDER BY a.is_pinned DESC, a.sort_order ASC, a.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
    
    // Buscar categorias para filtro
    $categories = db()->query("SELECT id, name FROM knowledge_base_categories WHERE is_active=1 ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $articles = [];
    $categories = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Artigos - Base de Conhecimento</h1>
        <div>
            <a href="/admin/kb_categories.php" class="btn btn-secondary me-2">
                <i class="las la-folder me-1"></i> Categorias
            </a>
            <a href="/admin/kb_article_edit.php" class="btn btn-primary">
                <i class="las la-plus me-1"></i> Novo Artigo
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Título, conteúdo, tags...">
                </div>
                <div class="col-md-3">
                    <label for="category_id" class="form-label">Categoria</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= h($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Publicado</option>
                        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($articles)): ?>
                <div class="text-center py-5">
                    <i class="las la-file-alt text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum artigo cadastrado ainda.</p>
                    <a href="/admin/kb_article_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Artigo
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Autor</th>
                                <th>Status</th>
                                <th>Visualizações</th>
                                <th>Feedback</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                                <tr class="<?= (int)$article['is_pinned'] === 1 ? 'table-warning' : '' ?>">
                                    <td>
                                        <?php if ((int)$article['is_pinned'] === 1): ?>
                                            <i class="las la-thumbtack text-warning me-1"></i>
                                        <?php endif; ?>
                                        <?php if ((int)$article['is_featured'] === 1): ?>
                                            <i class="las la-star text-warning me-1"></i>
                                        <?php endif; ?>
                                        <strong><?= h($article['title']) ?></strong>
                                        <?php if ($article['excerpt']): ?>
                                            <br><small class="text-muted"><?= h(substr($article['excerpt'], 0, 60)) ?><?= strlen($article['excerpt']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= h($article['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= h($article['author_name'] ?? 'Sistema') ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'draft' => 'bg-secondary',
                                            'published' => 'bg-success',
                                            'archived' => 'bg-warning'
                                        ];
                                        $statusLabels = [
                                            'draft' => 'Rascunho',
                                            'published' => 'Publicado',
                                            'archived' => 'Arquivado'
                                        ];
                                        ?>
                                        <span class="badge <?= $statusBadges[$article['status']] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$article['status']] ?? ucfirst($article['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= number_format((int)$article['view_count']) ?></span>
                                    </td>
                                    <td>
                                        <small>
                                            <span class="text-success"><?= (int)$article['helpful_count'] ?> útil</span> / 
                                            <span class="text-danger"><?= (int)$article['not_helpful_count'] ?> não útil</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/kb_article_edit.php?id=<?= (int)$article['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$article['is_featured'] === 1 ? 'warning' : 'secondary' ?>" title="<?= (int)$article['is_featured'] === 1 ? 'Remover destaque' : 'Destacar' ?>">
                                                    <i class="las la-star"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_pinned">
                                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$article['is_pinned'] === 1 ? 'warning' : 'secondary' ?>" title="<?= (int)$article['is_pinned'] === 1 ? 'Desafixar' : 'Fixar' ?>">
                                                    <i class="las la-thumbtack"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este artigo?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

