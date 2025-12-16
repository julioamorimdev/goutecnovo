-- Tabela para categorias da base de conhecimento
CREATE TABLE IF NOT EXISTS knowledge_base_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome da categoria',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug da categoria (URL-friendly)',
  description TEXT NULL COMMENT 'Descrição da categoria',
  icon VARCHAR(100) NULL COMMENT 'Ícone da categoria (classe CSS)',
  parent_id BIGINT UNSIGNED NULL COMMENT 'ID da categoria pai (para hierarquia)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Categoria ativa',
  article_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de artigos (atualizado automaticamente)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_category_slug (slug),
  KEY idx_parent (parent_id),
  KEY idx_active (is_active),
  KEY idx_sort (sort_order),
  KEY idx_created (created_at),
  CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES knowledge_base_categories(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para artigos da base de conhecimento
CREATE TABLE IF NOT EXISTS knowledge_base_articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL COMMENT 'Título do artigo',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug do artigo (URL-friendly)',
  content TEXT NOT NULL COMMENT 'Conteúdo do artigo (HTML permitido)',
  excerpt TEXT NULL COMMENT 'Resumo do artigo',
  category_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da categoria',
  author_id BIGINT UNSIGNED NULL COMMENT 'ID do administrador autor',
  status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'Status: draft, published, archived',
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Artigo em destaque',
  is_pinned TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Artigo fixado',
  view_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de visualizações',
  helpful_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de "útil"',
  not_helpful_count INT NOT NULL DEFAULT 0 COMMENT 'Contador de "não útil"',
  tags VARCHAR(500) NULL COMMENT 'Tags do artigo (separadas por vírgula)',
  meta_keywords VARCHAR(500) NULL COMMENT 'Palavras-chave para SEO',
  meta_description VARCHAR(500) NULL COMMENT 'Descrição para SEO',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  published_at DATETIME NULL COMMENT 'Data/hora de publicação',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_article_slug (slug),
  KEY idx_category (category_id),
  KEY idx_author (author_id),
  KEY idx_status (status),
  KEY idx_featured (is_featured),
  KEY idx_pinned (is_pinned),
  KEY idx_published (published_at),
  KEY idx_created (created_at),
  CONSTRAINT fk_article_category FOREIGN KEY (category_id) REFERENCES knowledge_base_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_article_author FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para feedback de artigos (útil/não útil)
CREATE TABLE IF NOT EXISTS knowledge_base_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do artigo',
  client_id BIGINT UNSIGNED NULL COMMENT 'ID do cliente (se aplicável)',
  is_helpful TINYINT(1) NOT NULL COMMENT '1 = útil, 0 = não útil',
  comment TEXT NULL COMMENT 'Comentário opcional',
  ip_address VARCHAR(45) NULL COMMENT 'Endereço IP',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  PRIMARY KEY (id),
  KEY idx_article (article_id),
  KEY idx_client (client_id),
  KEY idx_created (created_at),
  CONSTRAINT fk_feedback_article FOREIGN KEY (article_id) REFERENCES knowledge_base_articles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_feedback_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

