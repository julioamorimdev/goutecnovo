-- Tabela para modelos de email
CREATE TABLE IF NOT EXISTS email_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do modelo',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug único',
  subject VARCHAR(255) NOT NULL COMMENT 'Assunto do email',
  body_html TEXT NOT NULL COMMENT 'Corpo do email (HTML)',
  body_text TEXT NULL COMMENT 'Corpo do email (texto plano)',
  template_type VARCHAR(50) NOT NULL DEFAULT 'custom' COMMENT 'Tipo: system, custom, product_welcome',
  is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Modelo do sistema (não pode ser excluído)',
  applicable_to VARCHAR(50) NULL COMMENT 'Aplicável a: all, specific_plans',
  applicable_ids JSON NULL COMMENT 'IDs de planos específicos',
  variables JSON NULL COMMENT 'Variáveis disponíveis em JSON',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Modelo ativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_template_slug (slug),
  KEY idx_template_type (template_type),
  KEY idx_template_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modelos de email padrão do sistema
INSERT IGNORE INTO email_templates (id, name, slug, subject, body_html, template_type, is_system, variables) VALUES
(1, 'Bem-vindo', 'welcome', 'Bem-vindo ao {{company_name}}!', '<h1>Bem-vindo, {{client_name}}!</h1><p>Obrigado por se cadastrar em {{company_name}}.</p>', 'system', 1, '["company_name", "client_name", "client_email"]'),
(2, 'Pedido Criado', 'order_created', 'Seu pedido #{{order_number}} foi criado', '<h1>Pedido Criado</h1><p>Seu pedido #{{order_number}} foi criado com sucesso.</p>', 'system', 1, '["order_number", "client_name", "order_total"]'),
(3, 'Fatura Gerada', 'invoice_generated', 'Nova fatura #{{invoice_number}}', '<h1>Nova Fatura</h1><p>Uma nova fatura #{{invoice_number}} foi gerada no valor de {{invoice_total}}.</p>', 'system', 1, '["invoice_number", "invoice_total", "due_date", "client_name"]'),
(4, 'Fatura Paga', 'invoice_paid', 'Fatura #{{invoice_number}} paga com sucesso', '<h1>Pagamento Confirmado</h1><p>Sua fatura #{{invoice_number}} foi paga com sucesso.</p>', 'system', 1, '["invoice_number", "payment_amount", "payment_date", "client_name"]'),
(5, 'Ticket Criado', 'ticket_created', 'Ticket #{{ticket_number}} criado', '<h1>Ticket Criado</h1><p>Seu ticket #{{ticket_number}} foi criado. Nossa equipe responderá em breve.</p>', 'system', 1, '["ticket_number", "ticket_subject", "client_name"]'),
(6, 'Ticket Respondido', 'ticket_replied', 'Resposta ao ticket #{{ticket_number}}', '<h1>Resposta ao Ticket</h1><p>Seu ticket #{{ticket_number}} recebeu uma resposta.</p>', 'system', 1, '["ticket_number", "ticket_subject", "reply_message", "client_name"]');

