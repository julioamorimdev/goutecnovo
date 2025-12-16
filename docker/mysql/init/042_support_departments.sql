-- Tabela para departamentos de suporte
CREATE TABLE IF NOT EXISTS support_departments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do departamento',
  description TEXT NULL COMMENT 'Descrição',
  email VARCHAR(255) NOT NULL COMMENT 'Email do departamento',
  auto_respond TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Resposta automática',
  auto_respond_message TEXT NULL COMMENT 'Mensagem de resposta automática',
  import_method VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'Método: none, forward, pop3, imap',
  pop3_host VARCHAR(255) NULL COMMENT 'Host POP3',
  pop3_port INT NULL COMMENT 'Porta POP3',
  pop3_username VARCHAR(255) NULL COMMENT 'Usuário POP3',
  pop3_password VARCHAR(255) NULL COMMENT 'Senha POP3',
  pop3_ssl TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Usar SSL POP3',
  imap_host VARCHAR(255) NULL COMMENT 'Host IMAP',
  imap_port INT NULL COMMENT 'Porta IMAP',
  imap_username VARCHAR(255) NULL COMMENT 'Usuário IMAP',
  imap_password VARCHAR(255) NULL COMMENT 'Senha IMAP',
  imap_ssl TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Usar SSL IMAP',
  import_frequency INT NOT NULL DEFAULT 5 COMMENT 'Frequência de importação (minutos)',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Departamento ativo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_dept_email (email),
  KEY idx_dept_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para regras de escalonamento de tickets
CREATE TABLE IF NOT EXISTS ticket_escalation_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome da regra',
  priority VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Prioridade: low, medium, high, urgent',
  department VARCHAR(50) NULL COMMENT 'Departamento',
  status VARCHAR(20) NULL COMMENT 'Status do ticket',
  hours_without_reply INT NOT NULL DEFAULT 24 COMMENT 'Horas sem resposta',
  action VARCHAR(50) NOT NULL DEFAULT 'change_priority' COMMENT 'Ação: change_priority, assign_admin, close_ticket, send_notification',
  action_value VARCHAR(255) NULL COMMENT 'Valor da ação (ID do admin, etc)',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Regra ativa',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de execução',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_escalation_active (is_active),
  KEY idx_escalation_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para controle de spam de tickets
CREATE TABLE IF NOT EXISTS ticket_spam_control (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NULL COMMENT 'Endereço IP',
  email VARCHAR(255) NULL COMMENT 'Email',
  ticket_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de tickets',
  last_ticket_at TIMESTAMP NULL COMMENT 'Último ticket',
  is_blocked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bloqueado',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_spam_ip (ip_address),
  KEY idx_spam_email (email),
  KEY idx_spam_blocked (is_blocked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

