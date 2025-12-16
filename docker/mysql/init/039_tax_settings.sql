-- Tabela para configurações fiscais
CREATE TABLE IF NOT EXISTS tax_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL COMMENT 'Chave da configuração',
  setting_value TEXT NULL COMMENT 'Valor da configuração',
  setting_group VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Grupo: general, vat, rules, advanced',
  setting_type VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'Tipo: text, number, boolean, json',
  description TEXT NULL COMMENT 'Descrição da configuração',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tax_setting_key (setting_key),
  KEY idx_tax_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para regras fiscais
CREATE TABLE IF NOT EXISTS tax_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome da regra',
  country VARCHAR(2) NULL COMMENT 'País (ISO 2 letras)',
  state VARCHAR(100) NULL COMMENT 'Estado/Província',
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de imposto (%)',
  tax_type VARCHAR(50) NOT NULL DEFAULT 'vat' COMMENT 'Tipo: vat, sales_tax, gst',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Regra ativa',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tax_rule_country (country),
  KEY idx_tax_rule_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

