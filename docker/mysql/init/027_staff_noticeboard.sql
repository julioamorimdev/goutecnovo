-- Tabela para quadro de avisos da equipe (Staff Noticeboard)
CREATE TABLE IF NOT EXISTS staff_notices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador que criou o aviso',
  title VARCHAR(255) NOT NULL COMMENT 'Título do aviso',
  content TEXT NOT NULL COMMENT 'Conteúdo do aviso',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'Prioridade: low, normal, high, urgent',
  is_pinned TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Aviso fixado no topo',
  is_public TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Visível para todos os administradores',
  target_admin_ids JSON NULL COMMENT 'IDs de administradores específicos (se não for público)',
  expires_at TIMESTAMP NULL COMMENT 'Data de expiração do aviso',
  views_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de visualizações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notice_admin (admin_id),
  KEY idx_notice_priority (priority),
  KEY idx_notice_pinned (is_pinned),
  KEY idx_notice_expires (expires_at),
  KEY idx_notice_created (created_at),
  CONSTRAINT fk_notice_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para rastrear visualizações de avisos por administrador
CREATE TABLE IF NOT EXISTS staff_notice_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notice_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do aviso',
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador que visualizou',
  viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da visualização',
  PRIMARY KEY (id),
  UNIQUE KEY uk_notice_view (notice_id, admin_id),
  KEY idx_view_notice (notice_id),
  KEY idx_view_admin (admin_id),
  KEY idx_view_date (viewed_at),
  CONSTRAINT fk_view_notice FOREIGN KEY (notice_id) REFERENCES staff_notices(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_view_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

