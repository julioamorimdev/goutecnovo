<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Status da Base de Dados e Operações de Limpeza';
$active = 'database_maintenance';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = (int)($_SESSION['admin_user_id'] ?? 0);

// Processar operações de limpeza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $confirm = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';
    
    if (!$confirm) {
        $_SESSION['error'] = 'Confirmação necessária para executar esta operação.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $result = null;
            
            switch ($action) {
                case 'clear_portal_logs':
                    // Esvaziar Log dos Portais
                    $result = db()->exec("DELETE FROM portal_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $_SESSION['success'] = "Log dos portais limpo. {$result} registros removidos.";
                    break;
                    
                case 'clear_email_import_logs':
                    // Esvaziar Log das Importações dos Tickets por Email
                    $result = db()->exec("DELETE FROM email_import_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $_SESSION['success'] = "Log das importações de tickets por email limpo. {$result} registros removidos.";
                    break;
                    
                case 'clear_whois_logs':
                    // Esvaziar Log das Pesquisas WHOIS
                    $result = db()->exec("DELETE FROM whois_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $_SESSION['success'] = "Log das pesquisas WHOIS limpo. {$result} registros removidos.";
                    break;
                    
                case 'clear_model_cache':
                    // Esvaziar Cache dos Modelos
                    $cacheDir = __DIR__ . '/../storage/cache';
                    $cleared = 0;
                    if (is_dir($cacheDir)) {
                        $files = glob($cacheDir . '/*');
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                                $cleared++;
                            }
                        }
                    }
                    $_SESSION['success'] = "Cache dos modelos limpo. {$cleared} arquivos removidos.";
                    break;
                    
                case 'prune_client_activity_logs':
                    // Desbastar Logs das Atividades dos Clientes (manter últimos 90 dias)
                    $result = db()->exec("DELETE FROM client_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                    $_SESSION['success'] = "Logs de atividades dos clientes desbastados. {$result} registros removidos.";
                    break;
                    
                case 'prune_saved_emails':
                    // Desbastar Emails Salvos (manter últimos 180 dias)
                    $result = db()->exec("DELETE FROM saved_emails WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
                    $_SESSION['success'] = "Emails salvos desbastados. {$result} registros removidos.";
                    break;
                    
                case 'prune_ticket_attachments':
                    // Anexos de ingressos Prune (manter últimos 365 dias)
                    // Primeiro, buscar IDs de tickets antigos
                    $stmt = db()->prepare("SELECT id FROM tickets WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
                    $stmt->execute();
                    $oldTicketIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($oldTicketIds)) {
                        $placeholders = implode(',', array_fill(0, count($oldTicketIds), '?'));
                        $stmt = db()->prepare("SELECT attachments FROM ticket_replies WHERE ticket_id IN ({$placeholders})");
                        $stmt->execute($oldTicketIds);
                        $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $deletedFiles = 0;
                        foreach ($attachments as $attachmentJson) {
                            if ($attachmentJson) {
                                $files = json_decode($attachmentJson, true);
                                if (is_array($files)) {
                                    foreach ($files as $file) {
                                        $filePath = __DIR__ . '/../' . ltrim($file, '/');
                                        if (file_exists($filePath) && is_file($filePath)) {
                                            unlink($filePath);
                                            $deletedFiles++;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Remover referências de anexos antigos
                        $stmt = db()->prepare("UPDATE ticket_replies SET attachments = NULL WHERE ticket_id IN ({$placeholders})");
                        $stmt->execute($oldTicketIds);
                        
                        $_SESSION['success'] = "Anexos de tickets antigos removidos. {$deletedFiles} arquivos deletados.";
                    } else {
                        $_SESSION['success'] = "Nenhum anexo antigo encontrado para remover.";
                    }
                    break;
                    
                default:
                    $_SESSION['error'] = 'Operação inválida.';
                    break;
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao executar operação: ' . $e->getMessage();
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Obter status da base de dados
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Informações gerais do banco
    $dbInfo = [
        'version' => db()->query("SELECT VERSION() as version")->fetch()['version'],
        'database' => db()->query("SELECT DATABASE() as db")->fetch()['db'],
        'charset' => db()->query("SELECT @@character_set_database as charset")->fetch()['charset'],
        'collation' => db()->query("SELECT @@collation_database as collation")->fetch()['collation'],
    ];
    
    // Tamanho do banco de dados
    $stmt = db()->query("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $dbSize = $stmt->fetch();
    $dbInfo['size_mb'] = (float)($dbSize['size_mb'] ?? 0);
    
    // Contagem de tabelas
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE()");
    $dbInfo['table_count'] = (int)$stmt->fetch()['cnt'];
    
    // Estatísticas de tabelas principais
    $tables = [
        'clients' => 'Clientes',
        'orders' => 'Pedidos',
        'invoices' => 'Faturas',
        'tickets' => 'Tickets',
        'ticket_replies' => 'Respostas de Tickets',
        'plans' => 'Planos',
        'admin_users' => 'Administradores',
        'calendar_events' => 'Eventos do Calendário',
        'todo_items' => 'Itens a Fazer',
    ];
    
    $tableStats = [];
    foreach ($tables as $table => $label) {
        try {
            $stmt = db()->query("SELECT COUNT(*) as cnt FROM {$table}");
            $count = (int)$stmt->fetch()['cnt'];
            
            // Tamanho da tabela
            $stmt = db()->query("SELECT 
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = '{$table}'");
            $size = $stmt->fetch();
            
            $tableStats[$table] = [
                'label' => $label,
                'count' => $count,
                'size_mb' => (float)($size['size_mb'] ?? 0),
            ];
        } catch (Throwable $e) {
            $tableStats[$table] = [
                'label' => $label,
                'count' => 0,
                'size_mb' => 0,
            ];
        }
    }
    
    // Verificar existência de tabelas de logs
    $logTables = [
        'portal_logs' => 'Log dos Portais',
        'email_import_logs' => 'Log das Importações de Tickets por Email',
        'whois_logs' => 'Log das Pesquisas WHOIS',
        'client_activity_logs' => 'Logs das Atividades dos Clientes',
        'saved_emails' => 'Emails Salvos',
    ];
    
    $logTableStats = [];
    foreach ($logTables as $table => $label) {
        try {
            $stmt = db()->query("SELECT COUNT(*) as cnt FROM {$table}");
            $count = (int)$stmt->fetch()['cnt'];
            
            $stmt = db()->query("SELECT 
                MIN(created_at) as oldest,
                MAX(created_at) as newest
                FROM {$table}");
            $dates = $stmt->fetch();
            
            $logTableStats[$table] = [
                'label' => $label,
                'count' => $count,
                'oldest' => $dates['oldest'] ?? null,
                'newest' => $dates['newest'] ?? null,
            ];
        } catch (Throwable $e) {
            $logTableStats[$table] = [
                'label' => $label,
                'count' => 0,
                'oldest' => null,
                'newest' => null,
                'exists' => false,
            ];
        }
    }
    
    // Verificar cache
    $cacheDir = __DIR__ . '/../storage/cache';
    $cacheFiles = 0;
    $cacheSize = 0;
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        $cacheFiles = count($files);
        foreach ($files as $file) {
            if (is_file($file)) {
                $cacheSize += filesize($file);
            }
        }
    }
    $cacheSizeMB = round($cacheSize / 1024 / 1024, 2);
    
    // Estatísticas de anexos de tickets
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM ticket_replies WHERE attachments IS NOT NULL");
    $ticketAttachmentsCount = (int)$stmt->fetch()['cnt'];
    
} catch (Throwable $e) {
    $dbInfo = ['version' => 'N/A', 'database' => 'N/A', 'size_mb' => 0, 'table_count' => 0];
    $tableStats = [];
    $logTableStats = [];
    $cacheFiles = 0;
    $cacheSizeMB = 0;
    $ticketAttachmentsCount = 0;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Status da Base de Dados e Operações de Limpeza</h1>
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

    <!-- Status da Base de Dados -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="las la-database me-2"></i> Status da Base de Dados</h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Versão MySQL</h6>
                        <h4 class="mb-0 text-primary"><?= h($dbInfo['version']) ?></h4>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Banco de Dados</h6>
                        <h4 class="mb-0 text-info"><?= h($dbInfo['database']) ?></h4>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Tamanho Total</h6>
                        <h4 class="mb-0 text-success"><?= number_format($dbInfo['size_mb'], 2) ?> MB</h4>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center p-3 bg-light rounded">
                        <h6 class="text-muted mb-1">Total de Tabelas</h6>
                        <h4 class="mb-0 text-warning"><?= number_format($dbInfo['table_count']) ?></h4>
                    </div>
                </div>
            </div>

            <h6 class="mb-3">Estatísticas das Tabelas Principais</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tabela</th>
                            <th>Registros</th>
                            <th>Tamanho (MB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableStats as $table => $stats): ?>
                            <tr>
                                <td><strong><?= h($stats['label']) ?></strong></td>
                                <td><?= number_format($stats['count']) ?></td>
                                <td><?= number_format($stats['size_mb'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Operações de Limpeza Simples -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="las la-broom me-2"></i> Operações de Limpeza Simples</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">As operações abaixo removem registros com mais de 30 dias.</p>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title">Esvaziar Log dos Portais</h6>
                            <p class="card-text small text-muted">
                                <?php if (isset($logTableStats['portal_logs'])): ?>
                                    <strong>Registros:</strong> <?= number_format($logTableStats['portal_logs']['count']) ?><br>
                                    <?php if ($logTableStats['portal_logs']['oldest']): ?>
                                        <strong>Mais antigo:</strong> <?= date('d/m/Y', strtotime($logTableStats['portal_logs']['oldest'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tabela não encontrada.
                                <?php endif; ?>
                            </p>
                            <form method="POST" onsubmit="return confirmOperation('Esvaziar Log dos Portais')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear_portal_logs">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="las la-trash me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title">Esvaziar Log das Importações dos Tickets por Email</h6>
                            <p class="card-text small text-muted">
                                <?php if (isset($logTableStats['email_import_logs'])): ?>
                                    <strong>Registros:</strong> <?= number_format($logTableStats['email_import_logs']['count']) ?><br>
                                    <?php if ($logTableStats['email_import_logs']['oldest']): ?>
                                        <strong>Mais antigo:</strong> <?= date('d/m/Y', strtotime($logTableStats['email_import_logs']['oldest'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tabela não encontrada.
                                <?php endif; ?>
                            </p>
                            <form method="POST" onsubmit="return confirmOperation('Esvaziar Log das Importações dos Tickets por Email')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear_email_import_logs">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="las la-trash me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title">Esvaziar Log das Pesquisas WHOIS</h6>
                            <p class="card-text small text-muted">
                                <?php if (isset($logTableStats['whois_logs'])): ?>
                                    <strong>Registros:</strong> <?= number_format($logTableStats['whois_logs']['count']) ?><br>
                                    <?php if ($logTableStats['whois_logs']['oldest']): ?>
                                        <strong>Mais antigo:</strong> <?= date('d/m/Y', strtotime($logTableStats['whois_logs']['oldest'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tabela não encontrada.
                                <?php endif; ?>
                            </p>
                            <form method="POST" onsubmit="return confirmOperation('Esvaziar Log das Pesquisas WHOIS')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear_whois_logs">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="las la-trash me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="card-title">Esvaziar Cache dos Modelos</h6>
                            <p class="card-text small text-muted">
                                <strong>Arquivos:</strong> <?= number_format($cacheFiles) ?><br>
                                <strong>Tamanho:</strong> <?= number_format($cacheSizeMB, 2) ?> MB
                            </p>
                            <form method="POST" onsubmit="return confirmOperation('Esvaziar Cache dos Modelos')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear_model_cache">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="las la-trash me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Operações de Limpeza Avançadas -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="las la-exclamation-triangle me-2"></i> Operações de Limpeza Avançadas</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <i class="las la-exclamation-triangle me-2"></i>
                <strong>Atenção!</strong> Estas operações são irreversíveis. Certifique-se de que deseja executá-las.
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-body">
                            <h6 class="card-title">Desbastar Logs das Atividades dos Clientes</h6>
                            <p class="card-text small text-muted">
                                Remove logs com mais de 90 dias.<br>
                                <?php if (isset($logTableStats['client_activity_logs'])): ?>
                                    <strong>Registros:</strong> <?= number_format($logTableStats['client_activity_logs']['count']) ?><br>
                                    <?php if ($logTableStats['client_activity_logs']['oldest']): ?>
                                        <strong>Mais antigo:</strong> <?= date('d/m/Y', strtotime($logTableStats['client_activity_logs']['oldest'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tabela não encontrada.
                                <?php endif; ?>
                            </p>
                            <form method="POST" onsubmit="return confirmAdvancedOperation('Desbastar Logs das Atividades dos Clientes')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="prune_client_activity_logs">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="las la-cut me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-body">
                            <h6 class="card-title">Desbastar Emails Salvos</h6>
                            <p class="card-text small text-muted">
                                Remove emails com mais de 180 dias.<br>
                                <?php if (isset($logTableStats['saved_emails'])): ?>
                                    <strong>Registros:</strong> <?= number_format($logTableStats['saved_emails']['count']) ?><br>
                                    <?php if ($logTableStats['saved_emails']['oldest']): ?>
                                        <strong>Mais antigo:</strong> <?= date('d/m/Y', strtotime($logTableStats['saved_emails']['oldest'])) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tabela não encontrada.
                                <?php endif; ?>
                            </p>
                            <form method="POST" onsubmit="return confirmAdvancedOperation('Desbastar Emails Salvos')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="prune_saved_emails">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="las la-cut me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-body">
                            <h6 class="card-title">Anexos de Ingressos Prune</h6>
                            <p class="card-text small text-muted">
                                Remove anexos de tickets com mais de 365 dias.<br>
                                <strong>Respostas com anexos:</strong> <?= number_format($ticketAttachmentsCount) ?>
                            </p>
                            <form method="POST" onsubmit="return confirmAdvancedOperation('Remover Anexos de Tickets Antigos')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="prune_ticket_attachments">
                                <input type="hidden" name="confirm" value="yes">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="las la-cut me-1"></i> Executar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmOperation(operation) {
    return confirm(`Tem certeza que deseja executar: ${operation}?\n\nEsta operação removerá registros com mais de 30 dias.`);
}

function confirmAdvancedOperation(operation) {
    return confirm(`⚠️ ATENÇÃO ⚠️\n\nTem certeza que deseja executar: ${operation}?\n\nEsta operação é IRREVERSÍVEL e pode remover dados importantes.\n\nDigite "SIM" para confirmar.`) && 
           prompt('Digite "SIM" para confirmar:') === 'SIM';
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

