<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Relatórios de Suporte';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Estatísticas gerais
    $stmt = db()->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status='answered' THEN 1 ELSE 0 END) as answered,
        SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed
        FROM tickets WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $ticketStats = $stmt->fetch();
    
    // Tickets por departamento
    $stmt = db()->prepare("SELECT department, COUNT(*) as cnt FROM tickets WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY department");
    $stmt->execute([$dateFrom, $dateTo]);
    $ticketsByDept = $stmt->fetchAll();
    
    // Tickets por prioridade
    $stmt = db()->prepare("SELECT priority, COUNT(*) as cnt FROM tickets WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY priority");
    $stmt->execute([$dateFrom, $dateTo]);
    $ticketsByPriority = $stmt->fetchAll();
    
    // Tempo médio de resposta
    $stmt = db()->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, tr.created_at)) as avg_hours
        FROM tickets t
        INNER JOIN ticket_replies tr ON tr.ticket_id = t.id
        WHERE tr.user_type = 'admin'
        AND DATE(t.created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $avgResponse = $stmt->fetch();
    $avgResponseHours = $avgResponse ? (float)$avgResponse['avg_hours'] : 0;
    
    // Top atendentes
    $stmt = db()->prepare("SELECT a.username, COUNT(tr.id) as reply_count, AVG(TIMESTAMPDIFF(HOUR, t.created_at, tr.created_at)) as avg_response
        FROM ticket_replies tr
        INNER JOIN admin_users a ON tr.user_id = a.id
        INNER JOIN tickets t ON tr.ticket_id = t.id
        WHERE tr.user_type = 'admin'
        AND DATE(tr.created_at) BETWEEN ? AND ?
        GROUP BY a.id, a.username
        ORDER BY reply_count DESC
        LIMIT 10");
    $stmt->execute([$dateFrom, $dateTo]);
    $topAgents = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $ticketStats = ['total' => 0, 'open' => 0, 'answered' => 0, 'closed' => 0];
    $ticketsByDept = [];
    $ticketsByPriority = [];
    $avgResponseHours = 0;
    $topAgents = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios de Suporte</h1>
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
                    <h6 class="text-muted mb-1">Total de Tickets</h6>
                    <h3 class="mb-0"><?= number_format((int)$ticketStats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Tickets Abertos</h6>
                    <h3 class="mb-0 text-warning"><?= number_format((int)$ticketStats['open']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Tickets Respondidos</h6>
                    <h3 class="mb-0 text-info"><?= number_format((int)$ticketStats['answered']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Tempo Médio de Resposta</h6>
                    <h3 class="mb-0 text-success">
                        <?= $avgResponseHours > 0 ? number_format($avgResponseHours, 1) . 'h' : 'N/A' ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Tickets por Departamento -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Tickets por Departamento</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Departamento</th>
                                    <th>Quantidade</th>
                                    <th>Percentual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = (int)$ticketStats['total'];
                                foreach ($ticketsByDept as $dept): 
                                    $percent = $total > 0 ? (($dept['cnt'] / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= h(ucfirst($dept['department'])) ?></strong></td>
                                        <td><?= number_format((int)$dept['cnt']) ?></td>
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
        </div>

        <div class="col-lg-6">
            <!-- Tickets por Prioridade -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Tickets por Prioridade</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Prioridade</th>
                                    <th>Quantidade</th>
                                    <th>Percentual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($ticketsByPriority as $priority): 
                                    $percent = $total > 0 ? (($priority['cnt'] / $total) * 100) : 0;
                                    $badgeClass = [
                                        'urgent' => 'bg-danger',
                                        'high' => 'bg-warning',
                                        'medium' => 'bg-info',
                                        'low' => 'bg-secondary'
                                    ];
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= $badgeClass[$priority['priority']] ?? 'bg-secondary' ?>">
                                                <?= ucfirst($priority['priority']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format((int)$priority['cnt']) ?></td>
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
        </div>
    </div>

    <!-- Top Atendentes -->
    <?php if (!empty($topAgents)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Top 10 Atendentes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Atendente</th>
                                <th>Respostas</th>
                                <th>Tempo Médio de Resposta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topAgents as $index => $agent): ?>
                                <tr>
                                    <td><span class="badge bg-primary">#<?= $index + 1 ?></span></td>
                                    <td><strong><?= h($agent['username']) ?></strong></td>
                                    <td><?= number_format((int)$agent['reply_count']) ?></td>
                                    <td>
                                        <?php if ($agent['avg_response']): ?>
                                            <strong><?= number_format((float)$agent['avg_response'], 1) ?>h</strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
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

