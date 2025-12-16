<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Visão Geral do Suporte';
$active = 'support_overview';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Estatísticas gerais de tickets
    $stats = [
        'total' => 0,
        'open' => 0,
        'answered' => 0,
        'customer_reply' => 0,
        'closed' => 0,
        'urgent' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0,
    ];
    
    $stmt = db()->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status");
    while ($row = $stmt->fetch()) {
        $stats['total'] += (int)$row['cnt'];
        $stats[$row['status']] = (int)$row['cnt'];
    }
    
    $stmt = db()->query("SELECT priority, COUNT(*) as cnt FROM tickets WHERE status != 'closed' GROUP BY priority");
    while ($row = $stmt->fetch()) {
        $stats[$row['priority']] = (int)$row['cnt'];
    }
    
    // Estatísticas por departamento
    $deptStats = [];
    $stmt = db()->query("SELECT department, status, COUNT(*) as cnt FROM tickets GROUP BY department, status");
    while ($row = $stmt->fetch()) {
        if (!isset($deptStats[$row['department']])) {
            $deptStats[$row['department']] = ['total' => 0, 'open' => 0, 'answered' => 0, 'customer_reply' => 0, 'closed' => 0];
        }
        $deptStats[$row['department']]['total'] += (int)$row['cnt'];
        $deptStats[$row['department']][$row['status']] = (int)$row['cnt'];
    }
    
    // Tickets recentes
    $recentTickets = db()->query("SELECT t.*, c.first_name, c.last_name, c.email as client_email,
                                          (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) as reply_count
                                   FROM tickets t
                                   LEFT JOIN clients c ON t.client_id = c.id
                                   ORDER BY t.created_at DESC
                                   LIMIT 10")->fetchAll();
    
    // Tickets urgentes
    $urgentTickets = db()->query("SELECT t.*, c.first_name, c.last_name, c.email as client_email,
                                          (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) as reply_count
                                   FROM tickets t
                                   LEFT JOIN clients c ON t.client_id = c.id
                                   WHERE t.priority = 'urgent' AND t.status != 'closed'
                                   ORDER BY t.created_at DESC
                                   LIMIT 10")->fetchAll();
    
    // Tickets aguardando resposta do cliente
    $waitingCustomer = db()->query("SELECT t.*, c.first_name, c.last_name, c.email as client_email,
                                            (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) as reply_count
                                     FROM tickets t
                                     LEFT JOIN clients c ON t.client_id = c.id
                                     WHERE t.status = 'answered'
                                     ORDER BY t.last_reply_at DESC
                                     LIMIT 10")->fetchAll();
    
    // Estatísticas de tempo médio de resposta
    $avgResponseTime = db()->query("SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, tr.created_at)) as avg_hours
                                    FROM tickets t
                                    INNER JOIN ticket_replies tr ON tr.ticket_id = t.id
                                    WHERE tr.user_type = 'admin'
                                    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch();
    $avgResponseHours = $avgResponseTime ? (float)$avgResponseTime['avg_hours'] : 0;
    
    // Tickets por dia (últimos 7 dias)
    $dailyStats = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $dailyStats[$date] = (int)$stmt->fetch()['cnt'];
    }
    
    // Top atendentes (por número de respostas)
    $topAgents = db()->query("SELECT a.username, COUNT(tr.id) as reply_count
                               FROM ticket_replies tr
                               INNER JOIN admin_users a ON tr.user_id = a.id
                               WHERE tr.user_type = 'admin'
                               AND tr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               GROUP BY a.id, a.username
                               ORDER BY reply_count DESC
                               LIMIT 5")->fetchAll();
    
} catch (Throwable $e) {
    $stats = ['total' => 0, 'open' => 0, 'answered' => 0, 'customer_reply' => 0, 'closed' => 0, 'urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $deptStats = [];
    $recentTickets = [];
    $urgentTickets = [];
    $waitingCustomer = [];
    $avgResponseHours = 0;
    $dailyStats = [];
    $topAgents = [];
}

$deptLabels = [
    'support' => 'Suporte',
    'sales' => 'Vendas',
    'billing' => 'Faturamento',
    'technical' => 'Técnico'
];

$statusLabels = [
    'open' => 'Abertos',
    'answered' => 'Respondidos',
    'customer_reply' => 'Aguardando Cliente',
    'closed' => 'Fechados'
];

$priorityLabels = [
    'urgent' => 'Urgente',
    'high' => 'Alta',
    'medium' => 'Média',
    'low' => 'Baixa'
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Visão Geral do Suporte</h1>
        <a href="/admin/tickets.php" class="btn btn-primary">
            <i class="las la-ticket-alt me-1"></i> Ver Todos os Tickets
        </a>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total de Tickets</h6>
                            <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="las la-ticket-alt"></i>
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
                            <h6 class="text-muted mb-1">Abertos</h6>
                            <h3 class="mb-0 text-warning"><?= number_format($stats['open']) ?></h3>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="las la-clock"></i>
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
                            <h6 class="text-muted mb-1">Aguardando Cliente</h6>
                            <h3 class="mb-0 text-info"><?= number_format($stats['customer_reply']) ?></h3>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="las la-user-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Urgentes</h6>
                            <h3 class="mb-0 text-danger"><?= number_format($stats['urgent']) ?></h3>
                        </div>
                        <div class="text-danger" style="font-size: 2.5rem;">
                            <i class="las la-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Tickets Urgentes -->
            <?php if (!empty($urgentTickets)): ?>
                <div class="card shadow-sm mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="las la-exclamation-triangle me-2"></i> Tickets Urgentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Cliente</th>
                                        <th>Assunto</th>
                                        <th>Departamento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urgentTickets as $ticket): ?>
                                        <tr>
                                            <td><strong><?= h($ticket['ticket_number']) ?></strong></td>
                                            <td><?= h($ticket['first_name'] . ' ' . $ticket['last_name']) ?></td>
                                            <td><?= h($ticket['subject']) ?></td>
                                            <td><span class="badge bg-secondary"><?= $deptLabels[$ticket['department']] ?? ucfirst($ticket['department']) ?></span></td>
                                            <td>
                                                <span class="badge bg-<?= $ticket['status'] === 'open' ? 'warning' : 'info' ?>">
                                                    <?= $statusLabels[$ticket['status']] ?? ucfirst($ticket['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/admin/ticket_view.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="las la-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tickets Recentes -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="las la-clock me-2"></i> Tickets Recentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentTickets)): ?>
                        <p class="text-muted text-center mb-0">Nenhum ticket recente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Cliente</th>
                                        <th>Assunto</th>
                                        <th>Prioridade</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                        <tr>
                                            <td><strong><?= h($ticket['ticket_number']) ?></strong></td>
                                            <td><?= h($ticket['first_name'] . ' ' . $ticket['last_name']) ?></td>
                                            <td><?= h($ticket['subject']) ?></td>
                                            <td>
                                                <?php
                                                $priorityBadges = [
                                                    'urgent' => 'bg-danger',
                                                    'high' => 'bg-warning',
                                                    'medium' => 'bg-info',
                                                    'low' => 'bg-secondary'
                                                ];
                                                ?>
                                                <span class="badge <?= $priorityBadges[$ticket['priority']] ?? 'bg-secondary' ?>">
                                                    <?= $priorityLabels[$ticket['priority']] ?? ucfirst($ticket['priority']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $ticket['status'] === 'open' ? 'warning' : ($ticket['status'] === 'closed' ? 'success' : 'info') ?>">
                                                    <?= $statusLabels[$ticket['status']] ?? ucfirst($ticket['status']) ?>
                                                </span>
                                            </td>
                                            <td><small><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></small></td>
                                            <td>
                                                <a href="/admin/ticket_view.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="las la-eye"></i>
                                                </a>
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

        <div class="col-lg-4">
            <!-- Estatísticas por Departamento -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="las la-chart-pie me-2"></i> Por Departamento</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($deptStats)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <?php foreach ($deptStats as $dept => $deptData): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= $deptLabels[$dept] ?? ucfirst($dept) ?></strong>
                                    <span class="badge bg-primary"><?= $deptData['total'] ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $deptData['total'] > 0 ? ($deptData['open'] / $deptData['total'] * 100) : 0 ?>%"></div>
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $deptData['total'] > 0 ? ($deptData['answered'] / $deptData['total'] * 100) : 0 ?>%"></div>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $deptData['total'] > 0 ? ($deptData['closed'] / $deptData['total'] * 100) : 0 ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    Abertos: <?= $deptData['open'] ?> | 
                                    Respondidos: <?= $deptData['answered'] ?> | 
                                    Fechados: <?= $deptData['closed'] ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estatísticas de Performance -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="las la-tachometer-alt me-2"></i> Performance</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Tempo Médio de Resposta</strong>
                        <div class="h4 text-success">
                            <?php if ($avgResponseHours > 0): ?>
                                <?= number_format($avgResponseHours, 1) ?> horas
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Últimos 30 dias</small>
                    </div>
                    <hr>
                    <div>
                        <strong>Prioridades Abertas</strong>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Urgente</span>
                                <span class="badge bg-danger"><?= $stats['urgent'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Alta</span>
                                <span class="badge bg-warning"><?= $stats['high'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Média</span>
                                <span class="badge bg-info"><?= $stats['medium'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Baixa</span>
                                <span class="badge bg-secondary"><?= $stats['low'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Atendentes -->
            <?php if (!empty($topAgents)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="las la-trophy me-2"></i> Top Atendentes</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($topAgents as $index => $agent): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-<?= $index === 0 ? 'warning' : 'secondary' ?> me-2">#<?= $index + 1 ?></span>
                                    <?= h($agent['username']) ?>
                                </div>
                                <span class="badge bg-primary"><?= (int)$agent['reply_count'] ?> respostas</span>
                            </div>
                        <?php endforeach; ?>
                        <small class="text-muted">Últimos 30 dias</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

