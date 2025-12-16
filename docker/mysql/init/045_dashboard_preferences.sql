-- Tabela para preferências do dashboard por admin
CREATE TABLE IF NOT EXISTS dashboard_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador',
  widget_key VARCHAR(100) NOT NULL COMMENT 'Chave do widget',
  widget_type VARCHAR(50) NOT NULL COMMENT 'Tipo: stat, chart, list',
  position INT NOT NULL DEFAULT 0 COMMENT 'Posição/ordem',
  is_visible TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Widget visível',
  config JSON NULL COMMENT 'Configurações do widget em JSON',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_widget (admin_id, widget_key),
  KEY idx_admin_position (admin_id, position),
  CONSTRAINT fk_dashboard_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

