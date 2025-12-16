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
    
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE knowledge_base_categories SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/kb_categories.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se há artigos na categoria
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM knowledge_base_articles WHERE category_id=?");
        $stmt->execute([$id]);
        $count = (int)$stmt->fetch()['cnt'];
        
        if ($count > 0) {
            $_SESSION['error'] = 'Não é possível excluir a categoria pois existem ' . $count . ' artigo(s) vinculado(s).';
        } else {
            db()->prepare("DELETE FROM knowledge_base_categories WHERE id=?")->execute([$id]);
            $_SESSION['success'] = 'Categoria excluída com sucesso.';
        }
        header('Location: /admin/kb_categories.php');
        exit;
    }
}

$page_title = 'Categorias - Base de Conhecimento';
$active = 'kb_categories';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar categorias
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter === 'active') {
        $where[] = "c.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where[] = "c.is_active = 0";
    }
    
    if ($search !== '') {
        $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT c.*, 
                   p.name as parent_name,
                   (SELECT COUNT(*) FROM knowledge_base_articles WHERE category_id = c.id) as article_count
            FROM knowledge_base_categories c
            LEFT JOIN knowledge_base_categories p ON c.parent_id = p.id
            {$whereClause}
            ORDER BY c.sort_order ASC, c.name ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Categorias - Base de Conhecimento</h1>
        <div>
            <a href="/admin/kb_articles.php" class="btn btn-secondary me-2">
                <i class="las la-file-alt me-1"></i> Artigos
            </a>
            <a href="/admin/kb_category_edit.php" class="btn btn-primary">
                <i class="las la-plus me-1"></i> Nova Categoria
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

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Nome, descrição...">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativas</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativas</option>
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
            <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="las la-folder text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma categoria cadastrada ainda.</p>
                    <a href="/admin/kb_category_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeira Categoria
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Slug</th>
                                <th>Categoria Pai</th>
                                <th>Artigos</th>
                                <th>Ícone</th>
                                <th>Ordem</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($cat['name']) ?></strong>
                                        <?php if ($cat['description']): ?>
                                            <br><small class="text-muted"><?= h(substr($cat['description'], 0, 60)) ?><?= strlen($cat['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= h($cat['slug']) ?></code>
                                    </td>
                                    <td>
                                        <?php if ($cat['parent_name']): ?>
                                            <span class="badge bg-info"><?= h($cat['parent_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= (int)$cat['article_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($cat['icon']): ?>
                                            <i class="<?= h($cat['icon']) ?>"></i>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= (int)$cat['sort_order'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$cat['is_active'] === 1): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/kb_category_edit.php?id=<?= (int)$cat['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$cat['is_active'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$cat['is_active'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$cat['is_active'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
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

