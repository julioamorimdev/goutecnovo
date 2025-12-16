-- Tabela para campos personalizados de clientes
CREATE TABLE IF NOT EXISTS client_custom_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  field_name VARCHAR(100) NOT NULL COMMENT 'Nome interno do campo',
  field_label VARCHAR(255) NOT NULL COMMENT 'Rótulo exibido',
  field_type VARCHAR(50) NOT NULL DEFAULT 'text' COMMENT 'Tipo: text, textarea, email, phone, number, date, select, checkbox, radio',
  field_options TEXT NULL COMMENT 'Opções para select/radio (uma por linha)',
  is_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Campo obrigatório',
  is_encrypted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Campo criptografado',
  validation_regex VARCHAR(255) NULL COMMENT 'Regex de validação',
  placeholder VARCHAR(255) NULL COMMENT 'Texto placeholder',
  help_text VARCHAR(500) NULL COMMENT 'Texto de ajuda',
  default_value VARCHAR(500) NULL COMMENT 'Valor padrão',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Campo ativo',
  show_in_registration TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Exibir no registro',
  show_in_profile TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir no perfil',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_field_name (field_name),
  KEY idx_field_sort (sort_order),
  KEY idx_field_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para valores dos campos personalizados dos clientes
CREATE TABLE IF NOT EXISTS client_custom_field_values (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  field_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do campo',
  field_value TEXT NULL COMMENT 'Valor do campo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_client_field (client_id, field_id),
  KEY idx_value_client (client_id),
  KEY idx_value_field (field_id),
  CONSTRAINT fk_value_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_value_field FOREIGN KEY (field_id) REFERENCES client_custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

