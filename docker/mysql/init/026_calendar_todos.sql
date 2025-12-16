-- Tabela para eventos do calendário
CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador',
  title VARCHAR(255) NOT NULL COMMENT 'Título do evento',
  description TEXT NULL COMMENT 'Descrição do evento',
  start_date DATETIME NOT NULL COMMENT 'Data/hora de início',
  end_date DATETIME NULL COMMENT 'Data/hora de término',
  all_day TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Evento de dia inteiro',
  color VARCHAR(7) NOT NULL DEFAULT '#007bff' COMMENT 'Cor do evento (hex)',
  location VARCHAR(255) NULL COMMENT 'Local do evento',
  reminder_minutes INT NULL COMMENT 'Lembrete em minutos antes do evento',
  is_completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Evento concluído',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_event_admin (admin_id),
  KEY idx_event_start (start_date),
  KEY idx_event_completed (is_completed),
  CONSTRAINT fk_event_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para itens a fazer (todos)
CREATE TABLE IF NOT EXISTS todo_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do administrador',
  title VARCHAR(255) NOT NULL COMMENT 'Título da tarefa',
  description TEXT NULL COMMENT 'Descrição da tarefa',
  priority VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Prioridade: low, medium, high, urgent',
  status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Status: pending, in_progress, completed, cancelled',
  due_date DATE NULL COMMENT 'Data de vencimento',
  completed_at TIMESTAMP NULL COMMENT 'Data de conclusão',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_todo_admin (admin_id),
  KEY idx_todo_status (status),
  KEY idx_todo_priority (priority),
  KEY idx_todo_due_date (due_date),
  CONSTRAINT fk_todo_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

