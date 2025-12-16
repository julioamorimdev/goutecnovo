-- Tabela para artigos do blog
CREATE TABLE IF NOT EXISTS blog_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  image VARCHAR(255) NOT NULL COMMENT 'Imagem do artigo',
  title VARCHAR(255) NOT NULL COMMENT 'Título do artigo',
  author VARCHAR(160) NOT NULL COMMENT 'Nome do autor',
  published_date DATE NOT NULL COMMENT 'Data de publicação',
  url VARCHAR(255) NOT NULL COMMENT 'URL do artigo (ex: blog-details.html?id=1)',
  content LONGTEXT NULL COMMENT 'Conteúdo completo do artigo (HTML)',
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se é o artigo em destaque (principal)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blog_featured (is_featured),
  KEY idx_blog_sort (sort_order),
  KEY idx_blog_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de artigos (baseado no index.html atual)
INSERT IGNORE INTO blog_posts (id, image, title, author, published_date, url, is_featured, sort_order, is_enabled)
VALUES
  (1, 'assets/img/blog-1.png', 'Atualizações da versão 6.4 do WordPress: Lançamento das atualizações e potencial de inovação', 'João da Silva', '2023-02-18', 'blog-details.html?id=1', 1, 10, 1),
  (2, 'assets/img/blog-2.png', 'Marketing de imóveis: Retenção de clientes mesmo em tempos de pandemia.', 'João da Silva', '2023-02-18', 'blog-details.html?id=2', 0, 20, 1),
  (3, 'assets/img/blog-3.png', 'O que é cPanel? Guia completo para dominar o painel de controle', 'João da Silva', '2023-02-18', 'blog-details.html?id=3', 0, 30, 1),
  (4, 'assets/img/blog-4.png', 'Paletas de cores 2024: Melhores estilos para colorir seus websites', 'João da Silva', '2023-02-18', 'blog-details.html?id=4', 0, 40, 1);
