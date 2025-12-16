<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Configurações de Automações';
$active = 'automation_settings';
require_once __DIR__ . '/partials/layout_start.php';

$activeTab = $_GET['tab'] ?? 'scheduling';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === '_csrf' || $key === 'tab') continue;
            
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $settingType = 'text';
            if (is_numeric($value) && strpos($value, '.') === false) {
                $settingType = 'number';
            } elseif (in_array($value, ['0', '1', 'true', 'false'])) {
                $settingType = 'boolean';
                $value = in_array($value, ['1', 'true']) ? '1' : '0';
            }
            
            $stmt = db()->prepare("INSERT INTO automation_settings (setting_key, setting_value, setting_type) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)");
            $stmt->execute([$key, $value, $settingType]);
        }
        
        db()->commit();
        $_SESSION['success'] = 'Configurações de automação salvas com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . $activeTab);
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Erro ao salvar configurações: ' . $e->getMessage();
    }
}

// Buscar configurações
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT setting_key, setting_value, setting_type, setting_group FROM automation_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['setting_type'],
            'group' => $row['setting_group']
        ];
    }
} catch (Throwable $e) {
    $settings = [];
}

function getAutoSetting($key, $default = '') {
    global $settings;
    return $settings[$key]['value'] ?? $default;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Configurações de Automações</h1>
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

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'scheduling' ? 'active' : '' ?>" 
                   href="?tab=scheduling">Scheduling</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'module_functions' ? 'active' : '' ?>" 
                   href="?tab=module_functions">Funções do Módulo</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'billing' ? 'active' : '' ?>" 
                   href="?tab=billing">Faturamento</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'payment_capture' ? 'active' : '' ?>" 
                   href="?tab=payment_capture">Captura de Pagamento</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'currency_update' ? 'active' : '' ?>" 
                   href="?tab=currency_update">Atualização de Moeda</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'domain_reminder' ? 'active' : '' ?>" 
                   href="?tab=domain_reminder">Lembrete de Domínio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'domain_sync' ? 'active' : '' ?>" 
                   href="?tab=domain_sync">Sincronização de Domínio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'support_tickets' ? 'active' : '' ?>" 
                   href="?tab=support_tickets">Tickets de Suporte</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'data_retention' ? 'active' : '' ?>" 
                   href="?tab=data_retention">Retenção de Dados</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'misc' ? 'active' : '' ?>" 
                   href="?tab=misc">Miscelânea</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Scheduling -->
            <?php if ($activeTab === 'scheduling'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Scheduling</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cron_enabled" name="cron_enabled" 
                                           value="1" <?= getAutoSetting('cron_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="cron_enabled">
                                        Habilitar agendamento de tarefas (Cron)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cron_key" class="form-label">Chave de Segurança do Cron</label>
                                <input type="text" class="form-control" id="cron_key" name="cron_key" 
                                       value="<?= h(getAutoSetting('cron_key')) ?>" placeholder="Gere uma chave aleatória">
                                <small class="text-muted">Use esta chave na URL do cron para segurança.</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Funções do Módulo de Automação -->
            <?php if ($activeTab === 'module_functions'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Funções do Módulo de Automação</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="module_auto_setup" name="module_auto_setup" 
                                           value="1" <?= getAutoSetting('module_auto_setup') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="module_auto_setup">
                                        Configuração Automática
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="module_auto_suspend" name="module_auto_suspend" 
                                           value="1" <?= getAutoSetting('module_auto_suspend') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="module_auto_suspend">
                                        Suspensão Automática
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="module_auto_unsuspend" name="module_auto_unsuspend" 
                                           value="1" <?= getAutoSetting('module_auto_unsuspend') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="module_auto_unsuspend">
                                        Reativação Automática
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="module_auto_terminate" name="module_auto_terminate" 
                                           value="1" <?= getAutoSetting('module_auto_terminate') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="module_auto_terminate">
                                        Encerramento Automático
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações de Faturamento -->
            <?php if ($activeTab === 'billing'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações de Faturamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="billing_auto_generate" name="billing_auto_generate" 
                                           value="1" <?= getAutoSetting('billing_auto_generate', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="billing_auto_generate">
                                        Gerar Faturas Automaticamente
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="billing_generate_days_before" class="form-label">Dias Antes do Vencimento para Gerar</label>
                                <input type="number" class="form-control" id="billing_generate_days_before" name="billing_generate_days_before" 
                                       value="<?= h(getAutoSetting('billing_generate_days_before', '7')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="billing_retry_failed" name="billing_retry_failed" 
                                           value="1" <?= getAutoSetting('billing_retry_failed', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="billing_retry_failed">
                                        Tentar Novamente Faturas Falhadas
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="billing_retry_attempts" class="form-label">Tentativas de Cobrança</label>
                                <input type="number" class="form-control" id="billing_retry_attempts" name="billing_retry_attempts" 
                                       value="<?= h(getAutoSetting('billing_retry_attempts', '3')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="billing_retry_days" class="form-label">Dias Entre Tentativas</label>
                                <input type="number" class="form-control" id="billing_retry_days" name="billing_retry_days" 
                                       value="<?= h(getAutoSetting('billing_retry_days', '3')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações de captura de pagamento -->
            <?php if ($activeTab === 'payment_capture'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações de Captura de Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="payment_auto_capture" name="payment_auto_capture" 
                                           value="1" <?= getAutoSetting('payment_auto_capture', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="payment_auto_capture">
                                        Captura Automática de Pagamento
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="payment_capture_on_invoice" name="payment_capture_on_invoice" 
                                           value="1" <?= getAutoSetting('payment_capture_on_invoice', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="payment_capture_on_invoice">
                                        Capturar ao Gerar Fatura
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="payment_retry_failed" name="payment_retry_failed" 
                                           value="1" <?= getAutoSetting('payment_retry_failed', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="payment_retry_failed">
                                        Tentar Novamente Pagamentos Falhados
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações de Atualização Automática da Moeda -->
            <?php if ($activeTab === 'currency_update'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações de Atualização Automática da Moeda</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="currency_auto_update" name="currency_auto_update" 
                                           value="1" <?= getAutoSetting('currency_auto_update') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="currency_auto_update">
                                        Atualização Automática de Moedas
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency_update_frequency" class="form-label">Frequência de Atualização</label>
                                <select class="form-select" id="currency_update_frequency" name="currency_update_frequency">
                                    <option value="hourly" <?= getAutoSetting('currency_update_frequency') === 'hourly' ? 'selected' : '' ?>>A cada hora</option>
                                    <option value="daily" <?= getAutoSetting('currency_update_frequency') === 'daily' ? 'selected' : '' ?>>Diariamente</option>
                                    <option value="weekly" <?= getAutoSetting('currency_update_frequency') === 'weekly' ? 'selected' : '' ?>>Semanalmente</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency_api_provider" class="form-label">Provedor da API de Câmbio</label>
                                <select class="form-select" id="currency_api_provider" name="currency_api_provider">
                                    <option value="">Nenhum</option>
                                    <option value="fixer" <?= getAutoSetting('currency_api_provider') === 'fixer' ? 'selected' : '' ?>>Fixer.io</option>
                                    <option value="exchangerate" <?= getAutoSetting('currency_api_provider') === 'exchangerate' ? 'selected' : '' ?>>ExchangeRate-API</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency_api_key" class="form-label">Chave da API</label>
                                <input type="text" class="form-control" id="currency_api_key" name="currency_api_key" 
                                       value="<?= h(getAutoSetting('currency_api_key')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações do Lembrete de Domínio -->
            <?php if ($activeTab === 'domain_reminder'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações do Lembrete de Domínio</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="domain_reminder_enabled" name="domain_reminder_enabled" 
                                           value="1" <?= getAutoSetting('domain_reminder_enabled', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="domain_reminder_enabled">
                                        Habilitar Lembretes de Domínio
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="domain_reminder_days" class="form-label">Dias Antes do Vencimento</label>
                                <input type="text" class="form-control" id="domain_reminder_days" name="domain_reminder_days" 
                                       value="<?= h(getAutoSetting('domain_reminder_days', '30,15,7,1')) ?>" 
                                       placeholder="30,15,7,1">
                                <small class="text-muted">Separe por vírgula (ex: 30,15,7,1)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="domain_reminder_email_template" class="form-label">Template de Email</label>
                                <input type="text" class="form-control" id="domain_reminder_email_template" name="domain_reminder_email_template" 
                                       value="<?= h(getAutoSetting('domain_reminder_email_template', 'domain_expiry_reminder')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações de sincronização de domínio -->
            <?php if ($activeTab === 'domain_sync'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações de Sincronização de Domínio</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="domain_sync_enabled" name="domain_sync_enabled" 
                                           value="1" <?= getAutoSetting('domain_sync_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="domain_sync_enabled">
                                        Habilitar Sincronização de Domínios
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="domain_sync_frequency" class="form-label">Frequência de Sincronização</label>
                                <select class="form-select" id="domain_sync_frequency" name="domain_sync_frequency">
                                    <option value="hourly" <?= getAutoSetting('domain_sync_frequency') === 'hourly' ? 'selected' : '' ?>>A cada hora</option>
                                    <option value="daily" <?= getAutoSetting('domain_sync_frequency') === 'daily' ? 'selected' : '' ?>>Diariamente</option>
                                    <option value="weekly" <?= getAutoSetting('domain_sync_frequency') === 'weekly' ? 'selected' : '' ?>>Semanalmente</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="domain_sync_registrar" class="form-label">Registrador para Sincronizar</label>
                                <input type="text" class="form-control" id="domain_sync_registrar" name="domain_sync_registrar" 
                                       value="<?= h(getAutoSetting('domain_sync_registrar')) ?>" placeholder="Ex: Registro.br">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações dos Tickets de Suporte -->
            <?php if ($activeTab === 'support_tickets'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Configurações dos Tickets de Suporte</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ticket_auto_assign" name="ticket_auto_assign" 
                                           value="1" <?= getAutoSetting('ticket_auto_assign') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ticket_auto_assign">
                                        Atribuição Automática de Tickets
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ticket_auto_close_days" class="form-label">Dias para Fechar Automaticamente</label>
                                <input type="number" class="form-control" id="ticket_auto_close_days" name="ticket_auto_close_days" 
                                       value="<?= h(getAutoSetting('ticket_auto_close_days')) ?>" placeholder="0 = desabilitado">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ticket_auto_respond" name="ticket_auto_respond" 
                                           value="1" <?= getAutoSetting('ticket_auto_respond') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ticket_auto_respond">
                                        Resposta Automática
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ticket_escalation_enabled" name="ticket_escalation_enabled" 
                                           value="1" <?= getAutoSetting('ticket_escalation_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ticket_escalation_enabled">
                                        Escalação Automática
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ticket_escalation_hours" class="form-label">Horas para Escalar</label>
                                <input type="number" class="form-control" id="ticket_escalation_hours" name="ticket_escalation_hours" 
                                       value="<?= h(getAutoSetting('ticket_escalation_hours', '24')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações de retenção de dados -->
            <?php if ($activeTab === 'data_retention'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações de Retenção de Dados</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="data_retention_enabled" name="data_retention_enabled" 
                                           value="1" <?= getAutoSetting('data_retention_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="data_retention_enabled">
                                        Habilitar Retenção de Dados
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_retention_logs_days" class="form-label">Dias para Reter Logs</label>
                                <input type="number" class="form-control" id="data_retention_logs_days" name="data_retention_logs_days" 
                                       value="<?= h(getAutoSetting('data_retention_logs_days', '90')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_retention_emails_days" class="form-label">Dias para Reter Emails</label>
                                <input type="number" class="form-control" id="data_retention_emails_days" name="data_retention_emails_days" 
                                       value="<?= h(getAutoSetting('data_retention_emails_days', '180')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_retention_tickets_days" class="form-label">Dias para Reter Tickets</label>
                                <input type="number" class="form-control" id="data_retention_tickets_days" name="data_retention_tickets_days" 
                                       value="<?= h(getAutoSetting('data_retention_tickets_days', '365')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Miscelânea -->
            <?php if ($activeTab === 'misc'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Miscelânea</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled" 
                                           value="1" <?= getAutoSetting('auto_backup_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="auto_backup_enabled">
                                        Backup Automático
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="auto_backup_frequency" class="form-label">Frequência de Backup</label>
                                <select class="form-select" id="auto_backup_frequency" name="auto_backup_frequency">
                                    <option value="hourly" <?= getAutoSetting('auto_backup_frequency') === 'hourly' ? 'selected' : '' ?>>A cada hora</option>
                                    <option value="daily" <?= getAutoSetting('auto_backup_frequency') === 'daily' ? 'selected' : '' ?>>Diariamente</option>
                                    <option value="weekly" <?= getAutoSetting('auto_backup_frequency') === 'weekly' ? 'selected' : '' ?>>Semanalmente</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="notification_email" class="form-label">Email para Notificações</label>
                                <input type="email" class="form-control" id="notification_email" name="notification_email" 
                                       value="<?= h(getAutoSetting('notification_email')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="maintenance_notifications" name="maintenance_notifications" 
                                           value="1" <?= getAutoSetting('maintenance_notifications', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="maintenance_notifications">
                                        Notificações de Manutenção
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="las la-save me-1"></i> Salvar Configurações
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

