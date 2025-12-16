-- Tabela para categorias de planos
CREATE TABLE IF NOT EXISTS plan_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome da categoria (ex: Hospedagens, VPS, Dedicado)',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug único para URL',
  description TEXT NULL COMMENT 'Descrição da categoria',
  icon_class VARCHAR(100) NULL COMMENT 'Classe do ícone (ex: las la-server)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_category_slug (slug),
  KEY idx_plan_category_sort (sort_order),
  KEY idx_plan_category_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para planos
CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da categoria',
  name VARCHAR(255) NOT NULL COMMENT 'Nome do plano',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug único para URL',
  description TEXT NULL COMMENT 'Descrição do plano',
  short_description VARCHAR(500) NULL COMMENT 'Descrição curta',
  price_monthly DECIMAL(10,2) NULL COMMENT 'Preço mensal',
  price_quarterly DECIMAL(10,2) NULL COMMENT 'Preço trimestral',
  price_semiannual DECIMAL(10,2) NULL COMMENT 'Preço semestral',
  price_annual DECIMAL(10,2) NULL COMMENT 'Preço anual',
  price_biennial DECIMAL(10,2) NULL COMMENT 'Preço bienal',
  price_triennal DECIMAL(10,2) NULL COMMENT 'Preço trienal',
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de instalação',
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Moeda (BRL, USD, etc.)',
  features JSON NULL COMMENT 'Recursos do plano em JSON',
  disk_space VARCHAR(50) NULL COMMENT 'Espaço em disco (ex: 10GB, Ilimitado)',
  bandwidth VARCHAR(50) NULL COMMENT 'Largura de banda (ex: 100GB, Ilimitado)',
  domains INT NULL COMMENT 'Número de domínios (NULL = ilimitado)',
  email_accounts INT NULL COMMENT 'Número de contas de email (NULL = ilimitado)',
  `databases` INT NULL COMMENT 'Número de bancos de dados (NULL = ilimitado)',
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Plano em destaque (popular)',
  is_popular TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Plano popular/recomendado',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_slug (slug),
  KEY idx_plan_category (category_id),
  KEY idx_plan_sort (sort_order),
  KEY idx_plan_enabled (is_enabled),
  KEY idx_plan_featured (is_featured),
  CONSTRAINT fk_plan_category FOREIGN KEY (category_id) REFERENCES plan_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de categorias
INSERT IGNORE INTO plan_categories (id, name, slug, description, icon_class, sort_order, is_enabled)
VALUES
  (1, 'Hospedagens', 'hospedagens', 'Planos de hospedagem compartilhada', 'las la-server', 10, 1),
  (2, 'Servidores VPS', 'servidores-vps', 'Servidores virtuais privados', 'las la-cloud', 20, 1),
  (3, 'Servidores Dedicados', 'servidores-dedicados', 'Servidores dedicados completos', 'las la-server', 30, 1),
  (4, 'Hospedagem WordPress', 'hospedagem-wordpress', 'Hospedagem otimizada para WordPress', 'lab la-wordpress', 40, 1),
  (5, 'Hospedagem E-commerce', 'hospedagem-ecommerce', 'Hospedagem para lojas virtuais', 'las la-shopping-cart', 50, 1);

-- Seed inicial de planos (exemplos)
INSERT IGNORE INTO plans (id, category_id, name, slug, description, short_description, price_monthly, price_annual, currency, disk_space, bandwidth, domains, email_accounts, `databases`, is_featured, is_popular, sort_order, is_enabled)
VALUES
  (1, 1, 'Plano Básico', 'plano-basico', 'Ideal para sites pessoais e pequenos projetos', 'Perfeito para começar', 29.90, 299.00, 'BRL', '10GB', '100GB', 1, 5, 5, 0, 0, 10, 1),
  (2, 1, 'Plano Profissional', 'plano-profissional', 'Para sites profissionais e empresas', 'Mais recursos e performance', 59.90, 599.00, 'BRL', '50GB', '500GB', 5, 50, 50, 0, 1, 20, 1),
  (3, 1, 'Plano Empresarial', 'plano-empresarial', 'Para grandes empresas e projetos', 'Máxima performance', 99.90, 999.00, 'BRL', '100GB', 'Ilimitado', NULL, NULL, NULL, 1, 0, 30, 1),
  (4, 2, 'VPS Starter', 'vps-starter', 'VPS para iniciantes', 'Recursos básicos de VPS', 79.90, 799.00, 'BRL', '20GB SSD', '1TB', NULL, NULL, NULL, 0, 0, 10, 1),
  (5, 2, 'VPS Business', 'vps-business', 'VPS para empresas', 'Recursos avançados', 149.90, 1499.00, 'BRL', '50GB SSD', '2TB', NULL, NULL, NULL, 0, 1, 20, 1);
