<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

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
        db()->prepare("UPDATE canned_responses SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/canned_responses.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM canned_responses WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Resposta predefinida excluída com sucesso.';
        header('Location: /admin/canned_responses.php');
        exit;
    }
}

$page_title = 'Respostas Predefinidas';
$active = 'canned_responses';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar respostas predefinidas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $categoryFilter = $_GET['category'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($categoryFilter && in_array($categoryFilter, ['general', 'technical', 'billing', 'sales', 'other'], true)) {
        $where[] = "category = ?";
        $params[] = $categoryFilter;
    }
    
    if ($statusFilter === 'active') {
        $where[] = "is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where[] = "is_active = 0";
    }
    
    if ($search !== '') {
        $where[] = "(title LIKE ? OR message LIKE ? OR tags LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM canned_responses {$whereClause} ORDER BY sort_order ASC, title ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $responses = $stmt->fetchAll();
} catch (Throwable $e) {
    $responses = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Respostas Predefinidas</h1>
        <a href="/admin/canned_response_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Nova Resposta
        </a>
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
                    <label for="category" class="form-label">Categoria</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Todas</option>
                        <option value="general" <?= $categoryFilter === 'general' ? 'selected' : '' ?>>Geral</option>
                        <option value="technical" <?= $categoryFilter === 'technical' ? 'selected' : '' ?>>Técnico</option>
                        <option value="billing" <?= $categoryFilter === 'billing' ? 'selected' : '' ?>>Faturamento</option>
                        <option value="sales" <?= $categoryFilter === 'sales' ? 'selected' : '' ?>>Vendas</option>
                        <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-3">
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
            <?php if (empty($responses)): ?>
                <div class="text-center py-5">
                    <i class="las la-comment-dots text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma resposta predefinida cadastrada ainda.</p>
                    <a href="/admin/canned_response_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeira Resposta
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Assunto</th>
                                <th>Tags</th>
                                <th>Uso</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($response['title']) ?></strong>
                                        <?php if ($response['message']): ?>
                                            <br><small class="text-muted"><?= h(substr($response['message'], 0, 80)) ?><?= strlen($response['message']) > 80 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $categoryLabels = [
                                            'general' => 'Geral',
                                            'technical' => 'Técnico',
                                            'billing' => 'Faturamento',
                                            'sales' => 'Vendas',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $categoryLabels[$response['category']] ?? ucfirst($response['category']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($response['subject']): ?>
                                            <small><?= h($response['subject']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($response['tags']): ?>
                                            <?php
                                            $tags = explode(',', $response['tags']);
                                            foreach (array_slice($tags, 0, 3) as $tag):
                                                $tag = trim($tag);
                                                if ($tag):
                                            ?>
                                                <span class="badge bg-info me-1"><?= h($tag) ?></span>
                                            <?php
                                                endif;
                                            endforeach;
                                            if (count($tags) > 3):
                                            ?>
                                                <small class="text-muted">+<?= count($tags) - 3 ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= (int)$response['usage_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$response['is_active'] === 1): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/canned_response_edit.php?id=<?= (int)$response['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$response['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$response['is_active'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$response['is_active'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$response['is_active'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta resposta?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$response['id'] ?>">
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

