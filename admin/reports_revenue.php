<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Relatórios de Rendimento';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $period = $_GET['period'] ?? 'monthly'; // daily, weekly, monthly
    
    // Receita total no período
    $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $totalRevenue = (float)($stmt->fetch()['total'] ?? 0);
    
    // Receita por método de pagamento
    $stmt = db()->prepare("SELECT payment_method, SUM(total) as total, COUNT(*) as cnt FROM invoices WHERE status='paid' AND DATE(paid_date) BETWEEN ? AND ? AND payment_method IS NOT NULL GROUP BY payment_method");
    $stmt->execute([$dateFrom, $dateTo]);
    $revenueByMethod = $stmt->fetchAll();
    
    // Receita por período
    $revenueByPeriod = [];
    if ($period === 'daily') {
        $stmt = db()->prepare("SELECT DATE(paid_date) as period, SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) BETWEEN ? AND ? GROUP BY DATE(paid_date) ORDER BY period");
        $stmt->execute([$dateFrom, $dateTo]);
        while ($row = $stmt->fetch()) {
            $revenueByPeriod[$row['period']] = (float)$row['total'];
        }
    } elseif ($period === 'weekly') {
        $stmt = db()->prepare("SELECT YEARWEEK(paid_date) as period, SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) BETWEEN ? AND ? GROUP BY YEARWEEK(paid_date) ORDER BY period");
        $stmt->execute([$dateFrom, $dateTo]);
        while ($row = $stmt->fetch()) {
            $revenueByPeriod['Semana ' . $row['period']] = (float)$row['total'];
        }
    } else {
        $stmt = db()->prepare("SELECT DATE_FORMAT(paid_date, '%Y-%m') as period, SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) BETWEEN ? AND ? GROUP BY DATE_FORMAT(paid_date, '%Y-%m') ORDER BY period");
        $stmt->execute([$dateFrom, $dateTo]);
        while ($row = $stmt->fetch()) {
            $revenueByPeriod[$row['period']] = (float)$row['total'];
        }
    }
    
    // Top produtos/serviços
    $stmt = db()->prepare("SELECT p.name, COUNT(o.id) as orders, SUM(o.amount) as revenue FROM orders o INNER JOIN plans p ON o.plan_id = p.id WHERE o.status='active' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.id ORDER BY revenue DESC LIMIT 10");
    $stmt->execute([$dateFrom, $dateTo]);
    $topProducts = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $totalRevenue = 0;
    $revenueByMethod = [];
    $revenueByPeriod = [];
    $topProducts = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios de Rendimento</h1>
        <a href="/admin/reports.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
                </div>
                <div class="col-md-3">
                    <label for="period" class="form-label">Agrupamento</label>
                    <select class="form-select" id="period" name="period">
                        <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Diário</option>
                        <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Semanal</option>
                        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Receita Total -->
    <div class="card shadow-sm mb-4 border-success">
        <div class="card-body text-center">
            <h6 class="text-muted mb-2">Receita Total no Período</h6>
            <h1 class="text-success mb-0">R$ <?= number_format($totalRevenue, 2, ',', '.') ?></h1>
            <small class="text-muted">Período: <?= date('d/m/Y', strtotime($dateFrom)) ?> até <?= date('d/m/Y', strtotime($dateTo)) ?></small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Receita por Período -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Receita por Período</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($revenueByPeriod)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th>Receita</th>
                                        <th>Progresso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxRevenue = max($revenueByPeriod) ?: 1;
                                    foreach ($revenueByPeriod as $period => $revenue): 
                                    ?>
                                        <tr>
                                            <td><strong><?= h($period) ?></strong></td>
                                            <td><strong class="text-success">R$ <?= number_format($revenue, 2, ',', '.') ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($revenue / $maxRevenue) * 100 ?>%">
                                                        <?= number_format(($revenue / $maxRevenue) * 100, 1) ?>%
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
            <!-- Receita por Método de Pagamento -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Receita por Método de Pagamento</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($revenueByMethod)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Método</th>
                                        <th>Quantidade</th>
                                        <th>Receita</th>
                                        <th>Percentual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($revenueByMethod as $method): 
                                        $percent = $totalRevenue > 0 ? (($method['total'] / $totalRevenue) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?= h($method['payment_method']) ?></strong></td>
                                            <td><?= number_format((int)$method['cnt']) ?></td>
                                            <td><strong class="text-success">R$ <?= number_format((float)$method['total'], 2, ',', '.') ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $percent ?>%">
                                                        <?= number_format($percent, 1) ?>%
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

    <!-- Top Produtos/Serviços -->
    <?php if (!empty($topProducts)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Top 10 Produtos/Serviços por Receita</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Produto/Serviço</th>
                                <th>Pedidos</th>
                                <th>Receita Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <tr>
                                    <td><span class="badge bg-primary">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= h($product['name']) ?></strong></td>
                                    <td><?= number_format((int)$product['orders']) ?></td>
                                    <td><strong class="text-success">R$ <?= number_format((float)$product['revenue'], 2, ',', '.') ?></strong></td>
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

