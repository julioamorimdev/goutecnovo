-- Adicionar coluna de conteúdo ao blog_posts
ALTER TABLE blog_posts 
ADD COLUMN IF NOT EXISTS content LONGTEXT NULL COMMENT 'Conteúdo completo do artigo (HTML)' AFTER url;
