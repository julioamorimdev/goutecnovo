-- Tabela para grupos de opções configuráveis
CREATE TABLE IF NOT EXISTS configurable_option_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do grupo',
  description TEXT NULL COMMENT 'Descrição do grupo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Grupo ativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_group_sort (sort_order),
  KEY idx_group_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para opções configuráveis
CREATE TABLE IF NOT EXISTS configurable_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do grupo',
  name VARCHAR(255) NOT NULL COMMENT 'Nome da opção',
  description TEXT NULL COMMENT 'Descrição da opção',
  option_type VARCHAR(50) NOT NULL DEFAULT 'dropdown' COMMENT 'Tipo: dropdown, radio, checkbox, text, textarea, quantity',
  is_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Obrigatório',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_option_group (group_id),
  KEY idx_option_sort (sort_order),
  CONSTRAINT fk_option_group FOREIGN KEY (group_id) REFERENCES configurable_option_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para valores das opções configuráveis
CREATE TABLE IF NOT EXISTS configurable_option_values (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  option_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da opção',
  value_label VARCHAR(255) NOT NULL COMMENT 'Rótulo do valor',
  value_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço adicional',
  price_type VARCHAR(20) NOT NULL DEFAULT 'one_time' COMMENT 'Tipo: one_time, recurring',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Valor padrão',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_value_option (option_id),
  KEY idx_value_sort (sort_order),
  CONSTRAINT fk_value_option FOREIGN KEY (option_id) REFERENCES configurable_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para vincular grupos de opções a planos
CREATE TABLE IF NOT EXISTS plan_configurable_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do plano',
  group_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do grupo de opções',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_group (plan_id, group_id),
  KEY idx_plan_config_group_plan (plan_id),
  KEY idx_plan_config_group_group (group_id),
  CONSTRAINT fk_plan_config_group_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_config_group_group FOREIGN KEY (group_id) REFERENCES configurable_option_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

