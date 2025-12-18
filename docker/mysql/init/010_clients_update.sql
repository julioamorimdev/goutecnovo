-- Adicionar campos faltantes na tabela clients
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS cpf VARCHAR(14) NULL COMMENT 'CPF' AFTER phone,
ADD COLUMN IF NOT EXISTS cnpj VARCHAR(18) NULL COMMENT 'CNPJ' AFTER company_name,
ADD COLUMN IF NOT EXISTS address_number VARCHAR(20) NULL COMMENT 'Número do endereço' AFTER address,
ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) NULL COMMENT 'Bairro' AFTER address_number,
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Email verificado' AFTER email,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) NULL COMMENT 'Token de verificação de email' AFTER email_verified,
ADD COLUMN IF NOT EXISTS email_verification_sent_at TIMESTAMP NULL COMMENT 'Data de envio do token' AFTER email_verification_token,
ADD COLUMN IF NOT EXISTS newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Inscrito na newsletter' AFTER email_verification_sent_at;

-- Adicionar índices únicos para CPF e CNPJ
ALTER TABLE clients 
ADD UNIQUE KEY IF NOT EXISTS uk_client_cpf (cpf),
ADD UNIQUE KEY IF NOT EXISTS uk_client_cnpj (cnpj);

