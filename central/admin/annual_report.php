<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Relatório Anual 2025, Novos Clientes e Feedback de Tickets';
$active = 'annual_report';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $year = $_GET['year'] ?? '2025';
    $yearInt = (int)$year;
    
    // ========== ANNUAL INCOME REPORT FOR 2025 ==========
    $annualIncome = [
        'total' => 0.00,
        'by_month' => [],
        'by_quarter' => [],
        'total_invoices' => 0,
        'paid_invoices' => 0,
        'avg_monthly' => 0.00,
    ];
    
    // Receita por mês
    for ($month = 1; $month <= 12; $month++) {
        $monthStr = sprintf('%04d-%02d', $yearInt, $month);
        $stmt = db()->prepare("SELECT SUM(total) as total, COUNT(*) as cnt FROM invoices WHERE status='paid' AND DATE_FORMAT(paid_date, '%Y-%m') = ?");
        $stmt->execute([$monthStr]);
        $data = $stmt->fetch();
        $monthRevenue = (float)($data['total'] ?? 0);
        $monthInvoices = (int)($data['cnt'] ?? 0);
        
        $annualIncome['by_month'][$month] = [
            'revenue' => $monthRevenue,
            'invoices' => $monthInvoices,
        ];
        $annualIncome['total'] += $monthRevenue;
        $annualIncome['total_invoices'] += $monthInvoices;
    }
    
    $annualIncome['avg_monthly'] = $annualIncome['total'] / 12;
    
    // Receita por trimestre
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarterRevenue = 0.00;
        $quarterInvoices = 0;
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        for ($month = $startMonth; $month <= $endMonth; $month++) {
            $quarterRevenue += $annualIncome['by_month'][$month]['revenue'];
            $quarterInvoices += $annualIncome['by_month'][$month]['invoices'];
        }
        
        $annualIncome['by_quarter'][$quarter] = [
            'revenue' => $quarterRevenue,
            'invoices' => $quarterInvoices,
        ];
    }
    
    // Comparação com ano anterior (se disponível)
    $prevYear = $yearInt - 1;
    $stmt = db()->prepare("SELECT SUM(total) as total FROM invoices WHERE status='paid' AND YEAR(paid_date) = ?");
    $stmt->execute([$prevYear]);
    $prevYearRevenue = (float)($stmt->fetch()['total'] ?? 0);
    $yearOverYear = $prevYearRevenue > 0 ? (($annualIncome['total'] - $prevYearRevenue) / $prevYearRevenue) * 100 : 0;
    
    // ========== NEW CUSTOMERS ==========
    $newCustomers = [
        'total' => 0,
        'by_month' => [],
        'by_quarter' => [],
        'growth_rate' => 0.00,
    ];
    
    // Novos clientes por mês
    for ($month = 1; $month <= 12; $month++) {
        $monthStr = sprintf('%04d-%02d', $yearInt, $month);
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$monthStr]);
        $count = (int)$stmt->fetch()['cnt'];
        
        $newCustomers['by_month'][$month] = $count;
        $newCustomers['total'] += $count;
    }
    
    // Novos clientes por trimestre
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarterCount = 0;
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        for ($month = $startMonth; $month <= $endMonth; $month++) {
            $quarterCount += $newCustomers['by_month'][$month];
        }
        
        $newCustomers['by_quarter'][$quarter] = $quarterCount;
    }
    
    // Taxa de crescimento (comparando primeiro e último trimestre)
    $q1Customers = $newCustomers['by_quarter'][1];
    $q4Customers = $newCustomers['by_quarter'][4];
    $newCustomers['growth_rate'] = $q1Customers > 0 ? (($q4Customers - $q1Customers) / $q1Customers) * 100 : 0;
    
    // Top meses para novos clientes
    arsort($newCustomers['by_month']);
    $topMonths = array_slice($newCustomers['by_month'], 0, 3, true);
    
    // ========== TICKET FEEDBACK SCORES ==========
    // Nota: Assumindo que podemos adicionar uma coluna rating na tabela tickets ou criar uma tabela separada
    // Por enquanto, vamos usar uma abordagem baseada em tickets fechados e tempo de resposta
    
    $ticketFeedback = [
        'total_tickets' => 0,
        'closed_tickets' => 0,
        'avg_response_time' => 0.00,
        'by_department' => [],
        'by_month' => [],
        'satisfaction_score' => 0.00,
    ];
    
    // Total de tickets no ano
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE YEAR(created_at) = ?");
    $stmt->execute([$yearInt]);
    $ticketFeedback['total_tickets'] = (int)$stmt->fetch()['cnt'];
    
    // Tickets fechados
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE YEAR(created_at) = ? AND status='closed'");
    $stmt->execute([$yearInt]);
    $ticketFeedback['closed_tickets'] = (int)$stmt->fetch()['cnt'];
    
    // Tempo médio de resposta (em horas)
    $stmt = db()->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, tr.created_at)) as avg_hours
                          FROM tickets t
                          INNER JOIN ticket_replies tr ON tr.ticket_id = t.id
                          WHERE tr.user_type = 'admin' AND YEAR(t.created_at) = ?");
    $stmt->execute([$yearInt]);
    $avgResponse = $stmt->fetch();
    $ticketFeedback['avg_response_time'] = $avgResponse ? (float)$avgResponse['avg_hours'] : 0;
    
    // Tickets por departamento
    $stmt = db()->prepare("SELECT department, COUNT(*) as cnt, 
                          SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed
                          FROM tickets WHERE YEAR(created_at) = ? GROUP BY department");
    $stmt->execute([$yearInt]);
    while ($row = $stmt->fetch()) {
        $ticketFeedback['by_department'][$row['department']] = [
            'total' => (int)$row['cnt'],
            'closed' => (int)$row['closed'],
            'closure_rate' => (int)$row['cnt'] > 0 ? ((int)$row['closed'] / (int)$row['cnt']) * 100 : 0,
        ];
    }
    
    // Tickets por mês
    for ($month = 1; $month <= 12; $month++) {
        $monthStr = sprintf('%04d-%02d', $yearInt, $month);
        $stmt = db()->prepare("SELECT COUNT(*) as cnt, 
                              SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed
                              FROM tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$monthStr]);
        $data = $stmt->fetch();
        $ticketFeedback['by_month'][$month] = [
            'total' => (int)$data['cnt'],
            'closed' => (int)$data['closed'],
        ];
    }
    
    // Score de satisfação (baseado em taxa de fechamento e tempo de resposta)
    // Fórmula: (taxa de fechamento * 0.7) + (score de tempo de resposta * 0.3)
    $closureRate = $ticketFeedback['total_tickets'] > 0 ? ($ticketFeedback['closed_tickets'] / $ticketFeedback['total_tickets']) * 100 : 0;
    // Score de tempo: quanto menor o tempo, maior o score (máximo 24h = 100, mínimo 0)
    $timeScore = $ticketFeedback['avg_response_time'] > 0 ? max(0, 100 - ($ticketFeedback['avg_response_time'] / 24) * 100) : 0;
    $ticketFeedback['satisfaction_score'] = ($closureRate * 0.7) + ($timeScore * 0.3);
    
} catch (Throwable $e) {
    $annualIncome = ['total' => 0, 'by_month' => [], 'by_quarter' => [], 'total_invoices' => 0, 'paid_invoices' => 0, 'avg_monthly' => 0];
    $newCustomers = ['total' => 0, 'by_month' => [], 'by_quarter' => [], 'growth_rate' => 0];
    $ticketFeedback = ['total_tickets' => 0, 'closed_tickets' => 0, 'avg_response_time' => 0, 'by_department' => [], 'by_month' => [], 'satisfaction_score' => 0];
    $yearOverYear = 0;
    $topMonths = [];
    $prevYearRevenue = 0;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatório Anual <?= $yearInt ?></h1>
        <form method="GET" class="d-inline-flex align-items-center gap-2">
            <label for="year" class="form-label mb-0">Ano:</label>
            <select class="form-select" id="year" name="year" style="width: auto;" onchange="this.form.submit()">
                <?php for ($y = 2020; $y <= 2030; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $yearInt ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- ========== ANNUAL INCOME REPORT ========== -->
    <div class="card shadow-sm mb-4 border-success">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="las la-chart-line me-2"></i> Annual Income Report for <?= $yearInt ?></h4>
        </div>
        <div class="card-body">
            <!-- Resumo -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Receita Total</h6>
                        <h3 class="text-success mb-0">R$ <?= number_format($annualIncome['total'], 2, ',', '.') ?></h3>
                        <?php if ($yearOverYear != 0): ?>
                            <small class="<?= $yearOverYear >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $yearOverYear >= 0 ? '+' : '' ?><?= number_format($yearOverYear, 1) ?>% vs <?= $prevYear ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Média Mensal</h6>
                        <h3 class="text-primary mb-0">R$ <?= number_format($annualIncome['avg_monthly'], 2, ',', '.') ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Total de Faturas</h6>
                        <h3 class="text-info mb-0"><?= number_format($annualIncome['total_invoices']) ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Melhor Mês</h6>
                        <?php 
                        $bestMonth = 0;
                        $bestRevenue = 0;
                        foreach ($annualIncome['by_month'] as $month => $data) {
                            if ($data['revenue'] > $bestRevenue) {
                                $bestRevenue = $data['revenue'];
                                $bestMonth = $month;
                            }
                        }
                        $monthNames = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                        ?>
                        <h3 class="text-warning mb-0"><?= $monthNames[$bestMonth] ?? 'N/A' ?></h3>
                        <small>R$ <?= number_format($bestRevenue, 2, ',', '.') ?></small>
                    </div>
                </div>
            </div>

            <!-- Receita por Mês -->
            <h5 class="mb-3">Receita por Mês</h5>
            <div class="table-responsive mb-4">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Receita</th>
                            <th>Faturas</th>
                            <th>Progresso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                        $maxRevenue = max(array_column($annualIncome['by_month'], 'revenue')) ?: 1;
                        foreach ($annualIncome['by_month'] as $month => $data): 
                        ?>
                            <tr>
                                <td><strong><?= $monthNames[$month] ?></strong></td>
                                <td><strong class="text-success">R$ <?= number_format($data['revenue'], 2, ',', '.') ?></strong></td>
                                <td><?= number_format($data['invoices']) ?> faturas</td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($data['revenue'] / $maxRevenue) * 100 ?>%">
                                            <?= number_format(($data['revenue'] / $maxRevenue) * 100, 0) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary">
                            <td><strong>Total</strong></td>
                            <td><strong class="text-success">R$ <?= number_format($annualIncome['total'], 2, ',', '.') ?></strong></td>
                            <td><strong><?= number_format($annualIncome['total_invoices']) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Receita por Trimestre -->
            <h5 class="mb-3">Receita por Trimestre</h5>
            <div class="row">
                <?php foreach ($annualIncome['by_quarter'] as $quarter => $data): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-muted"><?= $quarter ?>º Trimestre</h6>
                                <h4 class="text-primary mb-1">R$ <?= number_format($data['revenue'], 2, ',', '.') ?></h4>
                                <small class="text-muted"><?= number_format($data['invoices']) ?> faturas</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ========== NEW CUSTOMERS ========== -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="las la-user-plus me-2"></i> New Customers - <?= $yearInt ?></h4>
        </div>
        <div class="card-body">
            <!-- Resumo -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Total de Novos Clientes</h6>
                        <h2 class="text-primary mb-0"><?= number_format($newCustomers['total']) ?></h2>
                        <small class="text-muted">Média: <?= number_format($newCustomers['total'] / 12, 1) ?>/mês</small>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Taxa de Crescimento</h6>
                        <h2 class="<?= $newCustomers['growth_rate'] >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                            <?= $newCustomers['growth_rate'] >= 0 ? '+' : '' ?><?= number_format($newCustomers['growth_rate'], 1) ?>%
                        </h2>
                        <small class="text-muted">Q1 vs Q4</small>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Melhor Mês</h6>
                        <?php 
                        $bestCustomerMonth = 0;
                        $bestCustomerCount = 0;
                        foreach ($newCustomers['by_month'] as $month => $count) {
                            if ($count > $bestCustomerCount) {
                                $bestCustomerCount = $count;
                                $bestCustomerMonth = $month;
                            }
                        }
                        ?>
                        <h2 class="text-info mb-0"><?= $monthNames[$bestCustomerMonth] ?? 'N/A' ?></h2>
                        <small><?= number_format($bestCustomerCount) ?> clientes</small>
                    </div>
                </div>
            </div>

            <!-- Novos Clientes por Mês -->
            <h5 class="mb-3">Novos Clientes por Mês</h5>
            <div class="table-responsive mb-4">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Novos Clientes</th>
                            <th>Progresso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $maxCustomers = max($newCustomers['by_month']) ?: 1;
                        foreach ($newCustomers['by_month'] as $month => $count): 
                        ?>
                            <tr>
                                <td><strong><?= $monthNames[$month] ?></strong></td>
                                <td><strong class="text-primary"><?= number_format($count) ?></strong></td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($count / $maxCustomers) * 100 ?>%">
                                            <?= number_format(($count / $maxCustomers) * 100, 0) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Novos Clientes por Trimestre -->
            <h5 class="mb-3">Novos Clientes por Trimestre</h5>
            <div class="row">
                <?php foreach ($newCustomers['by_quarter'] as $quarter => $count): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h6 class="text-muted"><?= $quarter ?>º Trimestre</h6>
                                <h3 class="text-info mb-0"><?= number_format($count) ?></h3>
                                <small class="text-muted">clientes</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ========== TICKET FEEDBACK SCORES ========== -->
    <div class="card shadow-sm border-warning">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0"><i class="las la-star me-2"></i> Ticket Feedback Scores - <?= $yearInt ?></h4>
        </div>
        <div class="card-body">
            <!-- Resumo -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Score de Satisfação</h6>
                        <h2 class="text-warning mb-0"><?= number_format($ticketFeedback['satisfaction_score'], 1) ?></h2>
                        <small class="text-muted">de 100 pontos</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Total de Tickets</h6>
                        <h2 class="text-primary mb-0"><?= number_format($ticketFeedback['total_tickets']) ?></h2>
                        <small class="text-muted"><?= number_format($ticketFeedback['closed_tickets']) ?> fechados</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Taxa de Fechamento</h6>
                        <h2 class="text-success mb-0">
                            <?= $ticketFeedback['total_tickets'] > 0 ? number_format(($ticketFeedback['closed_tickets'] / $ticketFeedback['total_tickets']) * 100, 1) : 0 ?>%
                        </h2>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Tempo Médio de Resposta</h6>
                        <h2 class="text-info mb-0"><?= number_format($ticketFeedback['avg_response_time'], 1) ?>h</h2>
                    </div>
                </div>
            </div>

            <!-- Tickets por Departamento -->
            <?php if (!empty($ticketFeedback['by_department'])): ?>
                <h5 class="mb-3">Tickets por Departamento</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Departamento</th>
                                <th>Total</th>
                                <th>Fechados</th>
                                <th>Taxa de Fechamento</th>
                                <th>Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticketFeedback['by_department'] as $dept => $data): ?>
                                <tr>
                                    <td><strong><?= h(ucfirst($dept)) ?></strong></td>
                                    <td><?= number_format($data['total']) ?></td>
                                    <td><span class="badge bg-success"><?= number_format($data['closed']) ?></span></td>
                                    <td><strong><?= number_format($data['closure_rate'], 1) ?>%</strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $data['closure_rate'] ?>%">
                                                <?= number_format($data['closure_rate'], 0) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Tickets por Mês -->
            <h5 class="mb-3">Tickets por Mês</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Total</th>
                            <th>Fechados</th>
                            <th>Taxa de Fechamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketFeedback['by_month'] as $month => $data): 
                            $monthClosureRate = $data['total'] > 0 ? ($data['closed'] / $data['total']) * 100 : 0;
                        ?>
                            <tr>
                                <td><strong><?= $monthNames[$month] ?></strong></td>
                                <td><?= number_format($data['total']) ?></td>
                                <td><span class="badge bg-success"><?= number_format($data['closed']) ?></span></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $monthClosureRate ?>%">
                                            <?= number_format($monthClosureRate, 1) ?>%
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
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

