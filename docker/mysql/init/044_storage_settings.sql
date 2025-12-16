-- Tabela para configurações de armazenamento
CREATE TABLE IF NOT EXISTS storage_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  storage_type VARCHAR(50) NOT NULL DEFAULT 'local' COMMENT 'Tipo: local, s3, ftp, sftp',
  setting_key VARCHAR(100) NOT NULL COMMENT 'Chave da configuração',
  setting_value TEXT NULL COMMENT 'Valor da configuração',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_storage_key (storage_type, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão de armazenamento local
INSERT IGNORE INTO storage_settings (storage_type, setting_key, setting_value) VALUES
('local', 'base_path', '/var/www/goutecnovo/storage'),
('local', 'max_file_size', '10485760'),
('local', 'allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip,rar'),
('s3', 'enabled', '0'),
('s3', 'access_key', ''),
('s3', 'secret_key', ''),
('s3', 'bucket', ''),
('s3', 'region', 'us-east-1'),
('ftp', 'enabled', '0'),
('ftp', 'host', ''),
('ftp', 'port', '21'),
('ftp', 'username', ''),
('ftp', 'password', ''),
('ftp', 'path', '/');

