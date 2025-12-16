<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Relatórios do Sistema';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Estatísticas do sistema
    $stats = [
        'total_admins' => 0,
        'total_logs' => 0,
        'total_incidents' => 0,
        'active_incidents' => 0,
    ];
    
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM admin_users");
    $stats['total_admins'] = (int)$stmt->fetch()['cnt'];
    
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM network_incidents");
    $stats['total_incidents'] = (int)$stmt->fetch()['cnt'];
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM network_incidents WHERE status NOT IN ('resolved', 'false_alarm')");
    $stats['active_incidents'] = (int)$stmt->fetch()['cnt'];
    
    // Incidentes por tipo
    $stmt = db()->query("SELECT type, COUNT(*) as cnt FROM network_incidents GROUP BY type");
    $incidentsByType = $stmt->fetchAll();
    
    // Incidentes por severidade
    $stmt = db()->query("SELECT severity, COUNT(*) as cnt FROM network_incidents GROUP BY severity");
    $incidentsBySeverity = $stmt->fetchAll();
    
    // Estatísticas de gateway (últimos 30 dias)
    $stmt = db()->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
        gateway
        FROM gateway_transactions 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY gateway");
    $gatewayStats = $stmt->fetchAll();
    
    // Informações do banco de dados
    $dbInfo = [
        'version' => db()->query("SELECT VERSION() as version")->fetch()['version'],
        'database' => db()->query("SELECT DATABASE() as db")->fetch()['db'],
    ];
    
} catch (Throwable $e) {
    $stats = ['total_admins' => 0, 'total_logs' => 0, 'total_incidents' => 0, 'active_incidents' => 0];
    $incidentsByType = [];
    $incidentsBySeverity = [];
    $gatewayStats = [];
    $dbInfo = ['version' => 'N/A', 'database' => 'N/A'];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios do Sistema</h1>
        <a href="/admin/reports.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Administradores</h6>
                    <h3 class="mb-0"><?= number_format($stats['total_admins']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total de Incidentes</h6>
                    <h3 class="mb-0"><?= number_format($stats['total_incidents']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Incidentes Ativos</h6>
                    <h3 class="mb-0 text-danger"><?= number_format($stats['active_incidents']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Versão MySQL</h6>
                    <h3 class="mb-0 text-info" style="font-size: 1.2rem;"><?= h($dbInfo['version']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Incidentes por Tipo -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Incidentes por Tipo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($incidentsByType)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Quantidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidentsByType as $type): ?>
                                        <tr>
                                            <td><strong><?= h(ucfirst($type['type'])) ?></strong></td>
                                            <td><span class="badge bg-primary"><?= number_format((int)$type['cnt']) ?></span></td>
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
            <!-- Incidentes por Severidade -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Incidentes por Severidade</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($incidentsBySeverity)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Severidade</th>
                                        <th>Quantidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $severityBadges = [
                                        'critical' => 'bg-danger',
                                        'high' => 'bg-warning',
                                        'medium' => 'bg-info',
                                        'low' => 'bg-secondary'
                                    ];
                                    foreach ($incidentsBySeverity as $severity): 
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?= $severityBadges[$severity['severity']] ?? 'bg-secondary' ?>">
                                                    <?= ucfirst($severity['severity']) ?>
                                                </span>
                                            </td>
                                            <td><strong><?= number_format((int)$severity['cnt']) ?></strong></td>
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

    <!-- Estatísticas de Gateway (Últimos 30 dias) -->
    <?php if (!empty($gatewayStats)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Transações de Gateway (Últimos 30 Dias)</h5>
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
                                <th>Taxa de Sucesso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gatewayStats as $gw): 
                                $successRate = (int)$gw['total'] > 0 ? ((int)$gw['success'] / (int)$gw['total']) * 100) : 0;
                            ?>
                                <tr>
                                    <td><strong><?= h(ucfirst($gw['gateway'])) ?></strong></td>
                                    <td><?= number_format((int)$gw['total']) ?></td>
                                    <td><span class="badge bg-success"><?= number_format((int)$gw['success']) ?></span></td>
                                    <td><span class="badge bg-danger"><?= number_format((int)$gw['failed']) ?></span></td>
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

