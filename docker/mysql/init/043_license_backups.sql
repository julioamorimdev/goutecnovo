-- Tabela para dados da licença
CREATE TABLE IF NOT EXISTS license_data (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  license_key VARCHAR(255) NOT NULL COMMENT 'Chave da licença',
  license_type VARCHAR(50) NOT NULL DEFAULT 'trial' COMMENT 'Tipo: trial, standard, professional, enterprise',
  max_admins INT NULL COMMENT 'Máximo de administradores',
  max_clients INT NULL COMMENT 'Máximo de clientes',
  expires_at TIMESTAMP NULL COMMENT 'Data de expiração',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, expired, suspended',
  last_validation TIMESTAMP NULL COMMENT 'Última validação',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_license_key (license_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para backups do banco de dados
CREATE TABLE IF NOT EXISTS database_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo',
  file_path VARCHAR(500) NOT NULL COMMENT 'Caminho do arquivo',
  file_size BIGINT NOT NULL DEFAULT 0 COMMENT 'Tamanho do arquivo (bytes)',
  backup_type VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'Tipo: manual, automatic, scheduled',
  status VARCHAR(20) NOT NULL DEFAULT 'completed' COMMENT 'Status: completed, failed, in_progress',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_backup_type (backup_type),
  KEY idx_backup_status (status),
  KEY idx_backup_created (created_at),
  CONSTRAINT fk_backup_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

