-- Tabela para tickets de suporte
CREATE TABLE IF NOT EXISTS tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  ticket_number VARCHAR(50) NOT NULL COMMENT 'Número do ticket',
  subject VARCHAR(255) NOT NULL COMMENT 'Assunto do ticket',
  department VARCHAR(50) NOT NULL DEFAULT 'support' COMMENT 'Departamento: support, sales, billing, technical',
  priority VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Prioridade: low, medium, high, urgent',
  status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'Status: open, answered, customer_reply, closed',
  last_reply_at TIMESTAMP NULL COMMENT 'Data da última resposta',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ticket_number (ticket_number),
  KEY idx_ticket_client (client_id),
  KEY idx_ticket_status (status),
  KEY idx_ticket_priority (priority),
  KEY idx_ticket_department (department),
  KEY idx_ticket_created (created_at),
  CONSTRAINT fk_ticket_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para respostas/mensagens dos tickets
CREATE TABLE IF NOT EXISTS ticket_replies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do ticket',
  user_id BIGINT UNSIGNED NULL COMMENT 'ID do usuário admin (NULL = cliente)',
  user_type VARCHAR(20) NOT NULL DEFAULT 'client' COMMENT 'Tipo: client, admin',
  message TEXT NOT NULL COMMENT 'Mensagem',
  attachments JSON NULL COMMENT 'Anexos (array de URLs)',
  is_internal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nota interna (não visível para o cliente)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  PRIMARY KEY (id),
  KEY idx_ticket_reply_ticket (ticket_id),
  KEY idx_ticket_reply_user (user_id),
  KEY idx_ticket_reply_created (created_at),
  CONSTRAINT fk_ticket_reply_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de tickets (exemplos)
-- Garantir UTF-8 antes de inserir dados
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;
SET character_set_connection=utf8mb4;

INSERT IGNORE INTO tickets (id, client_id, ticket_number, subject, department, priority, status)
VALUES
  (1, 1, 'TKT-2024-001', 'Problema com acesso ao painel', 'technical', 'high', 'open'),
  (2, 2, 'TKT-2024-002', 'Dúvida sobre faturamento', 'billing', 'medium', 'answered'),
  (3, 3, 'TKT-2024-003', 'Solicitação de upgrade de plano', 'sales', 'low', 'closed'),
  (4, 4, 'TKT-2024-004', 'Erro ao fazer upload de arquivos', 'technical', 'high', 'customer_reply');

-- Seed inicial de respostas
INSERT IGNORE INTO ticket_replies (id, ticket_id, user_id, user_type, message, is_internal)
VALUES
  (1, 1, NULL, 'client', 'Não consigo acessar meu painel de controle. A senha não está funcionando.', 0),
  (2, 2, NULL, 'client', 'Gostaria de entender melhor como funciona o faturamento mensal.', 0),
  (3, 2, 1, 'admin', 'Olá! O faturamento é realizado mensalmente no dia de vencimento do seu plano. Você receberá um email com a fatura 7 dias antes do vencimento.', 0),
  (4, 3, NULL, 'client', 'Quero fazer upgrade do meu plano atual.', 0),
  (5, 3, 1, 'admin', 'Upgrade realizado com sucesso! Seu novo plano já está ativo.', 0),
  (6, 4, NULL, 'client', 'Estou tendo problemas para fazer upload de arquivos no meu site.', 0);
