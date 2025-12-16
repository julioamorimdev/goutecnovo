-- Tabela para clientes
CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL COMMENT 'Nome',
  last_name VARCHAR(100) NOT NULL COMMENT 'Sobrenome',
  company_name VARCHAR(255) NULL COMMENT 'Nome da empresa (opcional)',
  email VARCHAR(255) NOT NULL COMMENT 'Email',
  phone VARCHAR(50) NULL COMMENT 'Telefone',
  address VARCHAR(255) NULL COMMENT 'Endereço',
  address2 VARCHAR(255) NULL COMMENT 'Complemento',
  city VARCHAR(100) NULL COMMENT 'Cidade',
  state VARCHAR(100) NULL COMMENT 'Estado',
  postal_code VARCHAR(20) NULL COMMENT 'CEP',
  country VARCHAR(100) NOT NULL DEFAULT 'Brasil' COMMENT 'País',
  password_hash VARCHAR(255) NULL COMMENT 'Hash da senha (se tiver acesso ao painel)',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, inactive, closed',
  notes TEXT NULL COMMENT 'Observações/Notas sobre o cliente',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de registro',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_client_email (email),
  KEY idx_client_status (status),
  KEY idx_client_name (first_name, last_name),
  KEY idx_client_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de clientes (exemplos)
INSERT IGNORE INTO clients (id, first_name, last_name, company_name, email, phone, address, city, state, postal_code, country, status, notes)
VALUES
  (1, 'João', 'Silva', 'Silva & Associados', 'joao.silva@example.com', '(11) 98765-4321', 'Rua das Flores, 123', 'São Paulo', 'SP', '01234-567', 'Brasil', 'active', 'Cliente desde 2023'),
  (2, 'Maria', 'Santos', NULL, 'maria.santos@example.com', '(21) 91234-5678', 'Av. Atlântica, 456', 'Rio de Janeiro', 'RJ', '22000-000', 'Brasil', 'active', NULL),
  (3, 'Pedro', 'Oliveira', 'Tech Solutions', 'pedro@techsol.com', '(31) 99876-5432', 'Rua da Tecnologia, 789', 'Belo Horizonte', 'MG', '30123-456', 'Brasil', 'inactive', 'Cliente inativo - pendente pagamento'),
  (4, 'Ana', 'Costa', NULL, 'ana.costa@example.com', '(41) 98765-4321', 'Rua Principal, 321', 'Curitiba', 'PR', '80000-000', 'Brasil', 'active', NULL);
