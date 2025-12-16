<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Performance Diária e Previsão de Receita';
$active = 'daily_performance';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Data selecionada (padrão: hoje)
    $selectedDate = $_GET['date'] ?? date('Y-m-d');
    $dateObj = new DateTime($selectedDate);
    
    // Performance do dia selecionado
    $todayStats = [
        'revenue' => 0.00,
        'new_clients' => 0,
        'new_orders' => 0,
        'paid_invoices' => 0,
        'tickets_answered' => 0,
        'tickets_created' => 0,
    ];
    
    // Receita do dia
    $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['revenue'] = (float)($stmt->fetch()['total'] ?? 0);
    
    // Novos clientes
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE(created_at) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['new_clients'] = (int)$stmt->fetch()['cnt'];
    
    // Novos pedidos
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM orders WHERE DATE(created_at) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['new_orders'] = (int)$stmt->fetch()['cnt'];
    
    // Faturas pagas
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE status='paid' AND DATE(paid_date) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['paid_invoices'] = (int)$stmt->fetch()['cnt'];
    
    // Tickets respondidos
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM ticket_replies tr 
                          INNER JOIN tickets t ON tr.ticket_id = t.id 
                          WHERE tr.user_type = 'admin' AND DATE(tr.created_at) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['tickets_answered'] = (int)$stmt->fetch()['cnt'];
    
    // Tickets criados
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE DATE(created_at) = ?");
    $stmt->execute([$selectedDate]);
    $todayStats['tickets_created'] = (int)$stmt->fetch()['cnt'];
    
    // Comparação com dias anteriores (últimos 7 dias)
    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dayStats = [
            'date' => $date,
            'revenue' => 0.00,
            'new_clients' => 0,
            'new_orders' => 0,
        ];
        
        $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND DATE(paid_date) = ?");
        $stmt->execute([$date]);
        $dayStats['revenue'] = (float)($stmt->fetch()['total'] ?? 0);
        
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $dayStats['new_clients'] = (int)$stmt->fetch()['cnt'];
        
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM orders WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $dayStats['new_orders'] = (int)$stmt->fetch()['cnt'];
        
        $last7Days[] = $dayStats;
    }
    
    // Média dos últimos 7 dias
    $avg7Days = [
        'revenue' => 0.00,
        'new_clients' => 0,
        'new_orders' => 0,
    ];
    foreach ($last7Days as $day) {
        $avg7Days['revenue'] += $day['revenue'];
        $avg7Days['new_clients'] += $day['new_clients'];
        $avg7Days['new_orders'] += $day['new_orders'];
    }
    $avg7Days['revenue'] = $avg7Days['revenue'] / 7;
    $avg7Days['new_clients'] = round($avg7Days['new_clients'] / 7);
    $avg7Days['new_orders'] = round($avg7Days['new_orders'] / 7);
    
    // Income Forecast - Previsão de receita
    // Baseado nos últimos 30 dias
    $forecastData = [];
    $stmt = db()->prepare("SELECT DATE(paid_date) as date, SUM(total) as total 
                          FROM invoices 
                          WHERE status='paid' AND paid_date >= DATE_SUB(?, INTERVAL 30 DAY) AND paid_date <= ?
                          GROUP BY DATE(paid_date) 
                          ORDER BY date");
    $stmt->execute([$selectedDate, $selectedDate]);
    $historicalRevenue = [];
    while ($row = $stmt->fetch()) {
        $historicalRevenue[$row['date']] = (float)$row['total'];
    }
    
    // Calcular média diária dos últimos 30 dias
    $avgDailyRevenue = count($historicalRevenue) > 0 ? array_sum($historicalRevenue) / count($historicalRevenue) : 0;
    
    // Previsão para os próximos 30 dias
    $forecast = [];
    for ($i = 1; $i <= 30; $i++) {
        $forecastDate = date('Y-m-d', strtotime($selectedDate . " +{$i} days"));
        $forecast[] = [
            'date' => $forecastDate,
            'projected' => $avgDailyRevenue,
            'day_of_week' => date('w', strtotime($forecastDate)), // 0 = domingo, 6 = sábado
        ];
    }
    
    // Receita mensal atual
    $currentMonth = date('Y-m', strtotime($selectedDate));
    $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND DATE_FORMAT(paid_date, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $currentMonthRevenue = (float)($stmt->fetch()['total'] ?? 0);
    
    // Previsão mensal (baseada na média diária)
    $daysInMonth = (int)date('t', strtotime($selectedDate));
    $daysPassed = (int)date('d', strtotime($selectedDate));
    $projectedMonthRevenue = $avgDailyRevenue * $daysInMonth;
    
    // Receita por dia da semana (últimos 30 dias)
    $revenueByDayOfWeek = [];
    $stmt = db()->prepare("SELECT DAYOFWEEK(paid_date) as day_of_week, AVG(total) as avg_revenue, COUNT(*) as count
                          FROM invoices 
                          WHERE status='paid' AND paid_date >= DATE_SUB(?, INTERVAL 30 DAY) AND paid_date <= ?
                          GROUP BY DAYOFWEEK(paid_date)");
    $stmt->execute([$selectedDate, $selectedDate]);
    while ($row = $stmt->fetch()) {
        $revenueByDayOfWeek[$row['day_of_week']] = [
            'avg' => (float)$row['avg_revenue'],
            'count' => (int)$row['count']
        ];
    }
    
} catch (Throwable $e) {
    $todayStats = ['revenue' => 0, 'new_clients' => 0, 'new_orders' => 0, 'paid_invoices' => 0, 'tickets_answered' => 0, 'tickets_created' => 0];
    $last7Days = [];
    $avg7Days = ['revenue' => 0, 'new_clients' => 0, 'new_orders' => 0];
    $forecast = [];
    $currentMonthRevenue = 0;
    $projectedMonthRevenue = 0;
    $revenueByDayOfWeek = [];
    $avgDailyRevenue = 0;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Performance Diária e Previsão de Receita</h1>
        <form method="GET" class="d-inline">
            <input type="date" class="form-control d-inline-block" style="width: auto;" name="date" value="<?= h($selectedDate) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <!-- Performance do Dia -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Performance do Dia - <?= date('d/m/Y', strtotime($selectedDate)) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Receita</h6>
                                <h4 class="text-success mb-0">R$ <?= number_format($todayStats['revenue'], 2, ',', '.') ?></h4>
                                <?php if ($avg7Days['revenue'] > 0): 
                                    $revenueDiff = (($todayStats['revenue'] - $avg7Days['revenue']) / $avg7Days['revenue']) * 100;
                                ?>
                                    <small class="<?= $revenueDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $revenueDiff >= 0 ? '+' : '' ?><?= number_format($revenueDiff, 1) ?>% vs média
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Novos Clientes</h6>
                                <h4 class="text-primary mb-0"><?= number_format($todayStats['new_clients']) ?></h4>
                                <?php if ($avg7Days['new_clients'] > 0): 
                                    $clientsDiff = (($todayStats['new_clients'] - $avg7Days['new_clients']) / $avg7Days['new_clients']) * 100;
                                ?>
                                    <small class="<?= $clientsDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $clientsDiff >= 0 ? '+' : '' ?><?= number_format($clientsDiff, 1) ?>% vs média
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Novos Pedidos</h6>
                                <h4 class="text-info mb-0"><?= number_format($todayStats['new_orders']) ?></h4>
                                <?php if ($avg7Days['new_orders'] > 0): 
                                    $ordersDiff = (($todayStats['new_orders'] - $avg7Days['new_orders']) / $avg7Days['new_orders']) * 100;
                                ?>
                                    <small class="<?= $ordersDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $ordersDiff >= 0 ? '+' : '' ?><?= number_format($ordersDiff, 1) ?>% vs média
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Faturas Pagas</h6>
                                <h4 class="text-success mb-0"><?= number_format($todayStats['paid_invoices']) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Tickets Respondidos</h6>
                                <h4 class="text-warning mb-0"><?= number_format($todayStats['tickets_answered']) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="text-center">
                                <h6 class="text-muted mb-1">Tickets Criados</h6>
                                <h4 class="text-danger mb-0"><?= number_format($todayStats['tickets_created']) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Últimos 7 Dias - Receita -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Receita - Últimos 7 Dias</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Receita</th>
                                    <th>Progresso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $maxRevenue = max(array_column($last7Days, 'revenue')) ?: 1;
                                foreach ($last7Days as $day): 
                                    $isSelected = $day['date'] === $selectedDate;
                                ?>
                                    <tr class="<?= $isSelected ? 'table-primary' : '' ?>">
                                        <td>
                                            <strong><?= date('d/m', strtotime($day['date'])) ?></strong>
                                            <?= $isSelected ? '<span class="badge bg-primary">Hoje</span>' : '' ?>
                                        </td>
                                        <td><strong class="text-success">R$ <?= number_format($day['revenue'], 2, ',', '.') ?></strong></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($day['revenue'] / $maxRevenue) * 100 ?>%">
                                                    <?= number_format(($day['revenue'] / $maxRevenue) * 100, 0) ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <td><strong>Média (7 dias)</strong></td>
                                    <td><strong class="text-info">R$ <?= number_format($avg7Days['revenue'], 2, ',', '.') ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- Income Forecast -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Previsão de Receita</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Receita do Mês Atual</h6>
                                <h4 class="text-success">R$ <?= number_format($currentMonthRevenue, 2, ',', '.') ?></h4>
                                <small class="text-muted"><?= $daysPassed ?> de <?= $daysInMonth ?> dias</small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Previsão Mensal</h6>
                                <h4 class="text-info">R$ <?= number_format($projectedMonthRevenue, 2, ',', '.') ?></h4>
                                <small class="text-muted">Baseado na média diária</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Média Diária (Últimos 30 dias)</h6>
                        <h3 class="text-primary">R$ <?= number_format($avgDailyRevenue, 2, ',', '.') ?></h3>
                    </div>
                    <div class="alert alert-info">
                        <i class="las la-info-circle"></i> <strong>Previsão:</strong> Baseada na média de receita diária dos últimos 30 dias.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Previsão para Próximos 30 Dias -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Previsão de Receita - Próximos 30 Dias</h5>
        </div>
        <div class="card-body">
            <?php if (empty($forecast)): ?>
                <p class="text-muted text-center mb-0">Sem dados suficientes para previsão.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dia da Semana</th>
                                <th>Receita Projetada</th>
                                <th>Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $maxProjected = $avgDailyRevenue;
                            $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                            foreach ($forecast as $day): 
                            ?>
                                <tr>
                                    <td><strong><?= date('d/m/Y', strtotime($day['date'])) ?></strong></td>
                                    <td><?= $dayNames[$day['day_of_week']] ?></td>
                                    <td><strong class="text-info">R$ <?= number_format($day['projected'], 2, ',', '.') ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 100%">
                                                100%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <td colspan="2"><strong>Total Projetado (30 dias)</strong></td>
                                <td><strong class="text-success">R$ <?= number_format($avgDailyRevenue * 30, 2, ',', '.') ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receita por Dia da Semana -->
    <?php if (!empty($revenueByDayOfWeek)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Receita Média por Dia da Semana (Últimos 30 dias)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Dia da Semana</th>
                                <th>Receita Média</th>
                                <th>Transações</th>
                                <th>Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $dayNamesFull = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            $maxAvg = max(array_column($revenueByDayOfWeek, 'avg')) ?: 1;
                            for ($i = 1; $i <= 7; $i++): 
                                if (isset($revenueByDayOfWeek[$i])):
                                    $dayData = $revenueByDayOfWeek[$i];
                            ?>
                                <tr>
                                    <td><strong><?= $dayNamesFull[$i] ?></strong></td>
                                    <td><strong class="text-success">R$ <?= number_format($dayData['avg'], 2, ',', '.') ?></strong></td>
                                    <td><?= number_format($dayData['count']) ?> transações</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($dayData['avg'] / $maxAvg) * 100 ?>%">
                                                <?= number_format(($dayData['avg'] / $maxAvg) * 100, 0) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endfor; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

