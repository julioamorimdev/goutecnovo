-- Tabela para TLDs (Top Level Domains)
CREATE TABLE IF NOT EXISTS tlds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tld VARCHAR(20) NOT NULL COMMENT 'TLD (ex: .com, .com.br, .net)',
  name VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do TLD',
  description TEXT NULL COMMENT 'Descrição do TLD',
  price_register DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço de registro (1 ano)',
  price_renew DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço de renovação (1 ano)',
  price_transfer DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço de transferência',
  min_years INT NOT NULL DEFAULT 1 COMMENT 'Anos mínimos de registro',
  max_years INT NOT NULL DEFAULT 10 COMMENT 'Anos máximos de registro',
  epp_code_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Requer código EPP para transferência',
  privacy_protection_available TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Proteção de privacidade disponível',
  privacy_protection_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço da proteção de privacidade',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'TLD ativo/inativo',
  sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tld (tld),
  KEY idx_tld_enabled (is_enabled),
  KEY idx_tld_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para registros de domínios
CREATE TABLE IF NOT EXISTS domain_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  tld_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do TLD',
  domain_name VARCHAR(255) NOT NULL COMMENT 'Nome do domínio (sem TLD)',
  full_domain VARCHAR(255) NOT NULL COMMENT 'Domínio completo (ex: exemplo.com.br)',
  registration_date DATE NOT NULL COMMENT 'Data de registro',
  expiration_date DATE NOT NULL COMMENT 'Data de expiração',
  years INT NOT NULL DEFAULT 1 COMMENT 'Anos registrados',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, expired, suspended, cancelled, pending_transfer',
  auto_renew TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Renovação automática',
  privacy_protection TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Proteção de privacidade ativa',
  nameservers JSON NULL COMMENT 'Nameservers em JSON',
  registrar VARCHAR(100) NULL COMMENT 'Registrador',
  epp_code VARCHAR(50) NULL COMMENT 'Código EPP (se aplicável)',
  notes TEXT NULL COMMENT 'Observações',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_full_domain (full_domain),
  KEY idx_domain_client (client_id),
  KEY idx_domain_tld (tld_id),
  KEY idx_domain_status (status),
  KEY idx_domain_expiration (expiration_date),
  KEY idx_domain_name (domain_name),
  CONSTRAINT fk_domain_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_domain_tld FOREIGN KEY (tld_id) REFERENCES tlds(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de TLDs comuns
INSERT IGNORE INTO tlds (tld, name, description, price_register, price_renew, price_transfer, min_years, max_years, epp_code_required, privacy_protection_available, privacy_protection_price, is_enabled, sort_order)
VALUES
  ('.com', 'Domínio .com', 'Domínio comercial internacional', 39.90, 39.90, 39.90, 1, 10, 1, 1, 19.90, 1, 1),
  ('.com.br', 'Domínio .com.br', 'Domínio comercial brasileiro', 39.90, 39.90, 39.90, 1, 1, 0, 0, 0.00, 1, 2),
  ('.net', 'Domínio .net', 'Domínio de rede', 49.90, 49.90, 49.90, 1, 10, 1, 1, 19.90, 1, 3),
  ('.org', 'Domínio .org', 'Domínio para organizações', 49.90, 49.90, 49.90, 1, 10, 1, 1, 19.90, 1, 4),
  ('.br', 'Domínio .br', 'Domínio brasileiro genérico', 39.90, 39.90, 39.90, 1, 1, 0, 0, 0.00, 1, 5),
  ('.net.br', 'Domínio .net.br', 'Domínio de rede brasileiro', 39.90, 39.90, 39.90, 1, 1, 0, 0, 0.00, 1, 6),
  ('.org.br', 'Domínio .org.br', 'Domínio para organizações brasileiras', 39.90, 39.90, 39.90, 1, 1, 0, 0, 0.00, 1, 7),
  ('.info', 'Domínio .info', 'Domínio informacional', 59.90, 59.90, 59.90, 1, 10, 1, 1, 19.90, 1, 8),
  ('.biz', 'Domínio .biz', 'Domínio para negócios', 59.90, 59.90, 59.90, 1, 10, 1, 1, 19.90, 1, 9);

