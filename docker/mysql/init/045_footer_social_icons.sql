-- Tabela para ícones de redes sociais do footer
CREATE TABLE IF NOT EXISTS footer_social_icons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COMMENT 'Nome da rede social (ex: Facebook, WhatsApp)',
  icon_class VARCHAR(120) NOT NULL COMMENT 'Classe do ícone (ex: lab la-facebook-f)',
  url VARCHAR(500) NOT NULL DEFAULT '#' COMMENT 'URL do link',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_social_sort (sort_order),
  KEY idx_social_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de ícones de redes sociais
INSERT IGNORE INTO footer_social_icons (id, name, icon_class, url, sort_order, is_enabled)
VALUES
  (1, 'Facebook', 'lab la-facebook-f', '#', 10, 1),
  (2, 'WhatsApp', 'lab la-whatsapp', '#', 20, 1),
  (3, 'Discord', 'lab la-discord', '#', 30, 1),
  (4, 'Instagram', 'lab la-instagram', '#', 40, 1);

