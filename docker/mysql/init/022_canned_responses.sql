-- Tabela para respostas predefinidas (canned responses)
CREATE TABLE IF NOT EXISTS canned_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL COMMENT 'Título da resposta',
  category VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Categoria: general, technical, billing, sales, other',
  subject VARCHAR(255) NULL COMMENT 'Assunto padrão (opcional)',
  message TEXT NOT NULL COMMENT 'Conteúdo da resposta',
  tags VARCHAR(255) NULL COMMENT 'Tags para busca (separadas por vírgula)',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Resposta ativa',
  usage_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de uso',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_by BIGINT UNSIGNED NULL COMMENT 'ID do administrador que criou',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_category (category),
  KEY idx_active (is_active),
  KEY idx_sort (sort_order),
  KEY idx_created (created_at),
  CONSTRAINT fk_canned_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

