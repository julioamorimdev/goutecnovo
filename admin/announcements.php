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
        db()->prepare("UPDATE announcements SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/announcements.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Anúncio excluído com sucesso.';
        header('Location: /admin/announcements.php');
        exit;
    }
}

$page_title = 'Anúncios';
$active = 'announcements';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar anúncios
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $typeFilter = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($typeFilter && in_array($typeFilter, ['info', 'warning', 'success', 'error', 'maintenance'], true)) {
        $where[] = "type = ?";
        $params[] = $typeFilter;
    }
    
    if ($statusFilter === 'active') {
        $where[] = "is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where[] = "is_active = 0";
    }
    
    if ($search !== '') {
        $where[] = "(title LIKE ? OR content LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM announcements {$whereClause} ORDER BY priority DESC, created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll();
} catch (Throwable $e) {
    $announcements = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Anúncios</h1>
        <a href="/admin/announcement_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Anúncio
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Título, conteúdo...">
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Tipo</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="info" <?= $typeFilter === 'info' ? 'selected' : '' ?>>Informação</option>
                        <option value="warning" <?= $typeFilter === 'warning' ? 'selected' : '' ?>>Aviso</option>
                        <option value="success" <?= $typeFilter === 'success' ? 'selected' : '' ?>>Sucesso</option>
                        <option value="error" <?= $typeFilter === 'error' ? 'selected' : '' ?>>Erro</option>
                        <option value="maintenance" <?= $typeFilter === 'maintenance' ? 'selected' : '' ?>>Manutenção</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
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
            <?php if (empty($announcements)): ?>
                <div class="text-center py-5">
                    <i class="las la-bullhorn text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum anúncio cadastrado ainda.</p>
                    <a href="/admin/announcement_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Anúncio
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Público</th>
                                <th>Período</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($announcements as $ann): 
                                $isActive = (int)$ann['is_active'] === 1;
                                $now = time();
                                $startDate = $ann['start_date'] ? strtotime($ann['start_date']) : null;
                                $endDate = $ann['end_date'] ? strtotime($ann['end_date']) : null;
                                $isScheduled = ($startDate && $now < $startDate) || ($endDate && $now > $endDate);
                            ?>
                                <tr class="<?= !$isActive ? 'table-secondary' : ($isScheduled ? 'table-warning' : '') ?>">
                                    <td>
                                        <strong><?= h($ann['title']) ?></strong>
                                        <?php if ($ann['content']): ?>
                                            <br><small class="text-muted"><?= h(substr(strip_tags($ann['content']), 0, 60)) ?><?= strlen(strip_tags($ann['content'])) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $typeBadges = [
                                            'info' => 'bg-info',
                                            'warning' => 'bg-warning',
                                            'success' => 'bg-success',
                                            'error' => 'bg-danger',
                                            'maintenance' => 'bg-secondary'
                                        ];
                                        $typeLabels = [
                                            'info' => 'Informação',
                                            'warning' => 'Aviso',
                                            'success' => 'Sucesso',
                                            'error' => 'Erro',
                                            'maintenance' => 'Manutenção'
                                        ];
                                        ?>
                                        <span class="badge <?= $typeBadges[$ann['type']] ?? 'bg-secondary' ?>">
                                            <?= $typeLabels[$ann['type']] ?? ucfirst($ann['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $audienceLabels = [
                                            'all' => 'Todos',
                                            'clients' => 'Clientes',
                                            'admins' => 'Administradores',
                                            'specific' => 'Específico'
                                        ];
                                        ?>
                                        <small><?= $audienceLabels[$ann['target_audience']] ?? ucfirst($ann['target_audience']) ?></small>
                                        <?php if ((int)$ann['show_on_dashboard'] === 1): ?>
                                            <br><small class="badge bg-primary">Dashboard</small>
                                        <?php endif; ?>
                                        <?php if ((int)$ann['show_on_client_area'] === 1): ?>
                                            <br><small class="badge bg-success">Área Cliente</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ann['start_date'] || $ann['end_date']): ?>
                                            <?php if ($ann['start_date']): ?>
                                                <small><strong>Início:</strong> <?= date('d/m/Y H:i', strtotime($ann['start_date'])) ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($ann['end_date']): ?>
                                                <small><strong>Fim:</strong> <?= date('d/m/Y H:i', strtotime($ann['end_date'])) ?></small>
                                                <?php if ($endDate && $now > $endDate): ?>
                                                    <br><span class="badge bg-danger">Expirado</span>
                                                <?php elseif ($startDate && $now < $startDate): ?>
                                                    <br><span class="badge bg-info">Agendado</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sem período definido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= (int)$ann['priority'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/announcement_edit.php?id=<?= (int)$ann['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $isActive ? 'warning' : 'success' ?>" title="<?= $isActive ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= $isActive ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este anúncio?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
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

