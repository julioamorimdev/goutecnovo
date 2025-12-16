-- Tabela para grupos de clientes
CREATE TABLE IF NOT EXISTS client_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do grupo',
  description TEXT NULL COMMENT 'Descrição do grupo',
  color VARCHAR(7) NOT NULL DEFAULT '#6c757d' COMMENT 'Cor do grupo (hex)',
  discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Desconto percentual para o grupo',
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Grupo padrão (novos clientes)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_group_name (name),
  KEY idx_group_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupo padrão
INSERT IGNORE INTO client_groups (id, name, description, color, is_default, sort_order) VALUES
(1, 'Padrão', 'Grupo padrão para novos clientes', '#6c757d', 1, 1);

-- Adicionar coluna group_id na tabela clients (se não existir)
-- ALTER TABLE clients ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER id;
-- ALTER TABLE clients ADD CONSTRAINT fk_client_group FOREIGN KEY (group_id) REFERENCES client_groups(id) ON DELETE SET NULL;

