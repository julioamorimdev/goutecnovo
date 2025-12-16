-- Tabela para atalhos do dashboard
CREATE TABLE IF NOT EXISTS dashboard_shortcuts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(160) NOT NULL,
  url VARCHAR(255) NOT NULL,
  icon_class VARCHAR(120) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shortcut_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para seções do footer
CREATE TABLE IF NOT EXISTS footer_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_footer_section_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para links do footer
CREATE TABLE IF NOT EXISTS footer_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(160) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_footer_link_section (section_id),
  KEY idx_footer_link_sort (sort_order),
  CONSTRAINT fk_footer_link_section FOREIGN KEY (section_id) REFERENCES footer_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para configurações gerais do footer (logo, descrição, newsletter, copyright, redes sociais)
CREATE TABLE IF NOT EXISTS footer_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_footer_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de atalhos
INSERT IGNORE INTO dashboard_shortcuts (id, label, url, icon_class, sort_order, is_enabled)
VALUES
  (1, 'Novo item de menu', '/admin/menu_edit.php', 'las la-plus', 10, 1),
  (2, 'Novo admin', '/admin/admin_edit.php', 'las la-user-plus', 20, 1);

-- Seed inicial do footer (baseado no footer.html atual)
INSERT IGNORE INTO footer_sections (id, title, sort_order, is_enabled)
VALUES
  (1, 'Produtos e Soluções', 10, 1),
  (2, 'Recursos da GouTec', 20, 1),
  (3, 'Informações da GouTec', 30, 1);

INSERT IGNORE INTO footer_links (id, section_id, label, url, sort_order, is_enabled)
VALUES
  -- Produtos e Soluções
  (1, 1, 'Hospedagem compartilhada', 'shared-hosting.html', 10, 1),
  (2, 1, 'Hospedagem WordPress', 'wp-hosting.html', 20, 1),
  (3, 1, 'VPS Hosting', 'vps-server.html', 30, 1),
  (4, 1, 'Servidores Cloud', 'dedicated-server.html', 40, 1),
  (5, 1, 'Servidores dedicados', 'dedicated-server.html', 50, 1),
  (6, 1, 'Servidores de jogos', 'game-server.html', 60, 1),
  -- Recursos da GouTec
  (7, 2, 'Data Center', 'server-page.html', 10, 1),
  (8, 2, 'Painel de controle', 'control-panel.html', 20, 1),
  (9, 2, 'Sistema operacional', 'operating-system.html', 30, 1),
  (10, 2, 'Garantia de uptime', 'premium-network.html', 40, 1),
  (11, 2, 'Proteção contra DDOS', 'ddos.html', 50, 1),
  (12, 2, 'Configuração de servidor', 'server-page.html', 60, 1),
  -- Informações da GouTec
  (13, 3, 'Sobre nós', 'about-us.html', 10, 1),
  (14, 3, 'Parceiros', 'ddos.html', 20, 1),
  (15, 3, 'Base de conhecimento', 'server-page.html', 30, 1),
  (16, 3, 'Contato', 'contact.html', 40, 1),
  (17, 3, 'Notícias', 'blog-listing.html', 50, 1),
  (18, 3, 'Chat ao vivo', 'contact.html', 60, 1);

-- Configurações padrão do footer
INSERT IGNORE INTO footer_settings (setting_key, setting_value)
VALUES
  ('logo_url', 'assets/img/logo-dark.png'),
  ('description', 'Se você tem um site de e-commerce ou um site de negócios, você quer atrair o maior número de visitantes possível ou quando você não quer mais ser limitado por'),
  ('show_newsletter', '1'),
  ('copyright', '&copy; 2024 GouTec. Todos os direitos reservados'),
  ('social_twitter', '#'),
  ('social_facebook', '#'),
  ('social_dribbble', '#'),
  ('social_behance', '#');
