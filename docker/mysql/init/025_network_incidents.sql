-- Tabela para falhas/incidentes na rede
CREATE TABLE IF NOT EXISTS network_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_number VARCHAR(50) NOT NULL COMMENT 'Número do incidente',
  title VARCHAR(255) NOT NULL COMMENT 'Título do incidente',
  description TEXT NOT NULL COMMENT 'Descrição detalhada do incidente',
  type VARCHAR(50) NOT NULL DEFAULT 'network' COMMENT 'Tipo: network, server, service, database, other',
  severity VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Severidade: low, medium, high, critical',
  status VARCHAR(20) NOT NULL DEFAULT 'investigating' COMMENT 'Status: investigating, identified, monitoring, resolved, false_alarm',
  affected_services TEXT NULL COMMENT 'Serviços afetados (JSON ou texto)',
  affected_servers TEXT NULL COMMENT 'Servidores afetados (JSON ou texto)',
  impact_description TEXT NULL COMMENT 'Descrição do impacto',
  root_cause TEXT NULL COMMENT 'Causa raiz (após investigação)',
  resolution TEXT NULL COMMENT 'Resolução aplicada',
  started_at DATETIME NOT NULL COMMENT 'Data/hora de início do incidente',
  resolved_at DATETIME NULL COMMENT 'Data/hora de resolução',
  estimated_resolution DATETIME NULL COMMENT 'Estimativa de resolução',
  is_public TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Visível publicamente (status page)',
  notify_clients TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Notificar clientes',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  resolved_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que resolveu',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_incident_number (incident_number),
  KEY idx_type (type),
  KEY idx_severity (severity),
  KEY idx_status (status),
  KEY idx_started (started_at),
  KEY idx_resolved (resolved_at),
  KEY idx_public (is_public),
  KEY idx_created (created_at),
  CONSTRAINT fk_incident_creator FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_incident_resolver FOREIGN KEY (resolved_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para atualizações/atualizações de status do incidente
CREATE TABLE IF NOT EXISTS network_incident_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do incidente',
  status VARCHAR(20) NOT NULL COMMENT 'Status na atualização',
  message TEXT NOT NULL COMMENT 'Mensagem de atualização',
  is_public TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Atualização pública',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  PRIMARY KEY (id),
  KEY idx_incident (incident_id),
  KEY idx_created (created_at),
  CONSTRAINT fk_update_incident FOREIGN KEY (incident_id) REFERENCES network_incidents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_update_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

