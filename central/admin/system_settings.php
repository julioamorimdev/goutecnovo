<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Configurações Gerais do Sistema';
$active = 'system_settings';
require_once __DIR__ . '/partials/layout_start.php';

$activeTab = $_GET['tab'] ?? 'general';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === '_csrf' || $key === 'tab') continue;
            
            // Converter arrays para JSON
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            // Determinar tipo
            $settingType = 'text';
            if (is_numeric($value) && strpos($value, '.') === false) {
                $settingType = 'number';
            } elseif (in_array($value, ['0', '1', 'true', 'false'])) {
                $settingType = 'boolean';
                $value = in_array($value, ['1', 'true']) ? '1' : '0';
            }
            
            $stmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)");
            $stmt->execute([$key, $value, $settingType]);
        }
        
        db()->commit();
        $_SESSION['success'] = 'Configurações salvas com sucesso.';
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
    $stmt = db()->query("SELECT setting_key, setting_value, setting_type, setting_group FROM system_settings");
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

function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key]['value'] ?? $default;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Configurações Gerais do Sistema</h1>
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
                <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" 
                   href="?tab=general">Geral</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'localization' ? 'active' : '' ?>" 
                   href="?tab=localization">Localização</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'orders' ? 'active' : '' ?>" 
                   href="?tab=orders">Pedidos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'domains' ? 'active' : '' ?>" 
                   href="?tab=domains">Domínios</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'email' ? 'active' : '' ?>" 
                   href="?tab=email">Email</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'support' ? 'active' : '' ?>" 
                   href="?tab=support">Suporte</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'invoices' ? 'active' : '' ?>" 
                   href="?tab=invoices">Faturas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'credit' ? 'active' : '' ?>" 
                   href="?tab=credit">Crédito</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'affiliates' ? 'active' : '' ?>" 
                   href="?tab=affiliates">Afiliados</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" 
                   href="?tab=security">Segurança</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'social' ? 'active' : '' ?>" 
                   href="?tab=social">Social</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'other' ? 'active' : '' ?>" 
                   href="?tab=other">Outros</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Geral -->
            <?php if ($activeTab === 'general'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Configurações Gerais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Nome da Empresa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?= h(getSetting('company_name', 'GouTec')) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="company_email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="company_email" name="company_email" 
                                       value="<?= h(getSetting('company_email')) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="domain" class="form-label">Domínio Principal do Site <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="domain" name="domain" 
                                       value="<?= h(getSetting('domain')) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="system_url" class="form-label">URL do Sistema <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="system_url" name="system_url" 
                                       value="<?= h(getSetting('system_url')) ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="payment_text" class="form-label">Texto do Pagamento</label>
                                <textarea class="form-control" id="payment_text" name="payment_text" rows="2"><?= h(getSetting('payment_text')) ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="theme" class="form-label">Tema do Sistema</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="default" <?= getSetting('theme') === 'default' ? 'selected' : '' ?>>Padrão</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="records_per_page" class="form-label">Registros para Exibir por Página</label>
                                <input type="number" class="form-control" id="records_per_page" name="records_per_page" 
                                       value="<?= h(getSetting('records_per_page', '25')) ?>" min="10" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="activity_log_limit" class="form-label">Limitar Log das Atividades</label>
                                <input type="number" class="form-control" id="activity_log_limit" name="activity_log_limit" 
                                       value="<?= h(getSetting('activity_log_limit', '1000')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="enable_international_phone" name="enable_international_phone" 
                                           value="1" <?= getSetting('enable_international_phone') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_international_phone">
                                        Ativar interface internacional de telefone e formatação automática
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3">Modo de Manutenção</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                           value="1" <?= getSetting('maintenance_mode') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        Ativar Modo de Manutenção
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="maintenance_message" class="form-label">Mensagem do Modo de Manutenção</label>
                                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?= h(getSetting('maintenance_message')) ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="maintenance_redirect_url" class="form-label">URL de Redirecionamento do Modo de Manutenção</label>
                                <input type="url" class="form-control" id="maintenance_redirect_url" name="maintenance_redirect_url" 
                                       value="<?= h(getSetting('maintenance_redirect_url')) ?>" placeholder="Deixe vazio para mostrar mensagem">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Localização -->
            <?php if ($activeTab === 'localization'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações de Localização</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_format" class="form-label">Formato de Data</label>
                                <select class="form-select" id="date_format" name="date_format">
                                    <option value="d/m/Y" <?= getSetting('date_format') === 'd/m/Y' ? 'selected' : '' ?>>dd/mm/yyyy</option>
                                    <option value="Y-m-d" <?= getSetting('date_format') === 'Y-m-d' ? 'selected' : '' ?>>yyyy-mm-dd</option>
                                    <option value="m/d/Y" <?= getSetting('date_format') === 'm/d/Y' ? 'selected' : '' ?>>mm/dd/yyyy</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="default_country" class="form-label">País Padrão</label>
                                <select class="form-select" id="default_country" name="default_country">
                                    <option value="BR" <?= getSetting('default_country') === 'BR' ? 'selected' : '' ?>>Brasil</option>
                                    <option value="US" <?= getSetting('default_country') === 'US' ? 'selected' : '' ?>>Estados Unidos</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="default_language" class="form-label">Idioma</label>
                                <select class="form-select" id="default_language" name="default_language">
                                    <option value="pt-BR" <?= getSetting('default_language') === 'pt-BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="enable_language_menu" name="enable_language_menu" 
                                           value="1" <?= getSetting('enable_language_menu') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enable_language_menu">
                                        Ativar Menu de Idiomas
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="charset" class="form-label">Sistema de Caracteres</label>
                                <select class="form-select" id="charset" name="charset">
                                    <option value="UTF-8" <?= getSetting('charset') === 'UTF-8' ? 'selected' : '' ?>>UTF-8</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="remove_utf8_extended" name="remove_utf8_extended" 
                                           value="1" <?= getSetting('remove_utf8_extended') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="remove_utf8_extended">
                                        Remover automaticamente os caracteres UTF-8 de 4 bytes (emoticons)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pedidos -->
            <?php if ($activeTab === 'orders'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações de Pedidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Aprovação Automática de Pedidos</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="orders_auto_approve" value="1" <?= getSetting('orders_auto_approve') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Aprovar pedidos automaticamente</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Enviar Email ao Criar Pedido</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="orders_send_email" value="1" <?= getSetting('orders_send_email') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Enviar email ao cliente</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prazo para Cancelamento (dias)</label>
                                <input type="number" class="form-control" name="orders_cancellation_days" value="<?= h(getSetting('orders_cancellation_days', '7')) ?>" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Padrão do Pedido</label>
                                <select class="form-select" name="orders_default_status">
                                    <option value="pending" <?= getSetting('orders_default_status') === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="active" <?= getSetting('orders_default_status') === 'active' ? 'selected' : '' ?>>Ativo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Domínios -->
            <?php if ($activeTab === 'domains'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">Configurações de Domínios</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registrador Padrão</label>
                                <input type="text" class="form-control" name="domains_default_registrar" value="<?= h(getSetting('domains_default_registrar')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Renovação Automática</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="domains_auto_renew" value="1" <?= getSetting('domains_auto_renew') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Renovar domínios automaticamente</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dias Antes do Aviso de Expiração</label>
                                <input type="number" class="form-control" name="domains_expiry_warning_days" value="<?= h(getSetting('domains_expiry_warning_days', '30')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sincronização Automática</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="domains_auto_sync" value="1" <?= getSetting('domains_auto_sync') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Sincronizar com registrador</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Email -->
            <?php if ($activeTab === 'email'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações de Email</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Método de Envio</label>
                                <select class="form-select" name="email_sending_method">
                                    <option value="php" <?= getSetting('email_sending_method') === 'php' ? 'selected' : '' ?>>PHP Mail</option>
                                    <option value="smtp" <?= getSetting('email_sending_method') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email do Remetente</label>
                                <input type="email" class="form-control" name="email_from_address" value="<?= h(getSetting('email_from_address')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome do Remetente</label>
                                <input type="text" class="form-control" name="email_from_name" value="<?= h(getSetting('email_from_name')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="email_smtp_host" value="<?= h(getSetting('email_smtp_host')) ?>" placeholder="smtp.exemplo.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SMTP Porta</label>
                                <input type="number" class="form-control" name="email_smtp_port" value="<?= h(getSetting('email_smtp_port', '587')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SMTP Usuário</label>
                                <input type="text" class="form-control" name="email_smtp_username" value="<?= h(getSetting('email_smtp_username')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SMTP Senha</label>
                                <input type="password" class="form-control" name="email_smtp_password" value="<?= h(getSetting('email_smtp_password')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SMTP Segurança</label>
                                <select class="form-select" name="email_smtp_encryption">
                                    <option value="none" <?= getSetting('email_smtp_encryption') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                                    <option value="ssl" <?= getSetting('email_smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="tls" <?= getSetting('email_smtp_encryption') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Suporte -->
            <?php if ($activeTab === 'support'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Configurações de Suporte</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempo de Resposta Esperado (horas)</label>
                                <input type="number" class="form-control" name="support_response_time" value="<?= h(getSetting('support_response_time', '24')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Notificações de Novos Tickets</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="support_notifications" value="1" <?= getSetting('support_notifications') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Enviar notificações</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fechar Tickets Automaticamente (dias)</label>
                                <input type="number" class="form-control" name="support_auto_close_days" value="<?= h(getSetting('support_auto_close_days', '30')) ?>" min="0">
                                <small class="text-muted">0 = não fechar automaticamente</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Permitir Anexos</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="support_allow_attachments" value="1" <?= getSetting('support_allow_attachments') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Permitir anexos nos tickets</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Faturas -->
            <?php if ($activeTab === 'invoices'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações de Faturas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prefixo do Número da Fatura</label>
                                <input type="text" class="form-control" name="invoices_prefix" value="<?= h(getSetting('invoices_prefix', 'INV')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Próximo Número</label>
                                <input type="number" class="form-control" name="invoices_next_number" value="<?= h(getSetting('invoices_next_number', '1')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prazo de Vencimento (dias)</label>
                                <input type="number" class="form-control" name="invoices_due_days" value="<?= h(getSetting('invoices_due_days', '30')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Enviar Email ao Gerar Fatura</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="invoices_send_email" value="1" <?= getSetting('invoices_send_email') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Enviar automaticamente</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Crédito -->
            <?php if ($activeTab === 'credit'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações de Crédito</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Permitir Crédito de Cliente</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="credit_enabled" value="1" <?= getSetting('credit_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Habilitar sistema de crédito</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Aplicar Crédito Automaticamente</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="credit_auto_apply" value="1" <?= getSetting('credit_auto_apply') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Aplicar ao gerar fatura</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Afiliados -->
            <?php if ($activeTab === 'affiliates'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">Configurações de Afiliados</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sistema de Afiliados</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="affiliates_enabled" value="1" <?= getSetting('affiliates_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Habilitar sistema de afiliados</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Comissão Padrão (%)</label>
                                <input type="number" class="form-control" name="affiliates_commission" step="0.01" min="0" max="100" value="<?= h(getSetting('affiliates_commission', '10')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Valor Mínimo para Saque</label>
                                <input type="number" class="form-control" name="affiliates_minimum_payout" step="0.01" min="0" value="<?= h(getSetting('affiliates_minimum_payout', '50')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Segurança -->
            <?php if ($activeTab === 'security'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Configurações de Segurança</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tentativas de Login Permitidas</label>
                                <input type="number" class="form-control" name="security_login_attempts" value="<?= h(getSetting('security_login_attempts', '5')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempo de Bloqueio (minutos)</label>
                                <input type="number" class="form-control" name="security_lockout_time" value="<?= h(getSetting('security_lockout_time', '15')) ?>" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Forçar HTTPS</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="security_force_https" value="1" <?= getSetting('security_force_https') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Redirecionar para HTTPS</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Autenticação de Dois Fatores</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="security_2fa_enabled" value="1" <?= getSetting('security_2fa_enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Habilitar 2FA</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Social -->
            <?php if ($activeTab === 'social'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Configurações de Redes Sociais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Facebook URL</label>
                                <input type="url" class="form-control" name="social_facebook" value="<?= h(getSetting('social_facebook')) ?>" placeholder="https://facebook.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Twitter URL</label>
                                <input type="url" class="form-control" name="social_twitter" value="<?= h(getSetting('social_twitter')) ?>" placeholder="https://twitter.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Instagram URL</label>
                                <input type="url" class="form-control" name="social_instagram" value="<?= h(getSetting('social_instagram')) ?>" placeholder="https://instagram.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LinkedIn URL</label>
                                <input type="url" class="form-control" name="social_linkedin" value="<?= h(getSetting('social_linkedin')) ?>" placeholder="https://linkedin.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">YouTube URL</label>
                                <input type="url" class="form-control" name="social_youtube" value="<?= h(getSetting('social_youtube')) ?>" placeholder="https://youtube.com/...">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Outros -->
            <?php if ($activeTab === 'other'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Outras Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="other_timezone">
                                    <option value="America/Sao_Paulo" <?= getSetting('other_timezone') === 'America/Sao_Paulo' ? 'selected' : '' ?>>America/Sao_Paulo</option>
                                    <option value="UTC" <?= getSetting('other_timezone') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Formato de Data e Hora</label>
                                <input type="text" class="form-control" name="other_datetime_format" value="<?= h(getSetting('other_datetime_format', 'd/m/Y H:i')) ?>">
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

