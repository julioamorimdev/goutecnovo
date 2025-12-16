-- Tabela para IPs banidos
CREATE TABLE IF NOT EXISTS banned_ips (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL COMMENT 'Endereço IP',
  reason TEXT NULL COMMENT 'Motivo do banimento',
  banned_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que baniu',
  expires_at TIMESTAMP NULL COMMENT 'Data de expiração (NULL = permanente)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_banned_ip (ip_address),
  KEY idx_banned_expires (expires_at),
  CONSTRAINT fk_banned_admin FOREIGN KEY (banned_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para domínios de email banidos
CREATE TABLE IF NOT EXISTS banned_email_domains (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain VARCHAR(255) NOT NULL COMMENT 'Domínio de email',
  reason TEXT NULL COMMENT 'Motivo do banimento',
  banned_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que baniu',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_banned_domain (domain),
  CONSTRAINT fk_banned_domain_admin FOREIGN KEY (banned_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para questões de segurança
CREATE TABLE IF NOT EXISTS security_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question TEXT NOT NULL COMMENT 'Pergunta de segurança',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pergunta ativa',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_active (is_active),
  KEY idx_question_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para respostas de segurança dos administradores
CREATE TABLE IF NOT EXISTS admin_security_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador',
  question_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da pergunta',
  answer_hash VARCHAR(255) NOT NULL COMMENT 'Hash da resposta',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_question (admin_id, question_id),
  CONSTRAINT fk_answer_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES security_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

