-- Tabela para configurações gerais do sistema
CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL COMMENT 'Chave da configuração',
  setting_value TEXT NULL COMMENT 'Valor da configuração',
  setting_group VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Grupo: general, localization, orders, domains, email, support, invoices, credit, affiliates, security, social, other',
  setting_type VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'Tipo: text, number, boolean, json',
  description TEXT NULL COMMENT 'Descrição da configuração',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_setting_key (setting_key),
  KEY idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group, setting_type, description) VALUES
-- Geral
('company_name', 'GouTec', 'general', 'text', 'Nome da empresa'),
('company_email', 'contato@goutec.com', 'general', 'text', 'Email principal da empresa'),
('domain', 'goutec.com', 'general', 'text', 'Domínio principal do site'),
('payment_text', 'Pagamento seguro via gateway de pagamento', 'general', 'text', 'Texto do Pagamento'),
('system_url', 'https://goutec.com', 'general', 'text', 'URL do Sistema'),
('theme', 'default', 'general', 'text', 'Tema do sistema'),
('activity_log_limit', '1000', 'general', 'number', 'Limitar Log das Atividades'),
('records_per_page', '25', 'general', 'number', 'Registros para Exibir por Página'),
('maintenance_mode', '0', 'general', 'boolean', 'Modo Manutenção'),
('maintenance_message', 'Sistema em manutenção. Voltaremos em breve.', 'general', 'text', 'Mensagem do Modo de Manutenção'),
('maintenance_redirect_url', '', 'general', 'text', 'URL de Redirecionamento do Modo de Manutenção'),
-- Localização
('date_format', 'd/m/Y', 'localization', 'text', 'Formato de data'),
('default_country', 'BR', 'localization', 'text', 'País padrão'),
('default_language', 'pt-BR', 'localization', 'text', 'Idioma padrão'),
('enable_language_menu', '0', 'localization', 'boolean', 'Ativar Menu de Idiomas'),
('charset', 'UTF-8', 'localization', 'text', 'Sistema de caracteres'),
('remove_utf8_extended', '0', 'localization', 'boolean', 'Remover Caracteres UTF-8 Estendidos'),
-- Telefone
('enable_international_phone', '0', 'general', 'boolean', 'Ativar interface internacional de telefone'),
-- Pedidos
('order_auto_activate', '0', 'orders', 'boolean', 'Ativar pedidos automaticamente'),
('order_auto_suspend_days', '0', 'orders', 'number', 'Dias para suspensão automática'),
-- Domínios
('domain_auto_register', '0', 'domains', 'boolean', 'Registro automático de domínios'),
('domain_auto_renew', '0', 'domains', 'boolean', 'Renovação automática de domínios'),
-- Email
('email_smtp_enabled', '0', 'email', 'boolean', 'Habilitar SMTP'),
('email_smtp_host', '', 'email', 'text', 'Servidor SMTP'),
('email_smtp_port', '587', 'email', 'number', 'Porta SMTP'),
('email_smtp_username', '', 'email', 'text', 'Usuário SMTP'),
('email_smtp_password', '', 'email', 'text', 'Senha SMTP'),
('email_smtp_encryption', 'tls', 'email', 'text', 'Criptografia SMTP'),
-- Suporte
('support_ticket_auto_assign', '0', 'support', 'boolean', 'Atribuição automática de tickets'),
('support_ticket_auto_close_days', '0', 'support', 'number', 'Dias para fechar tickets automaticamente'),
-- Faturas
('invoice_auto_generate', '0', 'invoices', 'boolean', 'Gerar faturas automaticamente'),
('invoice_due_days', '7', 'invoices', 'number', 'Dias para vencimento'),
-- Crédito
('credit_auto_apply', '0', 'credit', 'boolean', 'Aplicar crédito automaticamente'),
-- Afiliados
('affiliate_commission_percentage', '10.00', 'affiliates', 'number', 'Percentual de comissão'),
('affiliate_minimum_payout', '50.00', 'affiliates', 'number', 'Valor mínimo para saque'),
-- Segurança
('enable_2fa', '0', 'security', 'boolean', 'Habilitar autenticação de dois fatores'),
('password_min_length', '8', 'security', 'number', 'Tamanho mínimo da senha'),
('session_timeout', '3600', 'security', 'number', 'Timeout da sessão (segundos)'),
-- Social
('facebook_url', '', 'social', 'text', 'URL do Facebook'),
('twitter_url', '', 'social', 'text', 'URL do Twitter'),
('instagram_url', '', 'social', 'text', 'URL do Instagram'),
('linkedin_url', '', 'social', 'text', 'URL do LinkedIn');

