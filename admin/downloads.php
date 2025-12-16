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
        db()->prepare("UPDATE downloads SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/downloads.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        // Verificar se o arquivo existe e pode ser excluído
        $stmt = db()->prepare("SELECT file_path FROM downloads WHERE id=?");
        $stmt->execute([$id]);
        $download = $stmt->fetch();
        
        db()->prepare("DELETE FROM downloads WHERE id=?")->execute([$id]);
        
        // Tentar excluir o arquivo físico (opcional)
        if ($download && $download['file_path'] && file_exists($download['file_path'])) {
            @unlink($download['file_path']);
        }
        
        $_SESSION['success'] = 'Download excluído com sucesso.';
        header('Location: /admin/downloads.php');
        exit;
    }
}

$page_title = 'Downloads';
$active = 'downloads';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar downloads
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $categoryFilter = $_GET['category'] ?? '';
    $accessFilter = $_GET['access'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($categoryFilter && in_array($categoryFilter, ['general', 'documentation', 'software', 'template', 'other'], true)) {
        $where[] = "category = ?";
        $params[] = $categoryFilter;
    }
    
    if ($accessFilter && in_array($accessFilter, ['public', 'clients', 'admins', 'specific'], true)) {
        $where[] = "access_level = ?";
        $params[] = $accessFilter;
    }
    
    if ($statusFilter === 'active') {
        $where[] = "is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where[] = "is_active = 0";
    }
    
    if ($search !== '') {
        $where[] = "(title LIKE ? OR description LIKE ? OR file_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM downloads {$whereClause} ORDER BY sort_order ASC, title ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $downloads = $stmt->fetchAll();
} catch (Throwable $e) {
    $downloads = [];
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Downloads</h1>
        <a href="/admin/download_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Download
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
                <div class="col-md-3">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Título, descrição, arquivo...">
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label">Categoria</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Todas</option>
                        <option value="general" <?= $categoryFilter === 'general' ? 'selected' : '' ?>>Geral</option>
                        <option value="documentation" <?= $categoryFilter === 'documentation' ? 'selected' : '' ?>>Documentação</option>
                        <option value="software" <?= $categoryFilter === 'software' ? 'selected' : '' ?>>Software</option>
                        <option value="template" <?= $categoryFilter === 'template' ? 'selected' : '' ?>>Template</option>
                        <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="access" class="form-label">Acesso</label>
                    <select class="form-select" id="access" name="access">
                        <option value="">Todos</option>
                        <option value="public" <?= $accessFilter === 'public' ? 'selected' : '' ?>>Público</option>
                        <option value="clients" <?= $accessFilter === 'clients' ? 'selected' : '' ?>>Clientes</option>
                        <option value="admins" <?= $accessFilter === 'admins' ? 'selected' : '' ?>>Admins</option>
                        <option value="specific" <?= $accessFilter === 'specific' ? 'selected' : '' ?>>Específico</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($downloads)): ?>
                <div class="text-center py-5">
                    <i class="las la-download text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum download cadastrado ainda.</p>
                    <a href="/admin/download_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Download
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Arquivo</th>
                                <th>Tamanho</th>
                                <th>Acesso</th>
                                <th>Downloads</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($downloads as $download): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($download['title']) ?></strong>
                                        <?php if ($download['description']): ?>
                                            <br><small class="text-muted"><?= h(substr($download['description'], 0, 60)) ?><?= strlen($download['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                        <?php if ($download['version']): ?>
                                            <br><small class="badge bg-info">v<?= h($download['version']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $categoryLabels = [
                                            'general' => 'Geral',
                                            'documentation' => 'Documentação',
                                            'software' => 'Software',
                                            'template' => 'Template',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $categoryLabels[$download['category']] ?? ucfirst($download['category']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= h($download['file_name']) ?></small>
                                        <?php if (!file_exists($download['file_path'])): ?>
                                            <br><span class="badge bg-danger">Arquivo não encontrado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= $download['file_size'] ? formatFileSize((int)$download['file_size']) : '-' ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $accessLabels = [
                                            'public' => 'Público',
                                            'clients' => 'Clientes',
                                            'admins' => 'Admins',
                                            'specific' => 'Específico'
                                        ];
                                        ?>
                                        <small><?= $accessLabels[$download['access_level']] ?? ucfirst($download['access_level']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= number_format((int)$download['download_count']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$download['is_active'] === 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/download_edit.php?id=<?= (int)$download['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$download['is_active'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$download['is_active'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$download['is_active'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este download?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
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

