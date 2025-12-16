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
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && in_array($action, ['open', 'answered', 'customer_reply', 'closed'])) {
        db()->prepare("UPDATE tickets SET status=? WHERE id=?")->execute([$action, $id]);
        header('Location: /admin/tickets.php');
        exit;
    }
    
    if ($id > 0 && in_array($action, ['low', 'medium', 'high', 'urgent'])) {
        db()->prepare("UPDATE tickets SET priority=? WHERE id=?")->execute([$action, $id]);
        header('Location: /admin/tickets.php');
        exit;
    }
}

$page_title = 'Tickets de Suporte';
$active = 'tickets';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar tickets
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $priorityFilter = $_GET['priority'] ?? '';
    $departmentFilter = $_GET['department'] ?? '';
    $clientFilter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['open', 'answered', 'customer_reply', 'closed'], true)) {
        $where[] = "t.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($priorityFilter && in_array($priorityFilter, ['low', 'medium', 'high', 'urgent'], true)) {
        $where[] = "t.priority = ?";
        $params[] = $priorityFilter;
    }
    
    if ($departmentFilter && in_array($departmentFilter, ['support', 'sales', 'billing', 'technical'], true)) {
        $where[] = "t.department = ?";
        $params[] = $departmentFilter;
    }
    
    if ($clientFilter > 0) {
        $where[] = "t.client_id = ?";
        $params[] = $clientFilter;
    }
    
    if ($search !== '') {
        $where[] = "(t.ticket_number LIKE ? OR t.subject LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT t.*, 
                   c.first_name, c.last_name, c.email as client_email,
                   (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) as reply_count
            FROM tickets t
            LEFT JOIN clients c ON t.client_id = c.id
            {$whereClause}
            ORDER BY 
                CASE t.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END ASC,
                t.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (Throwable $e) {
    $tickets = [];
}

// Contar por status
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    $stats = [
        'total' => db()->query("SELECT COUNT(*) as cnt FROM tickets")->fetch()['cnt'],
        'open' => db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status='open'")->fetch()['cnt'],
        'answered' => db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status='answered'")->fetch()['cnt'],
        'customer_reply' => db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status='customer_reply'")->fetch()['cnt'],
        'closed' => db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status='closed'")->fetch()['cnt'],
    ];
} catch (Throwable $e) {
    $stats = ['total' => 0, 'open' => 0, 'answered' => 0, 'customer_reply' => 0, 'closed' => 0];
}

// Buscar clientes para o filtro
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'open':
            return '<span class="badge bg-danger text-white">Aberto</span>';
        case 'answered':
            return '<span class="badge bg-success">Respondido</span>';
        case 'customer_reply':
            return '<span class="badge bg-warning text-dark">Aguardando Resposta</span>';
        case 'closed':
            return '<span class="badge bg-dark text-white">Fechado</span>';
        default:
            return '<span class="badge bg-secondary text-dark">' . h($status) . '</span>';
    }
}

function getPriorityBadge(string $priority): string {
    switch ($priority) {
        case 'urgent':
            return '<span class="badge bg-danger">Urgente</span>';
        case 'high':
            return '<span class="badge bg-warning text-dark">Alta</span>';
        case 'medium':
            return '<span class="badge bg-info text-white">Média</span>';
        case 'low':
            return '<span class="badge bg-dark text-white">Baixa</span>';
        default:
            return '<span class="badge bg-secondary text-dark">' . h($priority) . '</span>';
    }
}

function getDepartmentLabel(string $dept): string {
    $labels = [
        'support' => 'Suporte',
        'sales' => 'Vendas',
        'billing' => 'Faturamento',
        'technical' => 'Técnico',
    ];
    return $labels[$dept] ?? $dept;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Tickets de Suporte</h1>
        <a href="/admin/ticket_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Ticket
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total</h6>
                    <h4 class="mb-0"><?= number_format((int)$stats['total']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Abertos</h6>
                    <h4 class="mb-0 text-danger"><?= number_format((int)$stats['open']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Respondidos</h6>
                    <h4 class="mb-0 text-success"><?= number_format((int)$stats['answered']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Aguardando</h6>
                    <h4 class="mb-0 text-warning"><?= number_format((int)$stats['customer_reply']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Fechados</h6>
                    <h4 class="mb-0 text-secondary"><?= number_format((int)$stats['closed']) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, assunto, cliente...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aberto</option>
                        <option value="answered" <?= $statusFilter === 'answered' ? 'selected' : '' ?>>Respondido</option>
                        <option value="customer_reply" <?= $statusFilter === 'customer_reply' ? 'selected' : '' ?>>Aguardando Resposta</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Fechado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="priority" class="form-label">Prioridade</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">Todas</option>
                        <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                        <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Média</option>
                        <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Baixa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="department" class="form-label">Departamento</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">Todos</option>
                        <option value="support" <?= $departmentFilter === 'support' ? 'selected' : '' ?>>Suporte</option>
                        <option value="sales" <?= $departmentFilter === 'sales' ? 'selected' : '' ?>>Vendas</option>
                        <option value="billing" <?= $departmentFilter === 'billing' ? 'selected' : '' ?>>Faturamento</option>
                        <option value="technical" <?= $departmentFilter === 'technical' ? 'selected' : '' ?>>Técnico</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="client_id" class="form-label">Cliente</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>" <?= $clientFilter === (int)$client['id'] ? 'selected' : '' ?>>
                                <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                    <a href="/admin/tickets.php" class="btn btn-secondary">
                        <i class="las la-times me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de tickets -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Assunto</th>
                            <th>Departamento</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Respostas</th>
                            <th>Data</th>
                            <th style="width: 200px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Nenhum ticket encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><strong><?= h($ticket['ticket_number']) ?></strong></td>
                                    <td>
                                        <?= h($ticket['first_name'] . ' ' . $ticket['last_name']) ?>
                                        <br><small class="text-muted"><?= h($ticket['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <a href="/admin/ticket_view.php?id=<?= (int)$ticket['id'] ?>" class="text-decoration-none">
                                            <?= h($ticket['subject']) ?>
                                        </a>
                                    </td>
                                    <td><?= getDepartmentLabel($ticket['department']) ?></td>
                                    <td><?= getPriorityBadge($ticket['priority']) ?></td>
                                    <td><?= getStatusBadge($ticket['status']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= (int)$ticket['reply_count'] ?></span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($ticket['created_at'])) ?>
                                        <br><small class="text-muted"><?= date('H:i', strtotime($ticket['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/ticket_view.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-sm btn-primary" title="Visualizar">
                                                <i class="las la-eye"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="las la-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><h6 class="dropdown-header">Status</h6></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$ticket['id'] ?>, 'open')">Aberto</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$ticket['id'] ?>, 'answered')">Respondido</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$ticket['id'] ?>, 'customer_reply')">Aguardando Resposta</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= (int)$ticket['id'] ?>, 'closed')">Fechado</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><h6 class="dropdown-header">Prioridade</h6></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changePriority(<?= (int)$ticket['id'] ?>, 'urgent')">Urgente</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changePriority(<?= (int)$ticket['id'] ?>, 'high')">Alta</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changePriority(<?= (int)$ticket['id'] ?>, 'medium')">Média</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="changePriority(<?= (int)$ticket['id'] ?>, 'low')">Baixa</a></li>
                                                </ul>
                                            </div>
                                        </div>
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

<form id="statusForm" method="POST" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="statusAction">
    <input type="hidden" name="id" id="statusId">
</form>

<form id="priorityForm" method="POST" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="priorityAction">
    <input type="hidden" name="id" id="priorityId">
</form>

<script>
function changeStatus(id, status) {
    if (confirm('Tem certeza que deseja alterar o status deste ticket?')) {
        document.getElementById('statusId').value = id;
        document.getElementById('statusAction').value = status;
        document.getElementById('statusForm').submit();
    }
}

function changePriority(id, priority) {
    if (confirm('Tem certeza que deseja alterar a prioridade deste ticket?')) {
        document.getElementById('priorityId').value = id;
        document.getElementById('priorityAction').value = priority;
        document.getElementById('priorityForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
