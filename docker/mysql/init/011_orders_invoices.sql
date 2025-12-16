-- Tabela para pedidos
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  plan_id BIGINT UNSIGNED NULL COMMENT 'ID do plano (opcional)',
  order_number VARCHAR(50) NOT NULL COMMENT 'Número do pedido',
  status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Status: pending, active, suspended, cancelled, fraud',
  billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly' COMMENT 'Ciclo: monthly, quarterly, semiannual, annual, biennial, triennal',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do pedido',
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de instalação',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_order_number (order_number),
  KEY idx_order_client (client_id),
  KEY idx_order_plan (plan_id),
  KEY idx_order_status (status),
  KEY idx_order_created (created_at),
  CONSTRAINT fk_order_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_order_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para faturas
CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NULL COMMENT 'ID do pedido (opcional)',
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  invoice_number VARCHAR(50) NOT NULL COMMENT 'Número da fatura',
  status VARCHAR(20) NOT NULL DEFAULT 'unpaid' COMMENT 'Status: unpaid, paid, cancelled, refunded',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal',
  tax DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Impostos',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda',
  due_date DATE NULL COMMENT 'Data de vencimento',
  paid_date DATE NULL COMMENT 'Data de pagamento',
  payment_method VARCHAR(50) NULL COMMENT 'Método de pagamento',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_invoice_number (invoice_number),
  KEY idx_invoice_order (order_id),
  KEY idx_invoice_client (client_id),
  KEY idx_invoice_status (status),
  KEY idx_invoice_due_date (due_date),
  CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de pedidos (exemplos)
INSERT IGNORE INTO orders (id, client_id, plan_id, order_number, status, billing_cycle, amount, setup_fee, currency, notes)
VALUES
  (1, 1, 2, 'ORD-2024-001', 'active', 'annual', 599.00, 0.00, 'BRL', 'Pedido ativo - Plano Profissional'),
  (2, 2, 1, 'ORD-2024-002', 'pending', 'monthly', 29.90, 0.00, 'BRL', 'Aguardando pagamento'),
  (3, 3, 3, 'ORD-2024-003', 'suspended', 'monthly', 99.90, 50.00, 'BRL', 'Pedido suspenso - pagamento em atraso'),
  (4, 4, 2, 'ORD-2024-004', 'active', 'quarterly', 179.70, 0.00, 'BRL', NULL);

-- Seed inicial de faturas (exemplos)
INSERT IGNORE INTO invoices (id, order_id, client_id, invoice_number, status, subtotal, tax, total, currency, due_date, paid_date, payment_method)
VALUES
  (1, 1, 1, 'INV-2024-001', 'paid', 599.00, 0.00, 599.00, 'BRL', '2024-01-15', '2024-01-10', 'Cartão de Crédito'),
  (2, 2, 2, 'INV-2024-002', 'unpaid', 29.90, 0.00, 29.90, 'BRL', '2024-02-20', NULL, NULL),
  (3, 3, 3, 'INV-2024-003', 'unpaid', 149.90, 0.00, 149.90, 'BRL', '2024-01-10', NULL, NULL),
  (4, 4, 4, 'INV-2024-004', 'paid', 179.70, 0.00, 179.70, 'BRL', '2024-02-01', '2024-01-28', 'PIX');
