-- Tabela para promoções
CREATE TABLE IF NOT EXISTS promotions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome da promoção',
  description TEXT NULL COMMENT 'Descrição da promoção',
  discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'Tipo: percentage, fixed',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do desconto',
  min_purchase DECIMAL(10,2) NULL COMMENT 'Valor mínimo de compra',
  applicable_to VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'Aplicável a: all, specific_plans, specific_categories',
  applicable_ids JSON NULL COMMENT 'IDs de planos/categorias específicos',
  start_date DATETIME NOT NULL COMMENT 'Data de início',
  end_date DATETIME NULL COMMENT 'Data de término',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Promoção ativa',
  usage_limit INT NULL COMMENT 'Limite de usos (NULL = ilimitado)',
  usage_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de usos',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_promotion_active (is_active),
  KEY idx_promotion_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para cupons de desconto
CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL COMMENT 'Código do cupom',
  name VARCHAR(255) NOT NULL COMMENT 'Nome do cupom',
  description TEXT NULL COMMENT 'Descrição',
  discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'Tipo: percentage, fixed',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do desconto',
  min_purchase DECIMAL(10,2) NULL COMMENT 'Valor mínimo de compra',
  max_discount DECIMAL(10,2) NULL COMMENT 'Desconto máximo (para porcentagem)',
  applicable_to VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'Aplicável a: all, specific_plans',
  applicable_ids JSON NULL COMMENT 'IDs de planos específicos',
  start_date DATETIME NOT NULL COMMENT 'Data de início',
  end_date DATETIME NULL COMMENT 'Data de término',
  usage_limit INT NULL COMMENT 'Limite total de usos',
  usage_limit_per_user INT NULL COMMENT 'Limite de usos por usuário',
  usage_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de usos',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Cupom ativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_coupon_code (code),
  KEY idx_coupon_active (is_active),
  KEY idx_coupon_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para rastrear uso de cupons
CREATE TABLE IF NOT EXISTS coupon_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_usage_coupon (coupon_id),
  KEY idx_usage_client (client_id),
  KEY idx_usage_order (order_id),
  CONSTRAINT fk_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_usage_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_usage_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

