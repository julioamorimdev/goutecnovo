<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Relatórios Gerais';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Período
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Estatísticas gerais
    $stats = [
        'total_clients' => 0,
        'active_clients' => 0,
        'total_orders' => 0,
        'active_orders' => 0,
        'total_invoices' => 0,
        'paid_invoices' => 0,
        'total_revenue' => 0.00,
        'pending_revenue' => 0.00,
        'total_tickets' => 0,
        'open_tickets' => 0,
    ];
    
    // Clientes
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM clients");
    $stats['total_clients'] = (int)$stmt->fetch()['cnt'];
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM clients WHERE status='active'");
    $stats['active_clients'] = (int)$stmt->fetch()['cnt'];
    
    // Pedidos
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM orders");
    $stats['total_orders'] = (int)$stmt->fetch()['cnt'];
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM orders WHERE status='active'");
    $stats['active_orders'] = (int)$stmt->fetch()['cnt'];
    
    // Faturas
    $stmt = db()->query("SELECT COUNT(*) as cnt, SUM(total) as total FROM invoices");
    $invoiceData = $stmt->fetch();
    $stats['total_invoices'] = (int)$invoiceData['cnt'];
    $stmt = db()->query("SELECT COUNT(*) as cnt, SUM(total) as total FROM invoices WHERE status='paid'");
    $paidData = $stmt->fetch();
    $stats['paid_invoices'] = (int)$paidData['cnt'];
    $stats['total_revenue'] = (float)($paidData['total'] ?? 0);
    $stmt = db()->query("SELECT SUM(total) as total FROM invoices WHERE status='unpaid'");
    $pendingData = $stmt->fetch();
    $stats['pending_revenue'] = (float)($pendingData['total'] ?? 0);
    
    // Tickets
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM tickets");
    $stats['total_tickets'] = (int)$stmt->fetch()['cnt'];
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE status IN ('open', 'answered', 'customer_reply')");
    $stats['open_tickets'] = (int)$stmt->fetch()['cnt'];
    
    // Crescimento de clientes (últimos 6 meses)
    $clientGrowth = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $clientGrowth[$month] = (int)$stmt->fetch()['cnt'];
    }
    
    // Receita por mês (últimos 6 meses)
    $revenueByMonth = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND DATE_FORMAT(paid_date, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $revenueByMonth[$month] = (float)($stmt->fetch()['total'] ?? 0);
    }
    
} catch (Throwable $e) {
    $stats = array_fill_keys(['total_clients', 'active_clients', 'total_orders', 'active_orders', 'total_invoices', 'paid_invoices', 'total_revenue', 'pending_revenue', 'total_tickets', 'open_tickets'], 0);
    $clientGrowth = [];
    $revenueByMonth = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios Gerais</h1>
        <a href="/admin/reports.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total de Clientes</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_clients']) ?></h3>
                            <small class="text-success"><?= number_format($stats['active_clients']) ?> ativos</small>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="las la-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Receita Total</h6>
                            <h3 class="mb-0 text-success">R$ <?= number_format($stats['total_revenue'], 2, ',', '.') ?></h3>
                            <small class="text-warning">R$ <?= number_format($stats['pending_revenue'], 2, ',', '.') ?> pendente</small>
                        </div>
                        <div class="text-success" style="font-size: 2.5rem;">
                            <i class="las la-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pedidos</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_orders']) ?></h3>
                            <small class="text-success"><?= number_format($stats['active_orders']) ?> ativos</small>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="las la-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Tickets</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_tickets']) ?></h3>
                            <small class="text-warning"><?= number_format($stats['open_tickets']) ?> abertos</small>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="las la-ticket-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Crescimento de Clientes (Últimos 6 Meses)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($clientGrowth)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mês</th>
                                        <th>Novos Clientes</th>
                                        <th>Progresso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxClients = max($clientGrowth) ?: 1;
                                    foreach ($clientGrowth as $month => $count): 
                                    ?>
                                        <tr>
                                            <td><?= date('m/Y', strtotime($month . '-01')) ?></td>
                                            <td><strong><?= $count ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= ($count / $maxClients) * 100 ?>%">
                                                        <?= $count ?>
                                                    </div>
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

        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Receita por Mês (Últimos 6 Meses)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($revenueByMonth)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mês</th>
                                        <th>Receita</th>
                                        <th>Progresso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxRevenue = max($revenueByMonth) ?: 1;
                                    foreach ($revenueByMonth as $month => $revenue): 
                                    ?>
                                        <tr>
                                            <td><?= date('m/Y', strtotime($month . '-01')) ?></td>
                                            <td><strong class="text-success">R$ <?= number_format($revenue, 2, ',', '.') ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($revenue / $maxRevenue) * 100 ?>%">
                                                        R$ <?= number_format($revenue, 0, ',', '.') ?>
                                                    </div>
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
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

