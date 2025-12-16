-- Tabela para status customizados de pedidos
CREATE TABLE IF NOT EXISTS order_statuses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL COMMENT 'Nome do status',
  label VARCHAR(100) NOT NULL COMMENT 'Rótulo exibido',
  color VARCHAR(7) NOT NULL DEFAULT '#6c757d' COMMENT 'Cor do status (hex)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Status padrão (não pode ser excluído)',
  include_in_pending TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Incluir em pendente',
  include_in_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Incluir em ativo',
  include_in_cancelled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Incluir em cancelado',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_status_name (name),
  KEY idx_status_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir status padrão
INSERT IGNORE INTO order_statuses (id, name, label, color, sort_order, is_default, include_in_pending, include_in_active, include_in_cancelled) VALUES
(1, 'pending', 'Pendente', '#ffc107', 1, 1, 1, 0, 0),
(2, 'active', 'Ativo', '#28a745', 2, 1, 0, 1, 0),
(3, 'fraud', 'Fraude', '#dc3545', 3, 1, 0, 0, 0),
(4, 'cancelled', 'Cancelado', '#6c757d', 4, 1, 0, 0, 1);

