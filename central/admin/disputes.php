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
    
    if ($id > 0 && in_array($action, ['update_status', 'update_priority'])) {
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        
        if ($action === 'update_status') {
            $newStatus = trim((string)($_POST['status'] ?? ''));
            if (in_array($newStatus, ['open', 'under_review', 'resolved', 'won', 'lost', 'withdrawn'], true)) {
                $resolvedAt = in_array($newStatus, ['resolved', 'won', 'lost', 'withdrawn'], true) ? 'NOW()' : 'NULL';
                $resolvedBy = in_array($newStatus, ['resolved', 'won', 'lost', 'withdrawn'], true) ? $adminId : 'NULL';
                
                if ($resolvedAt === 'NOW()') {
                    db()->prepare("UPDATE disputes SET status=?, resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$newStatus, $adminId, $id]);
                } else {
                    db()->prepare("UPDATE disputes SET status=?, resolved_by=NULL, resolved_at=NULL WHERE id=?")->execute([$newStatus, $id]);
                }
                $_SESSION['success'] = 'Status da disputa atualizado com sucesso.';
            }
        } elseif ($action === 'update_priority') {
            $newPriority = trim((string)($_POST['priority'] ?? ''));
            if (in_array($newPriority, ['low', 'medium', 'high', 'urgent'], true)) {
                db()->prepare("UPDATE disputes SET priority=? WHERE id=?")->execute([$newPriority, $id]);
                $_SESSION['success'] = 'Prioridade da disputa atualizada com sucesso.';
            }
        }
        
        header('Location: /admin/disputes.php');
        exit;
    }
}

$page_title = 'Disputas';
$active = 'disputes';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar disputas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $priorityFilter = $_GET['priority'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['open', 'under_review', 'resolved', 'won', 'lost', 'withdrawn'], true)) {
        $where[] = "d.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($typeFilter && in_array($typeFilter, ['chargeback', 'refund_request', 'billing_error', 'service_issue', 'other'], true)) {
        $where[] = "d.type = ?";
        $params[] = $typeFilter;
    }
    
    if ($priorityFilter && in_array($priorityFilter, ['low', 'medium', 'high', 'urgent'], true)) {
        $where[] = "d.priority = ?";
        $params[] = $priorityFilter;
    }
    
    if ($search !== '') {
        $where[] = "(d.dispute_number LIKE ? OR d.reason LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR i.invoice_number LIKE ? OR o.order_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT d.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name,
                   i.invoice_number,
                   o.order_number,
                   a.username as resolved_by_name
            FROM disputes d
            LEFT JOIN clients c ON d.client_id = c.id
            LEFT JOIN invoices i ON d.invoice_id = i.id
            LEFT JOIN orders o ON d.order_id = o.id
            LEFT JOIN admin_users a ON d.resolved_by = a.id
            {$whereClause}
            ORDER BY 
                CASE d.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                CASE d.status 
                    WHEN 'open' THEN 1 
                    WHEN 'under_review' THEN 2 
                    ELSE 3 
                END,
                d.deadline_date ASC,
                d.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $disputes = $stmt->fetchAll();
} catch (Throwable $e) {
    $disputes = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Disputas</h1>
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, cliente, fatura...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aberta</option>
                        <option value="under_review" <?= $statusFilter === 'under_review' ? 'selected' : '' ?>>Em Análise</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvida</option>
                        <option value="won" <?= $statusFilter === 'won' ? 'selected' : '' ?>>Ganha</option>
                        <option value="lost" <?= $statusFilter === 'lost' ? 'selected' : '' ?>>Perdida</option>
                        <option value="withdrawn" <?= $statusFilter === 'withdrawn' ? 'selected' : '' ?>>Retirada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label">Tipo</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="chargeback" <?= $typeFilter === 'chargeback' ? 'selected' : '' ?>>Chargeback</option>
                        <option value="refund_request" <?= $typeFilter === 'refund_request' ? 'selected' : '' ?>>Solicitação de Reembolso</option>
                        <option value="billing_error" <?= $typeFilter === 'billing_error' ? 'selected' : '' ?>>Erro de Cobrança</option>
                        <option value="service_issue" <?= $typeFilter === 'service_issue' ? 'selected' : '' ?>>Problema no Serviço</option>
                        <option value="other" <?= $typeFilter === 'other' ? 'selected' : '' ?>>Outros</option>
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
            <?php if (empty($disputes)): ?>
                <div class="text-center py-5">
                    <i class="las la-gavel text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma disputa encontrada.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Valor</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Prazo</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputes as $dispute): 
                                $isUrgent = $dispute['deadline_date'] && strtotime($dispute['deadline_date']) <= strtotime('+3 days') && in_array($dispute['status'], ['open', 'under_review']);
                                $isOverdue = $dispute['deadline_date'] && strtotime($dispute['deadline_date']) < time() && in_array($dispute['status'], ['open', 'under_review']);
                            ?>
                                <tr class="<?= $isOverdue ? 'table-danger' : ($isUrgent ? 'table-warning' : '') ?>">
                                    <td>
                                        <strong class="text-primary"><?= h($dispute['dispute_number']) ?></strong>
                                        <?php if ($dispute['invoice_number']): ?>
                                            <br><small class="text-muted">Fatura: <?= h($dispute['invoice_number']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($dispute['order_number']): ?>
                                            <br><small class="text-muted">Pedido: <?= h($dispute['order_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($dispute['first_name'] . ' ' . $dispute['last_name']) ?></strong>
                                        <?php if ($dispute['company_name']): ?>
                                            <br><small class="text-muted"><?= h($dispute['company_name']) ?></small>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= h($dispute['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'chargeback' => 'Chargeback',
                                            'refund_request' => 'Reembolso',
                                            'billing_error' => 'Erro de Cobrança',
                                            'service_issue' => 'Problema no Serviço',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $typeLabels[$dispute['type']] ?? ucfirst($dispute['type']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($dispute['reason']) ?></strong>
                                        <?php if ($dispute['description']): ?>
                                            <br><small class="text-muted"><?= h(substr($dispute['description'], 0, 50)) ?><?= strlen($dispute['description']) > 50 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-danger">R$ <?= number_format((float)$dispute['amount'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityBadges = [
                                            'urgent' => 'bg-danger',
                                            'high' => 'bg-warning',
                                            'medium' => 'bg-info',
                                            'low' => 'bg-secondary'
                                        ];
                                        $priorityLabels = [
                                            'urgent' => 'Urgente',
                                            'high' => 'Alta',
                                            'medium' => 'Média',
                                            'low' => 'Baixa'
                                        ];
                                        $priority = $dispute['priority'] ?? 'medium';
                                        ?>
                                        <span class="badge <?= $priorityBadges[$priority] ?? 'bg-secondary' ?>">
                                            <?= $priorityLabels[$priority] ?? ucfirst($priority) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'open' => 'bg-warning',
                                            'under_review' => 'bg-info',
                                            'resolved' => 'bg-success',
                                            'won' => 'bg-success',
                                            'lost' => 'bg-danger',
                                            'withdrawn' => 'bg-secondary'
                                        ];
                                        $statusLabels = [
                                            'open' => 'Aberta',
                                            'under_review' => 'Em Análise',
                                            'resolved' => 'Resolvida',
                                            'won' => 'Ganha',
                                            'lost' => 'Perdida',
                                            'withdrawn' => 'Retirada'
                                        ];
                                        $status = $dispute['status'] ?? 'open';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($dispute['deadline_date']): ?>
                                            <small class="<?= $isOverdue ? 'text-danger fw-bold' : ($isUrgent ? 'text-warning' : '') ?>">
                                                <?= date('d/m/Y', strtotime($dispute['deadline_date'])) ?>
                                                <?php if ($isOverdue): ?>
                                                    <br><span class="badge bg-danger">Atrasado</span>
                                                <?php elseif ($isUrgent): ?>
                                                    <br><span class="badge bg-warning">Urgente</span>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/dispute_view.php?id=<?= (int)$dispute['id'] ?>" class="btn btn-sm btn-primary" title="Visualizar">
                                                <i class="las la-eye"></i>
                                            </a>
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

