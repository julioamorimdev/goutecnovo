-- Tabela para serviços addons
CREATE TABLE IF NOT EXISTS addons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do addon',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug único para URL',
  description TEXT NULL COMMENT 'Descrição completa do addon',
  short_description VARCHAR(500) NULL COMMENT 'Descrição curta',
  category VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'Categoria: ssl, backup, security, email, domain, other',
  icon_class VARCHAR(120) NULL COMMENT 'Classe do ícone (ex: las la-shield-alt)',
  price_type VARCHAR(20) NOT NULL DEFAULT 'one_time' COMMENT 'Tipo de preço: one_time, monthly, annual',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço do addon',
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de instalação',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda (BRL, USD, etc.)',
  billing_cycle VARCHAR(20) NULL COMMENT 'Ciclo de cobrança (se aplicável): monthly, quarterly, semiannual, annual',
  features JSON NULL COMMENT 'Recursos do addon em JSON',
  compatible_plans JSON NULL COMMENT 'IDs dos planos compatíveis (NULL = todos)',
  requires_order TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Requer pedido ativo',
  auto_setup TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Configuração automática',
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Addon em destaque',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_addon_slug (slug),
  KEY idx_addon_category (category),
  KEY idx_addon_sort (sort_order),
  KEY idx_addon_enabled (is_enabled),
  KEY idx_addon_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para addons vinculados a pedidos/serviços
CREATE TABLE IF NOT EXISTS order_addons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do pedido',
  addon_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do addon',
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  quantity INT NOT NULL DEFAULT 1 COMMENT 'Quantidade',
  price DECIMAL(10,2) NOT NULL COMMENT 'Preço no momento da compra',
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de instalação',
  billing_cycle VARCHAR(20) NULL COMMENT 'Ciclo de cobrança',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, suspended, cancelled',
  renewal_date DATE NULL COMMENT 'Data de renovação (se aplicável)',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_addon_order (order_id),
  KEY idx_order_addon_addon (addon_id),
  KEY idx_order_addon_client (client_id),
  KEY idx_order_addon_status (status),
  KEY idx_order_addon_renewal (renewal_date),
  CONSTRAINT fk_order_addon_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_addon_addon FOREIGN KEY (addon_id) REFERENCES addons(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_order_addon_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de addons comuns
INSERT IGNORE INTO addons (id, name, slug, description, short_description, category, icon_class, price_type, price, setup_fee, currency, billing_cycle, is_featured, sort_order, is_enabled)
VALUES
  (1, 'Certificado SSL', 'certificado-ssl', 'Certificado SSL para criptografia e segurança do site', 'Proteja seu site com SSL', 'ssl', 'las la-shield-alt', 'annual', 99.90, 0.00, 'BRL', 'annual', 1, 10, 1),
  (2, 'Backup Diário', 'backup-diario', 'Backup automático diário do seu site', 'Proteja seus dados com backup automático', 'backup', 'las la-database', 'monthly', 19.90, 0.00, 'BRL', 'monthly', 1, 20, 1),
  (3, 'Proteção DDoS', 'protecao-ddos', 'Proteção contra ataques DDoS', 'Mantenha seu site sempre online', 'security', 'las la-shield', 'monthly', 49.90, 0.00, 'BRL', 'monthly', 0, 30, 1),
  (4, 'Email Profissional', 'email-profissional', 'Contas de email profissionais adicionais', 'Email com seu domínio', 'email', 'las la-envelope', 'monthly', 9.90, 0.00, 'BRL', 'monthly', 0, 40, 1),
  (5, 'Domínio Adicional', 'dominio-adicional', 'Registro de domínio adicional', 'Adicione mais domínios ao seu plano', 'domain', 'las la-globe', 'annual', 39.90, 0.00, 'BRL', 'annual', 0, 50, 1),
  (6, 'CDN Global', 'cdn-global', 'Rede de distribuição de conteúdo global', 'Acelere seu site no mundo todo', 'other', 'las la-network-wired', 'monthly', 29.90, 0.00, 'BRL', 'monthly', 0, 60, 1);

