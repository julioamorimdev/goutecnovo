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
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM network_incidents WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Falha na rede excluída com sucesso.';
        header('Location: /admin/network_incidents.php');
        exit;
    }
}

$page_title = 'Falhas na Rede';
$active = 'network_incidents';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar falhas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $severityFilter = $_GET['severity'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['investigating', 'identified', 'monitoring', 'resolved', 'false_alarm'], true)) {
        $where[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($typeFilter && in_array($typeFilter, ['network', 'server', 'service', 'database', 'other'], true)) {
        $where[] = "i.type = ?";
        $params[] = $typeFilter;
    }
    
    if ($severityFilter && in_array($severityFilter, ['low', 'medium', 'high', 'critical'], true)) {
        $where[] = "i.severity = ?";
        $params[] = $severityFilter;
    }
    
    if ($search !== '') {
        $where[] = "(i.incident_number LIKE ? OR i.title LIKE ? OR i.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT i.*, 
                   c.username as created_by_name,
                   r.username as resolved_by_name
            FROM network_incidents i
            LEFT JOIN admin_users c ON i.created_by = c.id
            LEFT JOIN admin_users r ON i.resolved_by = r.id
            {$whereClause}
            ORDER BY 
                CASE i.severity 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                CASE i.status 
                    WHEN 'investigating' THEN 1 
                    WHEN 'identified' THEN 2 
                    WHEN 'monitoring' THEN 3 
                    WHEN 'resolved' THEN 4 
                    ELSE 5 
                END,
                i.started_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();
} catch (Throwable $e) {
    $incidents = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Falhas na Rede</h1>
        <a href="/admin/network_incident_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Nova Falha
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, título, descrição...">
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label">Tipo</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="network" <?= $typeFilter === 'network' ? 'selected' : '' ?>>Rede</option>
                        <option value="server" <?= $typeFilter === 'server' ? 'selected' : '' ?>>Servidor</option>
                        <option value="service" <?= $typeFilter === 'service' ? 'selected' : '' ?>>Serviço</option>
                        <option value="database" <?= $typeFilter === 'database' ? 'selected' : '' ?>>Banco de Dados</option>
                        <option value="other" <?= $typeFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="severity" class="form-label">Severidade</label>
                    <select class="form-select" id="severity" name="severity">
                        <option value="">Todas</option>
                        <option value="critical" <?= $severityFilter === 'critical' ? 'selected' : '' ?>>Crítica</option>
                        <option value="high" <?= $severityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="medium" <?= $severityFilter === 'medium' ? 'selected' : '' ?>>Média</option>
                        <option value="low" <?= $severityFilter === 'low' ? 'selected' : '' ?>>Baixa</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="investigating" <?= $statusFilter === 'investigating' ? 'selected' : '' ?>>Investigando</option>
                        <option value="identified" <?= $statusFilter === 'identified' ? 'selected' : '' ?>>Identificado</option>
                        <option value="monitoring" <?= $statusFilter === 'monitoring' ? 'selected' : '' ?>>Monitorando</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvido</option>
                        <option value="false_alarm" <?= $statusFilter === 'false_alarm' ? 'selected' : '' ?>>Falso Alarme</option>
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
            <?php if (empty($incidents)): ?>
                <div class="text-center py-5">
                    <i class="las la-network-wired text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma falha registrada ainda.</p>
                    <a href="/admin/network_incident_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Registrar Primeira Falha
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Severidade</th>
                                <th>Status</th>
                                <th>Início</th>
                                <th>Resolução</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $inc): 
                                $isActive = in_array($inc['status'], ['investigating', 'identified', 'monitoring']);
                                $duration = null;
                                if ($inc['resolved_at']) {
                                    $start = strtotime($inc['started_at']);
                                    $end = strtotime($inc['resolved_at']);
                                    $diff = $end - $start;
                                    $hours = floor($diff / 3600);
                                    $minutes = floor(($diff % 3600) / 60);
                                    $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                } elseif ($isActive) {
                                    $start = strtotime($inc['started_at']);
                                    $now = time();
                                    $diff = $now - $start;
                                    $hours = floor($diff / 3600);
                                    $minutes = floor(($diff % 3600) / 60);
                                    $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                }
                            ?>
                                <tr class="<?= $inc['severity'] === 'critical' ? 'table-danger' : ($inc['severity'] === 'high' ? 'table-warning' : '') ?>">
                                    <td>
                                        <strong class="text-primary"><?= h($inc['incident_number']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h($inc['title']) ?></strong>
                                        <?php if ($inc['description']): ?>
                                            <br><small class="text-muted"><?= h(substr($inc['description'], 0, 60)) ?><?= strlen($inc['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'network' => 'Rede',
                                            'server' => 'Servidor',
                                            'service' => 'Serviço',
                                            'database' => 'Banco de Dados',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $typeLabels[$inc['type']] ?? ucfirst($inc['type']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $severityBadges = [
                                            'critical' => 'bg-danger',
                                            'high' => 'bg-warning',
                                            'medium' => 'bg-info',
                                            'low' => 'bg-secondary'
                                        ];
                                        $severityLabels = [
                                            'critical' => 'Crítica',
                                            'high' => 'Alta',
                                            'medium' => 'Média',
                                            'low' => 'Baixa'
                                        ];
                                        ?>
                                        <span class="badge <?= $severityBadges[$inc['severity']] ?? 'bg-secondary' ?>">
                                            <?= $severityLabels[$inc['severity']] ?? ucfirst($inc['severity']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'investigating' => 'bg-warning',
                                            'identified' => 'bg-info',
                                            'monitoring' => 'bg-primary',
                                            'resolved' => 'bg-success',
                                            'false_alarm' => 'bg-secondary'
                                        ];
                                        $statusLabels = [
                                            'investigating' => 'Investigando',
                                            'identified' => 'Identificado',
                                            'monitoring' => 'Monitorando',
                                            'resolved' => 'Resolvido',
                                            'false_alarm' => 'Falso Alarme'
                                        ];
                                        ?>
                                        <span class="badge <?= $statusBadges[$inc['status']] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$inc['status']] ?? ucfirst($inc['status']) ?>
                                        </span>
                                        <?php if ((int)$inc['is_public'] === 1): ?>
                                            <br><small class="badge bg-success">Público</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($inc['started_at'])) ?></small>
                                        <?php if ($duration): ?>
                                            <br><small class="text-muted"><?= $duration ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($inc['resolved_at']): ?>
                                            <small class="text-success"><?= date('d/m/Y H:i', strtotime($inc['resolved_at'])) ?></small>
                                            <?php if ($inc['resolved_by_name']): ?>
                                                <br><small class="text-muted">por <?= h($inc['resolved_by_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/network_incident_edit.php?id=<?= (int)$inc['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta falha?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$inc['id'] ?>">
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

