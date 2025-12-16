-- Adicionar campos ao admin_users (se não existirem)
-- ALTER TABLE admin_users ADD COLUMN signature TEXT NULL COMMENT 'Assinatura para tickets';
-- ALTER TABLE admin_users ADD COLUMN ticket_notifications TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificações de tickets';
-- ALTER TABLE admin_users ADD COLUMN profile_photo VARCHAR(255) NULL COMMENT 'Foto de perfil';
-- ALTER TABLE admin_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '2FA habilitado';
-- ALTER TABLE admin_users ADD COLUMN two_factor_secret VARCHAR(32) NULL COMMENT 'Secret 2FA';

-- Tabela para notas pessoais dos administradores
CREATE TABLE IF NOT EXISTS admin_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador',
  title VARCHAR(255) NOT NULL COMMENT 'Título da nota',
  content TEXT NOT NULL COMMENT 'Conteúdo da nota',
  color VARCHAR(7) NOT NULL DEFAULT '#ffffff' COMMENT 'Cor da nota (hex)',
  is_pinned TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nota fixada',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_note_admin (admin_id),
  KEY idx_note_created (created_at),
  CONSTRAINT fk_note_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

