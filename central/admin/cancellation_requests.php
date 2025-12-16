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
    
    if ($id > 0 && in_array($action, ['approve', 'reject', 'complete'])) {
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
        $refundAmount = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : null;
        
        db()->beginTransaction();
        try {
            if ($action === 'approve') {
                $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
                if ($effectiveDate === '') {
                    $effectiveDate = date('Y-m-d');
                }
                
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='approved', effective_date=?, processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$effectiveDate, $adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                
                // Se houver pedido vinculado, cancelar o pedido
                $stmt = db()->prepare("SELECT order_id FROM cancellation_requests WHERE id=?");
                $stmt->execute([$id]);
                $request = $stmt->fetch();
                if ($request && $request['order_id']) {
                    db()->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([(int)$request['order_id']]);
                }
                
                $_SESSION['success'] = 'Solicitação de cancelamento aprovada com sucesso.';
            } elseif ($action === 'reject') {
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='rejected', processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                $_SESSION['success'] = 'Solicitação de cancelamento rejeitada.';
            } elseif ($action === 'complete') {
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='completed', processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                
                // Processar reembolso se solicitado
                if ($refundAmount !== null && $refundAmount > 0) {
                    $stmt = db()->prepare("UPDATE cancellation_requests SET refund_requested=1, refund_amount=?, refund_status='approved' WHERE id=?");
                    $stmt->execute([$refundAmount, $id]);
                }
                
                $_SESSION['success'] = 'Cancelamento concluído com sucesso.';
            }
            
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            $_SESSION['error'] = 'Erro ao processar solicitação: ' . $e->getMessage();
        }
        
        header('Location: /admin/cancellation_requests.php');
        exit;
    }
}

$page_title = 'Solicitações de Cancelamento';
$active = 'cancellation_requests';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar solicitações
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $typeFilter = $_GET['type'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled', 'completed'], true)) {
        $where[] = "cr.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($typeFilter && in_array($typeFilter, ['service', 'order', 'domain', 'subscription'], true)) {
        $where[] = "cr.type = ?";
        $params[] = $typeFilter;
    }
    
    if ($search !== '') {
        $where[] = "(cr.request_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR o.order_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT cr.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name,
                   o.order_number, o.amount as order_amount,
                   a.username as processed_by_name
            FROM cancellation_requests cr
            LEFT JOIN clients c ON cr.client_id = c.id
            LEFT JOIN orders o ON cr.order_id = o.id
            LEFT JOIN admin_users a ON cr.processed_by = a.id
            {$whereClause}
            ORDER BY 
                CASE cr.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'rejected' THEN 3 
                    ELSE 4 
                END,
                cr.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (Throwable $e) {
    $requests = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Solicitações de Cancelamento</h1>
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
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, cliente, pedido...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Tipo</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="service" <?= $typeFilter === 'service' ? 'selected' : '' ?>>Serviço</option>
                        <option value="order" <?= $typeFilter === 'order' ? 'selected' : '' ?>>Pedido</option>
                        <option value="domain" <?= $typeFilter === 'domain' ? 'selected' : '' ?>>Domínio</option>
                        <option value="subscription" <?= $typeFilter === 'subscription' ? 'selected' : '' ?>>Assinatura</option>
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
            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="las la-times-circle text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma solicitação de cancelamento encontrada.</p>
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
                                <th>Data Solicitada</th>
                                <th>Reembolso</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): 
                                $isPending = $request['status'] === 'pending';
                                $isUrgent = $isPending && strtotime($request['requested_date']) <= strtotime('+7 days');
                            ?>
                                <tr class="<?= $isUrgent ? 'table-warning' : '' ?>">
                                    <td>
                                        <strong class="text-primary"><?= h($request['request_number']) ?></strong>
                                        <?php if ($request['order_number']): ?>
                                            <br><small class="text-muted">Pedido: <?= h($request['order_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($request['first_name'] . ' ' . $request['last_name']) ?></strong>
                                        <?php if ($request['company_name']): ?>
                                            <br><small class="text-muted"><?= h($request['company_name']) ?></small>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= h($request['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'service' => 'Serviço',
                                            'order' => 'Pedido',
                                            'domain' => 'Domínio',
                                            'subscription' => 'Assinatura'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $typeLabels[$request['type']] ?? ucfirst($request['type']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($request['reason']) ?></strong>
                                        <?php if ($request['reason_details']): ?>
                                            <br><small class="text-muted"><?= h(substr($request['reason_details'], 0, 50)) ?><?= strlen($request['reason_details']) > 50 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($request['requested_date'])) ?></small>
                                        <?php if ($isUrgent): ?>
                                            <br><small class="text-warning"><i class="las la-exclamation-triangle"></i> Urgente</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$request['refund_requested'] === 1): ?>
                                            <span class="badge bg-info">Solicitado</span>
                                            <?php if ($request['refund_amount']): ?>
                                                <br><small class="text-muted">R$ <?= number_format((float)$request['refund_amount'], 2, ',', '.') ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-info',
                                            'rejected' => 'bg-danger',
                                            'cancelled' => 'bg-secondary',
                                            'completed' => 'bg-success'
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pendente',
                                            'approved' => 'Aprovado',
                                            'rejected' => 'Rejeitado',
                                            'cancelled' => 'Cancelado',
                                            'completed' => 'Concluído'
                                        ];
                                        $status = $request['status'] ?? 'pending';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                        <?php if ($request['processed_by_name']): ?>
                                            <br><small class="text-muted">Por: <?= h($request['processed_by_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/cancellation_request_view.php?id=<?= (int)$request['id'] ?>" class="btn btn-sm btn-primary" title="Visualizar">
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

