-- Tabela para itens faturáveis
CREATE TABLE IF NOT EXISTS billable_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL COMMENT 'Código do item',
  name VARCHAR(255) NOT NULL COMMENT 'Nome do item',
  description TEXT NULL COMMENT 'Descrição do item',
  category VARCHAR(50) NOT NULL DEFAULT 'service' COMMENT 'Categoria: service, product, license, other',
  unit VARCHAR(20) NOT NULL DEFAULT 'unit' COMMENT 'Unidade: unit, hour, day, month, year',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço unitário',
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de imposto (%)',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda',
  is_recurring TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Item recorrente',
  billing_cycle VARCHAR(20) NULL COMMENT 'Ciclo de cobrança (se recorrente): monthly, quarterly, semiannual, annual',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Item ativo/inativo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_item_code (code),
  KEY idx_item_category (category),
  KEY idx_item_enabled (is_enabled),
  KEY idx_item_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para orçamentos
CREATE TABLE IF NOT EXISTS quotations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  quotation_number VARCHAR(50) NOT NULL COMMENT 'Número do orçamento',
  title VARCHAR(255) NULL COMMENT 'Título do orçamento',
  description TEXT NULL COMMENT 'Descrição/observações',
  status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'Status: draft, sent, accepted, rejected, expired, converted',
  valid_until DATE NULL COMMENT 'Válido até',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal',
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Desconto',
  discount_type VARCHAR(20) NULL COMMENT 'Tipo de desconto: percentage, fixed',
  tax DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Impostos',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda',
  notes TEXT NULL COMMENT 'Notas internas',
  terms TEXT NULL COMMENT 'Termos e condições',
  sent_at TIMESTAMP NULL COMMENT 'Data de envio',
  accepted_at TIMESTAMP NULL COMMENT 'Data de aceitação',
  converted_to_order_id BIGINT UNSIGNED NULL COMMENT 'ID do pedido convertido',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_quotation_number (quotation_number),
  KEY idx_quotation_client (client_id),
  KEY idx_quotation_status (status),
  KEY idx_quotation_valid_until (valid_until),
  KEY idx_quotation_created (created_at),
  CONSTRAINT fk_quotation_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_quotation_order FOREIGN KEY (converted_to_order_id) REFERENCES orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_quotation_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para itens do orçamento
CREATE TABLE IF NOT EXISTS quotation_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do orçamento',
  billable_item_id BIGINT UNSIGNED NULL COMMENT 'ID do item faturável (opcional)',
  description VARCHAR(255) NOT NULL COMMENT 'Descrição do item',
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantidade',
  unit VARCHAR(20) NOT NULL DEFAULT 'unit' COMMENT 'Unidade',
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço unitário',
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de imposto (%)',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal do item',
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do imposto',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total do item',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quotation_item_quotation (quotation_id),
  KEY idx_quotation_item_billable (billable_item_id),
  KEY idx_quotation_item_sort (sort_order),
  CONSTRAINT fk_quotation_item_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quotation_item_billable FOREIGN KEY (billable_item_id) REFERENCES billable_items(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de itens faturáveis
INSERT IGNORE INTO billable_items (id, code, name, description, category, unit, price, tax_rate, currency, is_recurring, billing_cycle, is_enabled, sort_order)
VALUES
  (1, 'SVC-001', 'Configuração Inicial', 'Configuração inicial de serviços', 'service', 'unit', 150.00, 0.00, 'BRL', 0, NULL, 1, 10),
  (2, 'SVC-002', 'Migração de Site', 'Migração completa de site', 'service', 'unit', 300.00, 0.00, 'BRL', 0, NULL, 1, 20),
  (3, 'SVC-003', 'Suporte Técnico', 'Suporte técnico especializado', 'service', 'hour', 80.00, 0.00, 'BRL', 0, NULL, 1, 30),
  (4, 'LIC-001', 'Licença cPanel', 'Licença mensal do cPanel', 'license', 'month', 15.00, 0.00, 'BRL', 1, 'monthly', 1, 40),
  (5, 'PROD-001', 'IP Dedicado', 'Endereço IP dedicado', 'product', 'month', 25.00, 0.00, 'BRL', 1, 'monthly', 1, 50);

