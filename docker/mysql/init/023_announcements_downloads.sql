-- Tabela para anúncios
CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL COMMENT 'Título do anúncio',
  content TEXT NOT NULL COMMENT 'Conteúdo do anúncio',
  type VARCHAR(50) NOT NULL DEFAULT 'info' COMMENT 'Tipo: info, warning, success, error, maintenance',
  target_audience VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'Público-alvo: all, clients, admins, specific',
  target_client_ids JSON NULL COMMENT 'IDs de clientes específicos (se target_audience = specific)',
  start_date DATETIME NULL COMMENT 'Data/hora de início da exibição',
  end_date DATETIME NULL COMMENT 'Data/hora de fim da exibição',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Anúncio ativo',
  is_dismissible TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Permite fechar o anúncio',
  priority INT NOT NULL DEFAULT 0 COMMENT 'Prioridade (maior número = maior prioridade)',
  show_on_dashboard TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir no dashboard',
  show_on_client_area TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir na área do cliente',
  click_url VARCHAR(500) NULL COMMENT 'URL ao clicar no anúncio',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_type (type),
  KEY idx_active (is_active),
  KEY idx_dates (start_date, end_date),
  KEY idx_priority (priority),
  KEY idx_created (created_at),
  CONSTRAINT fk_announcement_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para downloads
CREATE TABLE IF NOT EXISTS downloads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL COMMENT 'Título do download',
  description TEXT NULL COMMENT 'Descrição do download',
  file_path VARCHAR(500) NOT NULL COMMENT 'Caminho do arquivo',
  file_name VARCHAR(255) NOT NULL COMMENT 'Nome original do arquivo',
  file_size BIGINT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',
  file_type VARCHAR(100) NULL COMMENT 'Tipo MIME do arquivo',
  category VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Categoria: general, documentation, software, template, other',
  access_level VARCHAR(50) NOT NULL DEFAULT 'public' COMMENT 'Nível de acesso: public, clients, admins, specific',
  required_client_ids JSON NULL COMMENT 'IDs de clientes com acesso (se access_level = specific)',
  download_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de downloads',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Download ativo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  version VARCHAR(50) NULL COMMENT 'Versão do arquivo',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_category (category),
  KEY idx_access_level (access_level),
  KEY idx_active (is_active),
  KEY idx_sort (sort_order),
  KEY idx_created (created_at),
  CONSTRAINT fk_download_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para rastreamento de downloads
CREATE TABLE IF NOT EXISTS download_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  download_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do download',
  client_id BIGINT UNSIGNED NULL COMMENT 'ID do cliente (se aplicável)',
  admin_id BIGINT UNSIGNED NULL COMMENT 'ID do administrador (se aplicável)',
  ip_address VARCHAR(45) NULL COMMENT 'Endereço IP',
  user_agent TEXT NULL COMMENT 'User agent',
  downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do download',
  PRIMARY KEY (id),
  KEY idx_download (download_id),
  KEY idx_client (client_id),
  KEY idx_admin (admin_id),
  KEY idx_downloaded (downloaded_at),
  CONSTRAINT fk_download_log_file FOREIGN KEY (download_id) REFERENCES downloads(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_download_log_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_download_log_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

