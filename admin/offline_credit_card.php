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
    
    if ($id > 0 && $action === 'capture') {
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        db()->prepare("UPDATE offline_credit_card_processings SET status='captured', capture_date=NOW(), processed_by=? WHERE id=?")->execute([$adminId, $id]);
        
        // Atualizar fatura se vinculada
        $stmt = db()->prepare("SELECT invoice_id FROM offline_credit_card_processings WHERE id=?");
        $stmt->execute([$id]);
        $processing = $stmt->fetch();
        if ($processing && $processing['invoice_id']) {
            db()->prepare("UPDATE invoices SET status='paid', paid_date=CURDATE(), payment_method='Cartão de Crédito Off-line' WHERE id=?")->execute([(int)$processing['invoice_id']]);
        }
        
        $_SESSION['success'] = 'Pagamento capturado com sucesso.';
        header('Location: /admin/offline_credit_card.php');
        exit;
    }
    
    if ($id > 0 && $action === 'decline') {
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));
        db()->prepare("UPDATE offline_credit_card_processings SET status='declined', processed_by=?, notes=? WHERE id=?")->execute([$adminId, $notes !== '' ? $notes : null, $id]);
        $_SESSION['success'] = 'Pagamento recusado.';
        header('Location: /admin/offline_credit_card.php');
        exit;
    }
    
    if ($id > 0 && $action === 'cancel') {
        $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
        db()->prepare("UPDATE offline_credit_card_processings SET status='cancelled', processed_by=? WHERE id=?")->execute([$adminId, $id]);
        $_SESSION['success'] = 'Processamento cancelado.';
        header('Location: /admin/offline_credit_card.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM offline_credit_card_processings WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Processamento excluído com sucesso.';
        header('Location: /admin/offline_credit_card.php');
        exit;
    }
}

$page_title = 'Processamento de Cartão Off-line';
$active = 'offline_credit_card';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar processamentos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $methodFilter = $_GET['method'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['pending', 'authorized', 'captured', 'declined', 'cancelled', 'refunded'], true)) {
        $where[] = "p.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($methodFilter && in_array($methodFilter, ['manual', 'terminal', 'phone', 'email'], true)) {
        $where[] = "p.processing_method = ?";
        $params[] = $methodFilter;
    }
    
    if ($search !== '') {
        $where[] = "(p.processing_number LIKE ? OR p.authorization_code LIKE ? OR p.transaction_id LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR i.invoice_number LIKE ? OR o.order_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT p.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name,
                   i.invoice_number,
                   o.order_number,
                   a.username as processed_by_name
            FROM offline_credit_card_processings p
            LEFT JOIN clients c ON p.client_id = c.id
            LEFT JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN orders o ON p.order_id = o.id
            LEFT JOIN admin_users a ON p.processed_by = a.id
            {$whereClause}
            ORDER BY 
                CASE p.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'authorized' THEN 2 
                    WHEN 'captured' THEN 3 
                    ELSE 4 
                END,
                p.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $processings = $stmt->fetchAll();
} catch (Throwable $e) {
    $processings = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Processamento de Cartão de Crédito Off-line</h1>
        <a href="/admin/offline_credit_card_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Processamento
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, código, cliente...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="authorized" <?= $statusFilter === 'authorized' ? 'selected' : '' ?>>Autorizado</option>
                        <option value="captured" <?= $statusFilter === 'captured' ? 'selected' : '' ?>>Capturado</option>
                        <option value="declined" <?= $statusFilter === 'declined' ? 'selected' : '' ?>>Recusado</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="method" class="form-label">Método</label>
                    <select class="form-select" id="method" name="method">
                        <option value="">Todos</option>
                        <option value="manual" <?= $methodFilter === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="terminal" <?= $methodFilter === 'terminal' ? 'selected' : '' ?>>Terminal</option>
                        <option value="phone" <?= $methodFilter === 'phone' ? 'selected' : '' ?>>Telefone</option>
                        <option value="email" <?= $methodFilter === 'email' ? 'selected' : '' ?>>Email</option>
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
            <?php if (empty($processings)): ?>
                <div class="text-center py-5">
                    <i class="las la-credit-card text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum processamento encontrado.</p>
                    <a href="/admin/offline_credit_card_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Processamento
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Cartão</th>
                                <th>Valor</th>
                                <th>Parcelas</th>
                                <th>Método</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th style="width: 200px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processings as $proc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= h($proc['processing_number']) ?></strong>
                                        <?php if ($proc['invoice_number']): ?>
                                            <br><small class="text-muted">Fatura: <?= h($proc['invoice_number']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($proc['order_number']): ?>
                                            <br><small class="text-muted">Pedido: <?= h($proc['order_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($proc['first_name'] . ' ' . $proc['last_name']) ?></strong>
                                        <br><small class="text-muted"><?= h($proc['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($proc['card_type']): ?>
                                            <?php
                                            $cardTypes = [
                                                'visa' => 'Visa',
                                                'mastercard' => 'Mastercard',
                                                'amex' => 'American Express',
                                                'elo' => 'Elo',
                                                'diners' => 'Diners',
                                                'other' => 'Outro'
                                            ];
                                            ?>
                                            <span class="badge bg-primary"><?= $cardTypes[$proc['card_type']] ?? ucfirst($proc['card_type']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($proc['card_last_four']): ?>
                                            <br><small class="text-muted">**** <?= h($proc['card_last_four']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($proc['card_holder_name']): ?>
                                            <br><small class="text-muted"><?= h($proc['card_holder_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$proc['amount'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?= (int)$proc['installments'] ?>x
                                    </td>
                                    <td>
                                        <?php
                                        $methodLabels = [
                                            'manual' => 'Manual',
                                            'terminal' => 'Terminal',
                                            'phone' => 'Telefone',
                                            'email' => 'Email'
                                        ];
                                        ?>
                                        <small><?= $methodLabels[$proc['processing_method']] ?? ucfirst($proc['processing_method']) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'bg-warning',
                                            'authorized' => 'bg-info',
                                            'captured' => 'bg-success',
                                            'declined' => 'bg-danger',
                                            'cancelled' => 'bg-secondary',
                                            'refunded' => 'bg-warning'
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pendente',
                                            'authorized' => 'Autorizado',
                                            'captured' => 'Capturado',
                                            'declined' => 'Recusado',
                                            'cancelled' => 'Cancelado',
                                            'refunded' => 'Reembolsado'
                                        ];
                                        $status = $proc['status'] ?? 'pending';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                        <?php if ($proc['authorization_code']): ?>
                                            <br><small class="text-muted">Auth: <?= h($proc['authorization_code']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($proc['created_at'])) ?></small>
                                        <?php if ($proc['capture_date']): ?>
                                            <br><small class="text-success">Capturado: <?= date('d/m/Y H:i', strtotime($proc['capture_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/offline_credit_card_edit.php?id=<?= (int)$proc['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <?php if ($proc['status'] === 'authorized'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Capturar este pagamento?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="capture">
                                                    <input type="hidden" name="id" value="<?= (int)$proc['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Capturar">
                                                        <i class="las la-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($proc['status'], ['pending', 'authorized'])): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Recusar este pagamento?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="decline">
                                                    <input type="hidden" name="id" value="<?= (int)$proc['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Recusar">
                                                        <i class="las la-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

