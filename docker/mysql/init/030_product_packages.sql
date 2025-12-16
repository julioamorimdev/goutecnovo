-- Tabela para pacotes de produtos
CREATE TABLE IF NOT EXISTS product_packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT 'Nome do pacote',
  description TEXT NULL COMMENT 'Descrição do pacote',
  slug VARCHAR(255) NOT NULL COMMENT 'Slug para URL',
  discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'Tipo: percentage, fixed',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do desconto',
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço total do pacote (após desconto)',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pacote ativo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_package_slug (slug),
  KEY idx_package_active (is_active),
  KEY idx_package_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para itens do pacote (relação muitos-para-muitos com planos)
CREATE TABLE IF NOT EXISTS product_package_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do pacote',
  plan_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do plano/produto',
  quantity INT NOT NULL DEFAULT 1 COMMENT 'Quantidade do item no pacote',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  PRIMARY KEY (id),
  UNIQUE KEY uk_package_plan (package_id, plan_id),
  KEY idx_package_item_package (package_id),
  KEY idx_package_item_plan (plan_id),
  CONSTRAINT fk_package_item_package FOREIGN KEY (package_id) REFERENCES product_packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_package_item_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

