-- Tabela para configurações de automações
CREATE TABLE IF NOT EXISTS automation_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL COMMENT 'Chave da configuração',
  setting_value TEXT NULL COMMENT 'Valor da configuração',
  setting_group VARCHAR(50) NOT NULL DEFAULT 'scheduling' COMMENT 'Grupo: scheduling, module_functions, billing, payment_capture, currency_update, domain_reminder, domain_sync, support_tickets, data_retention, misc',
  setting_type VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'Tipo: text, number, boolean, json',
  description TEXT NULL COMMENT 'Descrição da configuração',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_automation_key (setting_key),
  KEY idx_automation_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão de automações
INSERT IGNORE INTO automation_settings (setting_key, setting_value, setting_group, setting_type, description) VALUES
-- Scheduling
('cron_enabled', '1', 'scheduling', 'boolean', 'Habilitar agendamento de tarefas'),
('cron_key', '', 'scheduling', 'text', 'Chave de segurança do cron'),
-- Funções do Módulo de Automação
('module_auto_setup', '0', 'module_functions', 'boolean', 'Configuração automática de módulos'),
('module_auto_suspend', '0', 'module_functions', 'boolean', 'Suspensão automática'),
('module_auto_unsuspend', '0', 'module_functions', 'boolean', 'Reativação automática'),
('module_auto_terminate', '0', 'module_functions', 'boolean', 'Encerramento automático'),
-- Configurações de Faturamento
('billing_auto_generate', '1', 'billing', 'boolean', 'Gerar faturas automaticamente'),
('billing_generate_days_before', '7', 'billing', 'number', 'Dias antes do vencimento para gerar'),
('billing_retry_failed', '1', 'billing', 'boolean', 'Tentar novamente faturas falhadas'),
('billing_retry_attempts', '3', 'billing', 'number', 'Tentativas de cobrança'),
('billing_retry_days', '3', 'billing', 'number', 'Dias entre tentativas'),
-- Configurações de captura de pagamento
('payment_auto_capture', '1', 'payment_capture', 'boolean', 'Captura automática de pagamento'),
('payment_capture_on_invoice', '1', 'payment_capture', 'boolean', 'Capturar ao gerar fatura'),
('payment_retry_failed', '1', 'payment_capture', 'boolean', 'Tentar novamente pagamentos falhados'),
-- Configurações de Atualização Automática da Moeda
('currency_auto_update', '0', 'currency_update', 'boolean', 'Atualização automática de moedas'),
('currency_update_frequency', 'daily', 'currency_update', 'text', 'Frequência de atualização'),
('currency_api_provider', '', 'currency_update', 'text', 'Provedor da API de câmbio'),
('currency_api_key', '', 'currency_update', 'text', 'Chave da API'),
-- Configurações do Lembrete de Domínio
('domain_reminder_enabled', '1', 'domain_reminder', 'boolean', 'Habilitar lembretes de domínio'),
('domain_reminder_days', '30,15,7,1', 'domain_reminder', 'text', 'Dias antes do vencimento para enviar'),
('domain_reminder_email_template', 'domain_expiry_reminder', 'domain_reminder', 'text', 'Template de email'),
-- Configurações de sincronização de domínio
('domain_sync_enabled', '0', 'domain_sync', 'boolean', 'Habilitar sincronização de domínios'),
('domain_sync_frequency', 'daily', 'domain_sync', 'text', 'Frequência de sincronização'),
('domain_sync_registrar', '', 'domain_sync', 'text', 'Registrador para sincronizar'),
-- Configurações dos Tickets de Suporte
('ticket_auto_assign', '0', 'support_tickets', 'boolean', 'Atribuição automática de tickets'),
('ticket_auto_close_days', '0', 'support_tickets', 'number', 'Dias para fechar automaticamente'),
('ticket_auto_respond', '0', 'support_tickets', 'boolean', 'Resposta automática'),
('ticket_escalation_enabled', '0', 'support_tickets', 'boolean', 'Escalação automática'),
('ticket_escalation_hours', '24', 'support_tickets', 'number', 'Horas para escalar'),
-- Configurações de retenção de dados
('data_retention_enabled', '0', 'data_retention', 'boolean', 'Habilitar retenção de dados'),
('data_retention_logs_days', '90', 'data_retention', 'number', 'Dias para reter logs'),
('data_retention_emails_days', '180', 'data_retention', 'number', 'Dias para reter emails'),
('data_retention_tickets_days', '365', 'data_retention', 'number', 'Dias para reter tickets'),
-- Miscelânea
('auto_backup_enabled', '0', 'misc', 'boolean', 'Backup automático'),
('auto_backup_frequency', 'daily', 'misc', 'text', 'Frequência de backup'),
('notification_email', '', 'misc', 'text', 'Email para notificações'),
('maintenance_notifications', '1', 'misc', 'boolean', 'Notificações de manutenção');

