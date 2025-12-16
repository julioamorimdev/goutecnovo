-- Tabela para moedas
CREATE TABLE IF NOT EXISTS currencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(3) NOT NULL COMMENT 'Código da moeda (ISO 4217)',
  name VARCHAR(100) NOT NULL COMMENT 'Nome da moeda',
  symbol VARCHAR(10) NOT NULL COMMENT 'Símbolo da moeda',
  exchange_rate DECIMAL(10,4) NOT NULL DEFAULT 1.0000 COMMENT 'Taxa de câmbio (base: BRL)',
  is_base TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Moeda base',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Moeda ativa',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_currency_code (code),
  KEY idx_currency_active (is_active),
  KEY idx_currency_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Moedas padrão
INSERT IGNORE INTO currencies (id, code, name, symbol, exchange_rate, is_base, is_active, sort_order) VALUES
(1, 'BRL', 'Real Brasileiro', 'R$', 1.0000, 1, 1, 1),
(2, 'USD', 'Dólar Americano', '$', 5.0000, 0, 1, 2),
(3, 'EUR', 'Euro', '€', 5.5000, 0, 1, 3),
(4, 'GBP', 'Libra Esterlina', '£', 6.2000, 0, 1, 4);

