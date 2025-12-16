<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$affiliateId = isset($_GET['affiliate_id']) ? (int)$_GET['affiliate_id'] : 0;

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    if ($id > 0 && $action === 'approve') {
        $stmt = db()->prepare("UPDATE affiliate_commissions SET status='approved' WHERE id=?");
        $stmt->execute([$id]);
        header('Location: /admin/affiliate_commissions.php?affiliate_id=' . $affiliateId);
        exit;
    }
    
    if ($id > 0 && $action === 'pay') {
        db()->beginTransaction();
        try {
            // Atualizar comissão para pago
            $stmt = db()->prepare("SELECT affiliate_id, amount FROM affiliate_commissions WHERE id=?");
            $stmt->execute([$id]);
            $commission = $stmt->fetch();
            
            if ($commission) {
                $stmt = db()->prepare("UPDATE affiliate_commissions SET status='paid', payment_date=CURDATE() WHERE id=?");
                $stmt->execute([$id]);
                
                // Atualizar totais do afiliado
                $stmt = db()->prepare("UPDATE affiliates SET paid_earnings = paid_earnings + ?, pending_earnings = pending_earnings - ? WHERE id=?");
                $stmt->execute([$commission['amount'], $commission['amount'], $commission['affiliate_id']]);
            }
            
            db()->commit();
            $_SESSION['success'] = 'Comissão marcada como paga com sucesso.';
        } catch (Throwable $e) {
            db()->rollBack();
            $_SESSION['error'] = 'Erro ao processar pagamento: ' . $e->getMessage();
        }
        header('Location: /admin/affiliate_commissions.php?affiliate_id=' . $affiliateId);
        exit;
    }
    
    if ($id > 0 && $action === 'cancel') {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare("SELECT affiliate_id, amount FROM affiliate_commissions WHERE id=?");
            $stmt->execute([$id]);
            $commission = $stmt->fetch();
            
            if ($commission) {
                $stmt = db()->prepare("UPDATE affiliate_commissions SET status='cancelled' WHERE id=?");
                $stmt->execute([$id]);
                
                // Atualizar totais do afiliado
                $stmt = db()->prepare("UPDATE affiliates SET total_earnings = total_earnings - ?, pending_earnings = pending_earnings - ? WHERE id=?");
                $stmt->execute([$commission['amount'], $commission['amount'], $commission['affiliate_id']]);
            }
            
            db()->commit();
            $_SESSION['success'] = 'Comissão cancelada com sucesso.';
        } catch (Throwable $e) {
            db()->rollBack();
            $_SESSION['error'] = 'Erro ao cancelar comissão: ' . $e->getMessage();
        }
        header('Location: /admin/affiliate_commissions.php?affiliate_id=' . $affiliateId);
        exit;
    }
}

$page_title = 'Comissões de Afiliados';
$active = 'affiliates';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar afiliado se especificado
$affiliate = null;
if ($affiliateId > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM affiliates WHERE id=?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch();
    } catch (Throwable $e) {
        $affiliate = null;
    }
}

// Buscar comissões
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($affiliateId > 0) {
        $where[] = "ac.affiliate_id = ?";
        $params[] = $affiliateId;
    }
    
    if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'paid', 'cancelled'], true)) {
        $where[] = "ac.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT ac.*, 
                   a.code as affiliate_code, a.first_name, a.last_name,
                   o.order_number,
                   c.first_name as client_first_name, c.last_name as client_last_name
            FROM affiliate_commissions ac
            LEFT JOIN affiliates a ON ac.affiliate_id = a.id
            LEFT JOIN orders o ON ac.order_id = o.id
            LEFT JOIN clients c ON ac.client_id = c.id
            {$whereClause}
            ORDER BY ac.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $commissions = $stmt->fetchAll();
} catch (Throwable $e) {
    $commissions = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            Comissões de Afiliados
            <?php if ($affiliate): ?>
                - <?= h($affiliate['first_name'] . ' ' . $affiliate['last_name']) ?> (<?= h($affiliate['code']) ?>)
            <?php endif; ?>
        </h1>
        <div>
            <?php if ($affiliate): ?>
                <a href="/admin/affiliates.php" class="btn btn-secondary">
                    <i class="las la-arrow-left me-1"></i> Voltar para Afiliados
                </a>
            <?php else: ?>
                <a href="/admin/affiliates.php" class="btn btn-secondary">
                    <i class="las la-arrow-left me-1"></i> Afiliados
                </a>
            <?php endif; ?>
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
                <?php if ($affiliateId > 0): ?>
                    <input type="hidden" name="affiliate_id" value="<?= $affiliateId ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pago</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
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
            <?php if (empty($commissions)): ?>
                <div class="text-center py-5">
                    <i class="las la-money-bill text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhuma comissão encontrada.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Afiliado</th>
                                <th>Descrição</th>
                                <th>Cliente/Pedido</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th style="width: 200px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissions as $commission): ?>
                                <tr>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($commission['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if (!$affiliate): ?>
                                            <strong><?= h($commission['first_name'] . ' ' . $commission['last_name']) ?></strong>
                                            <br><small class="text-muted"><?= h($commission['affiliate_code']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= h($commission['description']) ?>
                                    </td>
                                    <td>
                                        <?php if ($commission['order_number']): ?>
                                            <small>Pedido: <strong><?= h($commission['order_number']) ?></strong></small>
                                        <?php endif; ?>
                                        <?php if ($commission['client_first_name']): ?>
                                            <br><small>Cliente: <?= h($commission['client_first_name'] . ' ' . $commission['client_last_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$commission['amount'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-info',
                                            'paid' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pendente',
                                            'approved' => 'Aprovado',
                                            'paid' => 'Pago',
                                            'cancelled' => 'Cancelado'
                                        ];
                                        $status = $commission['status'] ?? 'pending';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                        <?php if ($status === 'paid' && $commission['payment_date']): ?>
                                            <br><small class="text-muted">Pago em: <?= date('d/m/Y', strtotime($commission['payment_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($status === 'pending'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Aprovar esta comissão?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?= (int)$commission['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-info" title="Aprovar">
                                                        <i class="las la-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($status === 'approved'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Marcar esta comissão como paga?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="pay">
                                                    <input type="hidden" name="id" value="<?= (int)$commission['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Marcar como Pago">
                                                        <i class="las la-money-bill"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($status, ['pending', 'approved'])): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja cancelar esta comissão?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="id" value="<?= (int)$commission['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar">
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

