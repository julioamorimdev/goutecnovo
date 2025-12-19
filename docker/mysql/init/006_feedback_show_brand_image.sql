-- Adicionar campo para controlar exibição da imagem da marca
ALTER TABLE feedback_items 
ADD COLUMN show_brand_image TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir imagem da marca (1=sim, 0=não)' 
AFTER brand_image;

