<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Relatórios de Transações';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Estatísticas de faturas
    $stmt = db()->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) as unpaid,
        SUM(CASE WHEN status='paid' THEN total ELSE 0 END) as total_paid,
        SUM(CASE WHEN status='unpaid' THEN total ELSE 0 END) as total_unpaid
        FROM invoices WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $invoiceStats = $stmt->fetch();
    
    // Estatísticas de gateway
    $stmt = db()->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status='success' THEN amount ELSE 0 END) as total_success,
        gateway,
        COUNT(*) as count
        FROM gateway_transactions 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY gateway");
    $stmt->execute([$dateFrom, $dateTo]);
    $gatewayStats = $stmt->fetchAll();
    
    // Faturas por status
    $stmt = db()->prepare("SELECT status, COUNT(*) as cnt, SUM(total) as total FROM invoices WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$dateFrom, $dateTo]);
    $invoicesByStatus = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $invoiceStats = ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'total_paid' => 0, 'total_unpaid' => 0];
    $gatewayStats = [];
    $invoicesByStatus = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios de Transações</h1>
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

    <!-- Estatísticas de Faturas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total de Faturas</h6>
                    <h3 class="mb-0"><?= number_format((int)$invoiceStats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Faturas Pagas</h6>
                    <h3 class="mb-0 text-success"><?= number_format((int)$invoiceStats['paid']) ?></h3>
                    <small>R$ <?= number_format((float)$invoiceStats['total_paid'], 2, ',', '.') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Faturas Pendentes</h6>
                    <h3 class="mb-0 text-warning"><?= number_format((int)$invoiceStats['unpaid']) ?></h3>
                    <small>R$ <?= number_format((float)$invoiceStats['total_unpaid'], 2, ',', '.') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Taxa de Pagamento</h6>
                    <h3 class="mb-0 text-info">
                        <?= (int)$invoiceStats['total'] > 0 ? number_format(((int)$invoiceStats['paid'] / (int)$invoiceStats['total']) * 100), 1) : 0 ?>%
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Faturas por Status -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Faturas por Status</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Quantidade</th>
                            <th>Valor Total</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalInvoices = (int)$invoiceStats['total'];
                        foreach ($invoicesByStatus as $status): 
                            $percent = $totalInvoices > 0 ? (($status['cnt'] / $totalInvoices) * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= $status['status'] === 'paid' ? 'success' : ($status['status'] === 'unpaid' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($status['status']) ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format((int)$status['cnt']) ?></strong></td>
                                <td><strong>R$ <?= number_format((float)$status['total'], 2, ',', '.') ?></strong></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%">
                                            <?= number_format($percent, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Estatísticas por Gateway -->
    <?php if (!empty($gatewayStats)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Transações por Gateway</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Gateway</th>
                                <th>Total</th>
                                <th>Sucesso</th>
                                <th>Falhas</th>
                                <th>Valor Total (Sucesso)</th>
                                <th>Taxa de Sucesso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gatewayStats as $gw): 
                                $successRate = (int)$gw['total'] > 0 ? ((int)$gw['success'] / (int)$gw['total']) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?= h(ucfirst($gw['gateway'])) ?></strong></td>
                                    <td><?= number_format((int)$gw['total']) ?></td>
                                    <td><span class="badge bg-success"><?= number_format((int)$gw['success']) ?></span></td>
                                    <td><span class="badge bg-danger"><?= number_format((int)$gw['failed']) ?></span></td>
                                    <td><strong class="text-success">R$ <?= number_format((float)$gw['total_success'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $successRate ?>%">
                                                <?= number_format($successRate, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

