<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Exportação em Lote de Faturas PDF';
$active = 'batch_pdf_export';
require_once __DIR__ . '/partials/layout_start.php';

// Processar exportação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $invoiceIds = $_POST['invoice_ids'] ?? [];
    $exportFormat = $_POST['export_format'] ?? 'zip'; // zip ou individual
    
    if (empty($invoiceIds)) {
        $_SESSION['error'] = 'Selecione pelo menos uma fatura para exportar.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Buscar faturas selecionadas
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $stmt = db()->prepare("SELECT i.*, 
                                         c.first_name, c.last_name, c.email as client_email, 
                                         c.phone as client_phone, c.company_name, c.address, c.city, c.state, c.zip_code, c.country,
                                         o.order_number
                                  FROM invoices i
                                  LEFT JOIN clients c ON i.client_id = c.id
                                  LEFT JOIN orders o ON i.order_id = o.id
                                  WHERE i.id IN ({$placeholders})
                                  ORDER BY i.invoice_number");
            $stmt->execute($invoiceIds);
            $invoices = $stmt->fetchAll();
            
            if (empty($invoices)) {
                $_SESSION['error'] = 'Nenhuma fatura encontrada.';
            } else {
                // Verificar se TCPDF está disponível
                $useTcpdf = class_exists('TCPDF');
                
                if ($exportFormat === 'zip' && $useTcpdf) {
                    // Gerar ZIP com múltiplos PDFs
                    $zipFile = sys_get_temp_dir() . '/invoices_' . date('Y-m-d_His') . '.zip';
                    $zip = new ZipArchive();
                    
                    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        foreach ($invoices as $invoice) {
                            $pdfContent = generateInvoicePDF($invoice, $useTcpdf);
                            $zip->addFromString('INV-' . $invoice['invoice_number'] . '.pdf', $pdfContent);
                        }
                        $zip->close();
                        
                        // Enviar arquivo ZIP
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="faturas_' . date('Y-m-d_His') . '.zip"');
                        header('Content-Length: ' . filesize($zipFile));
                        readfile($zipFile);
                        unlink($zipFile);
                        exit;
                    } else {
                        $_SESSION['error'] = 'Erro ao criar arquivo ZIP.';
                    }
                } else {
                    // Gerar PDF individual (primeira fatura)
                    $invoice = $invoices[0];
                    $pdfContent = generateInvoicePDF($invoice, $useTcpdf);
                    
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="INV-' . $invoice['invoice_number'] . '.pdf"');
                    header('Content-Length: ' . strlen($pdfContent));
                    echo $pdfContent;
                    exit;
                }
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao exportar faturas: ' . $e->getMessage();
        }
    }
}

// Função para gerar PDF da fatura
function generateInvoicePDF(array $invoice, bool $useTcpdf = false): string {
    if ($useTcpdf && class_exists('TCPDF')) {
        return generateInvoicePDF_TCPDF($invoice);
    } else {
        return generateInvoicePDF_HTML($invoice);
    }
}

// Gerar PDF usando TCPDF
function generateInvoicePDF_TCPDF(array $invoice): string {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Goutec Novo');
    $pdf->SetAuthor('Goutec Novo');
    $pdf->SetTitle('Fatura ' . $invoice['invoice_number']);
    $pdf->SetSubject('Fatura');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    
    // HTML do PDF
    $html = getInvoiceHTML($invoice);
    $pdf->writeHTML($html, true, false, true, false, '');
    
    return $pdf->Output('', 'S');
}

// Gerar PDF usando HTML (fallback)
function generateInvoicePDF_HTML(array $invoice): string {
    $html = getInvoiceHTML($invoice);
    // Retornar HTML que pode ser convertido para PDF pelo navegador
    return $html;
}

// HTML da fatura
function getInvoiceHTML(array $invoice): string {
    $clientName = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''));
    $clientEmail = $invoice['client_email'] ?? '';
    $clientPhone = $invoice['client_phone'] ?? '';
    $clientCompany = $invoice['company_name'] ?? '';
    $clientAddress = $invoice['address'] ?? '';
    $clientCity = $invoice['city'] ?? '';
    $clientState = $invoice['state'] ?? '';
    $clientZip = $invoice['zip_code'] ?? '';
    $clientCountry = $invoice['country'] ?? '';
    
    $statusLabels = [
        'unpaid' => 'Não Paga',
        'paid' => 'Paga',
        'cancelled' => 'Cancelada',
        'refunded' => 'Reembolsada'
    ];
    
    $status = $statusLabels[$invoice['status']] ?? ucfirst($invoice['status']);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
            .header { margin-bottom: 30px; }
            .header h1 { margin: 0; color: #333; }
            .invoice-info { margin-bottom: 30px; }
            .invoice-info table { width: 100%; border-collapse: collapse; }
            .invoice-info td { padding: 5px; }
            .invoice-info .label { font-weight: bold; width: 150px; }
            .client-info, .invoice-details { margin-bottom: 30px; }
            .client-info h3, .invoice-details h3 { margin-top: 0; color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .items-table th { background-color: #f2f2f2; font-weight: bold; }
            .items-table .text-right { text-align: right; }
            .totals { margin-top: 20px; }
            .totals table { width: 300px; margin-left: auto; border-collapse: collapse; }
            .totals td { padding: 5px; }
            .totals .label { font-weight: bold; text-align: right; }
            .totals .value { text-align: right; }
            .totals .total-row { border-top: 2px solid #333; font-weight: bold; font-size: 14px; }
            .footer { margin-top: 50px; font-size: 10px; color: #666; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>FATURA</h1>
            <p>Número: <strong><?= h($invoice['invoice_number']) ?></strong></p>
        </div>
        
        <div class="invoice-info">
            <table>
                <tr>
                    <td class="label">Data de Emissão:</td>
                    <td><?= date('d/m/Y', strtotime($invoice['created_at'])) ?></td>
                </tr>
                <tr>
                    <td class="label">Data de Vencimento:</td>
                    <td><?= $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : 'N/A' ?></td>
                </tr>
                <tr>
                    <td class="label">Status:</td>
                    <td><strong><?= h($status) ?></strong></td>
                </tr>
                <?php if ($invoice['paid_date']): ?>
                <tr>
                    <td class="label">Data de Pagamento:</td>
                    <td><?= date('d/m/Y', strtotime($invoice['paid_date'])) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['payment_method']): ?>
                <tr>
                    <td class="label">Método de Pagamento:</td>
                    <td><?= h($invoice['payment_method']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['order_number']): ?>
                <tr>
                    <td class="label">Pedido:</td>
                    <td><?= h($invoice['order_number']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="client-info">
            <h3>Dados do Cliente</h3>
            <p><strong><?= h($clientName) ?></strong></p>
            <?php if ($clientCompany): ?>
                <p><?= h($clientCompany) ?></p>
            <?php endif; ?>
            <p><?= h($clientEmail) ?></p>
            <?php if ($clientPhone): ?>
                <p>Telefone: <?= h($clientPhone) ?></p>
            <?php endif; ?>
            <?php if ($clientAddress): ?>
                <p>
                    <?= h($clientAddress) ?><br>
                    <?php if ($clientCity): ?>
                        <?= h($clientCity) ?><?= $clientState ? ', ' . h($clientState) : '' ?><?= $clientZip ? ' - ' . h($clientZip) : '' ?><br>
                    <?php endif; ?>
                    <?= $clientCountry ? h($clientCountry) : '' ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="invoice-details">
            <h3>Itens da Fatura</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="text-right">Quantidade</th>
                        <th class="text-right">Valor Unitário</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Serviço de Hospedagem</td>
                        <td class="text-right">1</td>
                        <td class="text-right">R$ <?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?></td>
                        <td class="text-right">R$ <?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="totals">
                <table>
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="value">R$ <?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                    <?php if ((float)$invoice['tax'] > 0): ?>
                    <tr>
                        <td class="label">Impostos:</td>
                        <td class="value">R$ <?= number_format((float)$invoice['tax'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td class="label">Total:</td>
                        <td class="value">R$ <?= number_format((float)$invoice['total'], 2, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($invoice['notes']): ?>
        <div class="notes" style="margin-top: 30px;">
            <h3>Observações</h3>
            <p><?= nl2br(h($invoice['notes'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Esta é uma fatura gerada automaticamente pelo sistema.</p>
            <p>Para mais informações, entre em contato conosco.</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Buscar faturas para listagem
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $clientFilter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['unpaid', 'paid', 'cancelled', 'refunded'], true)) {
        $where[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($clientFilter > 0) {
        $where[] = "i.client_id = ?";
        $params[] = $clientFilter;
    }
    
    if ($dateFrom) {
        $where[] = "DATE(i.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "DATE(i.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search !== '') {
        $where[] = "(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT i.*, 
                   c.first_name, c.last_name, c.email as client_email,
                   o.order_number
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN orders o ON i.order_id = o.id
            {$whereClause}
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 500";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    // Buscar clientes para filtro
    $stmt = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name LIMIT 100");
    $clients = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $invoices = [];
    $clients = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Exportação em Lote de Faturas PDF</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, nome, email...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Não Paga</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paga</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Reembolsada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="client_id" class="form-label">Cliente</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter === (int)$client['id'] ? 'selected' : '' ?>>
                                <?= h($client['first_name'] . ' ' . $client['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulário de Exportação -->
    <form method="POST" id="exportForm">
        <?= csrf_field() ?>
        <input type="hidden" name="export_pdf" value="1">
        
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Faturas (<?= count($invoices) ?>)</h5>
                <div>
                    <button type="button" class="btn btn-light btn-sm" onclick="selectAll()">
                        <i class="las la-check-square"></i> Selecionar Todas
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="deselectAll()">
                        <i class="las la-square"></i> Desmarcar Todas
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($invoices)): ?>
                    <p class="text-muted text-center mb-0">Nenhuma fatura encontrada.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                    </th>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Vencimento</th>
                                    <th>Pagamento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): 
                                    $statusLabels = [
                                        'unpaid' => ['label' => 'Não Paga', 'class' => 'warning'],
                                        'paid' => ['label' => 'Paga', 'class' => 'success'],
                                        'cancelled' => ['label' => 'Cancelada', 'class' => 'secondary'],
                                        'refunded' => ['label' => 'Reembolsada', 'class' => 'info']
                                    ];
                                    $statusInfo = $statusLabels[$invoice['status']] ?? ['label' => ucfirst($invoice['status']), 'class' => 'secondary'];
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="invoice_ids[]" value="<?= $invoice['id'] ?>" class="invoice-checkbox">
                                        </td>
                                        <td><strong><?= h($invoice['invoice_number']) ?></strong></td>
                                        <td>
                                            <?= h(trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))) ?>
                                            <br><small class="text-muted"><?= h($invoice['client_email']) ?></small>
                                        </td>
                                        <td><strong>R$ <?= number_format((float)$invoice['total'], 2, ',', '.') ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= $statusInfo['class'] ?>">
                                                <?= h($statusInfo['label']) ?>
                                            </span>
                                        </td>
                                        <td><?= $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : 'N/A' ?></td>
                                        <td><?= $invoice['paid_date'] ? date('d/m/Y', strtotime($invoice['paid_date'])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($invoices)): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label for="export_format" class="form-label">Formato de Exportação</label>
                            <select class="form-select" id="export_format" name="export_format">
                                <option value="zip">ZIP (Múltiplos PDFs)</option>
                                <option value="individual">Individual (Primeira selecionada)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <span class="me-2">Selecionadas: <strong id="selectedCount">0</strong></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="submit" class="btn btn-success btn-lg" id="exportBtn" disabled>
                                <i class="las la-file-pdf me-1"></i> Exportar PDFs
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.invoice-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('exportBtn').disabled = checked === 0;
}

// Atualizar contador quando checkboxes mudarem
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    updateSelectedCount();
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

