-- Tabela para afiliados
CREATE TABLE IF NOT EXISTS affiliates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL COMMENT 'Código único do afiliado',
  first_name VARCHAR(100) NOT NULL COMMENT 'Nome',
  last_name VARCHAR(100) NOT NULL COMMENT 'Sobrenome',
  company_name VARCHAR(255) NULL COMMENT 'Nome da empresa (opcional)',
  email VARCHAR(255) NOT NULL COMMENT 'Email',
  phone VARCHAR(50) NULL COMMENT 'Telefone',
  address VARCHAR(255) NULL COMMENT 'Endereço',
  address2 VARCHAR(255) NULL COMMENT 'Complemento',
  city VARCHAR(100) NULL COMMENT 'Cidade',
  state VARCHAR(100) NULL COMMENT 'Estado',
  postal_code VARCHAR(20) NULL COMMENT 'CEP',
  country VARCHAR(100) NOT NULL DEFAULT 'Brasil' COMMENT 'País',
  payment_method VARCHAR(50) NULL COMMENT 'Método de pagamento: bank_transfer, pix, paypal',
  payment_details TEXT NULL COMMENT 'Detalhes do pagamento (conta bancária, chave PIX, etc.)',
  commission_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'Tipo: percentage, fixed',
  commission_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor da comissão (percentual ou fixo)',
  minimum_payout DECIMAL(10,2) NOT NULL DEFAULT 50.00 COMMENT 'Valor mínimo para saque',
  total_earnings DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total ganho',
  paid_earnings DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total pago',
  pending_earnings DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total pendente',
  total_referrals INT NOT NULL DEFAULT 0 COMMENT 'Total de indicações',
  total_sales INT NOT NULL DEFAULT 0 COMMENT 'Total de vendas',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, inactive, suspended',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de registro',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_affiliate_code (code),
  UNIQUE KEY uk_affiliate_email (email),
  KEY idx_affiliate_status (status),
  KEY idx_affiliate_name (first_name, last_name),
  KEY idx_affiliate_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para comissões de afiliados
CREATE TABLE IF NOT EXISTS affiliate_commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do afiliado',
  order_id BIGINT UNSIGNED NULL COMMENT 'ID do pedido (se aplicável)',
  client_id BIGINT UNSIGNED NULL COMMENT 'ID do cliente indicado',
  description VARCHAR(255) NOT NULL COMMENT 'Descrição da comissão',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor da comissão',
  commission_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'Tipo: percentage, fixed',
  status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Status: pending, approved, paid, cancelled',
  payment_date DATE NULL COMMENT 'Data do pagamento',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_commission_affiliate (affiliate_id),
  KEY idx_commission_order (order_id),
  KEY idx_commission_client (client_id),
  KEY idx_commission_status (status),
  KEY idx_commission_created (created_at),
  CONSTRAINT fk_commission_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_commission_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_commission_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campo de afiliado na tabela de clientes (se não existir)
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS affiliate_code VARCHAR(50) NULL COMMENT 'Código do afiliado que indicou',
ADD KEY IF NOT EXISTS idx_client_affiliate (affiliate_code);

-- Adicionar campo de afiliado na tabela de pedidos (se não existir)
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS affiliate_id BIGINT UNSIGNED NULL COMMENT 'ID do afiliado que gerou a venda',
ADD KEY IF NOT EXISTS idx_order_affiliate (affiliate_id),
ADD CONSTRAINT IF NOT EXISTS fk_order_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL ON UPDATE CASCADE;

