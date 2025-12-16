<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Exportações';
$active = 'reports';
require_once __DIR__ . '/partials/layout_start.php';

// Processar exportação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $exportType = $_POST['export_type'] ?? '';
    $format = $_POST['format'] ?? 'csv';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $filename = '';
        $headers = [];
        $data = [];
        
        if ($exportType === 'clients') {
            $filename = 'clientes_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Nome', 'Email', 'Telefone', 'Empresa', 'Status', 'Data de Criação'];
            $stmt = db()->query("SELECT id, first_name, last_name, email, phone, company_name, status, created_at FROM clients ORDER BY created_at DESC");
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email'],
                    $row['phone'] ?? '',
                    $row['company_name'] ?? '',
                    $row['status'],
                    $row['created_at']
                ];
            }
        } elseif ($exportType === 'invoices') {
            $filename = 'faturas_' . date('Y-m-d') . '.csv';
            $where = [];
            $params = [];
            if ($dateFrom) {
                $where[] = "DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $where[] = "DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $headers = ['Número', 'Cliente', 'Valor', 'Status', 'Data de Vencimento', 'Data de Pagamento'];
            $sql = "SELECT i.invoice_number, c.first_name, c.last_name, c.email, i.total, i.status, i.due_date, i.paid_date 
                    FROM invoices i 
                    LEFT JOIN clients c ON i.client_id = c.id 
                    {$whereClause} 
                    ORDER BY i.created_at DESC";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['invoice_number'],
                    $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ')',
                    $row['total'],
                    $row['status'],
                    $row['due_date'] ?? '',
                    $row['paid_date'] ?? ''
                ];
            }
        } elseif ($exportType === 'orders') {
            $filename = 'pedidos_' . date('Y-m-d') . '.csv';
            $headers = ['Número', 'Cliente', 'Plano', 'Valor', 'Status', 'Ciclo', 'Data de Criação'];
            $stmt = db()->query("SELECT o.order_number, c.first_name, c.last_name, c.email, p.name as plan_name, o.amount, o.status, o.billing_cycle, o.created_at 
                                FROM orders o 
                                LEFT JOIN clients c ON o.client_id = c.id 
                                LEFT JOIN plans p ON o.plan_id = p.id 
                                ORDER BY o.created_at DESC");
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['order_number'],
                    $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ')',
                    $row['plan_name'] ?? '',
                    $row['amount'],
                    $row['status'],
                    $row['billing_cycle'],
                    $row['created_at']
                ];
            }
        } elseif ($exportType === 'tickets') {
            $filename = 'tickets_' . date('Y-m-d') . '.csv';
            $headers = ['Número', 'Cliente', 'Assunto', 'Departamento', 'Prioridade', 'Status', 'Data de Criação'];
            $stmt = db()->query("SELECT t.ticket_number, c.first_name, c.last_name, c.email, t.subject, t.department, t.priority, t.status, t.created_at 
                                FROM tickets t 
                                LEFT JOIN clients c ON t.client_id = c.id 
                                ORDER BY t.created_at DESC");
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['ticket_number'],
                    $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ')',
                    $row['subject'],
                    $row['department'],
                    $row['priority'],
                    $row['status'],
                    $row['created_at']
                ];
            }
        }
        
        if (!empty($data)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($output, $headers, ';');
            
            // Data
            foreach ($data as $row) {
                fputcsv($output, $row, ';');
            }
            
            fclose($output);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao exportar dados: ' . $e->getMessage();
    }
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Exportações</h1>
        <a href="/admin/reports.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Exportar Dados</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="export" value="1">
                        
                        <div class="mb-3">
                            <label for="export_type" class="form-label">Tipo de Dados <span class="text-danger">*</span></label>
                            <select class="form-select" id="export_type" name="export_type" required>
                                <option value="">Selecione...</option>
                                <option value="clients">Clientes</option>
                                <option value="invoices">Faturas</option>
                                <option value="orders">Pedidos</option>
                                <option value="tickets">Tickets</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="format" class="form-label">Formato <span class="text-danger">*</span></label>
                            <select class="form-select" id="format" name="format" required>
                                <option value="csv" selected>CSV (Excel)</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label">Data Inicial (opcional)</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
                                <small class="text-muted">Aplicável apenas para faturas</small>
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label">Data Final (opcional)</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
                                <small class="text-muted">Aplicável apenas para faturas</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="las la-download me-1"></i> Exportar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Informações</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        <strong>Formato CSV:</strong> Os arquivos são exportados em formato CSV compatível com Excel e outros programas de planilha.
                    </p>
                    <p class="small text-muted mb-0">
                        <strong>Codificação:</strong> UTF-8 com BOM para garantir compatibilidade com Excel.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

