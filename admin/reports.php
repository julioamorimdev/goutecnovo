<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Relatórios';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatórios</h1>
    </div>

    <div class="row g-4">
        <!-- Relatórios Gerais -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-chart-bar text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Relatórios Gerais</h5>
                    <p class="card-text text-muted">Visão geral do sistema, estatísticas e métricas principais</p>
                    <a href="/admin/reports_general.php" class="btn btn-primary">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatórios de Transações -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-money-bill-wave text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Transações</h5>
                    <p class="card-text text-muted">Relatórios de pagamentos, faturas e transações financeiras</p>
                    <a href="/admin/reports_transactions.php" class="btn btn-success">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatórios de Rendimento -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-chart-line text-info" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Rendimento</h5>
                    <p class="card-text text-muted">Análise de receita, lucro e performance financeira</p>
                    <a href="/admin/reports_revenue.php" class="btn btn-info">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatórios de Clientes -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-users text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Clientes</h5>
                    <p class="card-text text-muted">Estatísticas de clientes, crescimento e atividade</p>
                    <a href="/admin/reports_clients.php" class="btn btn-warning">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatórios de Suporte -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-headset text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Suporte</h5>
                    <p class="card-text text-muted">Métricas de tickets, tempo de resposta e satisfação</p>
                    <a href="/admin/reports_support.php" class="btn btn-danger">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Exportações -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-file-export text-secondary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Exportações</h5>
                    <p class="card-text text-muted">Exportar dados em CSV, Excel e outros formatos</p>
                    <a href="/admin/reports_exports.php" class="btn btn-secondary">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatórios do Sistema -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="las la-server text-dark" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Sistema</h5>
                    <p class="card-text text-muted">Logs, auditoria e relatórios técnicos do sistema</p>
                    <a href="/admin/reports_system.php" class="btn btn-dark">
                        <i class="las la-arrow-right me-1"></i> Acessar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

