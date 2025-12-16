<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Relatórios de Clientes';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Estatísticas gerais de clientes
    $stmt = db()->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) as suspended
        FROM clients");
    $clientStats = $stmt->fetch();
    
    // Novos clientes no período
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $newClients = (int)$stmt->fetch()['cnt'];
    
    // Clientes por status
    $stmt = db()->query("SELECT status, COUNT(*) as cnt FROM clients GROUP BY status");
    $clientsByStatus = $stmt->fetchAll();
    
    // Top clientes por receita
    $stmt = db()->prepare("SELECT c.id, c.first_name, c.last_name, c.email, SUM(i.total) as total_revenue, COUNT(i.id) as invoice_count
        FROM clients c
        INNER JOIN invoices i ON c.id = i.client_id
        WHERE i.status='paid' AND DATE(i.paid_date) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_revenue DESC
        LIMIT 10");
    $stmt->execute([$dateFrom, $dateTo]);
    $topClients = $stmt->fetchAll();
    
    // Clientes por país (se houver campo)
    $stmt = db()->query("SELECT country, COUNT(*) as cnt FROM clients WHERE country IS NOT NULL AND country != '' GROUP BY country ORDER BY cnt DESC LIMIT 10");
    $clientsByCountry = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $clientStats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0];
    $newClients = 0;
    $clientsByStatus = [];
    $topClients = [];
    $clientsByCountry = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios de Clientes</h1>
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
                    <h6 class="text-muted mb-1">Total de Clientes</h6>
                    <h3 class="mb-0"><?= number_format((int)$clientStats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Clientes Ativos</h6>
                    <h3 class="mb-0 text-success"><?= number_format((int)$clientStats['active']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Novos no Período</h6>
                    <h3 class="mb-0 text-info"><?= number_format($newClients) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Clientes Suspensos</h6>
                    <h3 class="mb-0 text-danger"><?= number_format((int)$clientStats['suspended']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Clientes por Status -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Clientes por Status</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Quantidade</th>
                                    <th>Percentual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = (int)$clientStats['total'];
                                foreach ($clientsByStatus as $status): 
                                    $percent = $total > 0 ? (($status['cnt'] / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $status['status'] === 'active' ? 'success' : ($status['status'] === 'suspended' ? 'danger' : 'secondary') ?>">
                                                <?= ucfirst($status['status']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= number_format((int)$status['cnt']) ?></strong></td>
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
            <!-- Top Clientes por Receita -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Top 10 Clientes por Receita</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topClients)): ?>
                        <p class="text-muted text-center mb-0">Sem dados disponíveis.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Faturas</th>
                                        <th>Receita</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topClients as $index => $client): ?>
                                        <tr>
                                            <td><span class="badge bg-primary">#<?= $index + 1 ?></span></td>
                                            <td>
                                                <strong><?= h($client['first_name'] . ' ' . $client['last_name']) ?></strong>
                                                <br><small class="text-muted"><?= h($client['email']) ?></small>
                                            </td>
                                            <td><?= number_format((int)$client['invoice_count']) ?></td>
                                            <td><strong class="text-success">R$ <?= number_format((float)$client['total_revenue'], 2, ',', '.') ?></strong></td>
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

