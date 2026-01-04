-- Adicionar campo is_featured na tabela tlds (se a tabela existir)
-- Se a tabela não existir, o script 014_tlds_domains.sql será executado primeiro

-- Verificar se a coluna já existe antes de adicionar
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tlds' 
    AND COLUMN_NAME = 'is_featured'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tlds ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''TLD em destaque (aparece no site)'' AFTER is_enabled',
    'SELECT ''Coluna is_featured já existe'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índice se não existir
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tlds' 
    AND INDEX_NAME = 'idx_tld_featured'
);

SET @sql2 = IF(@idx_exists = 0,
    'ALTER TABLE tlds ADD KEY idx_tld_featured (is_featured)',
    'SELECT ''Índice idx_tld_featured já existe'' AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

