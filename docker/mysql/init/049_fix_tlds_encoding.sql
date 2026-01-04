-- Corrigir encoding dos TLDS (corrigir mojibake - UTF-8 duplo-encoded)
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;
SET character_set_connection=utf8mb4;

-- Corrigir encoding usando CONVERT (converte de latin1 para utf8mb4)
-- Atualiza todos os registros que têm caracteres corrompidos
UPDATE tlds SET 
    name = CASE 
        WHEN name LIKE '%Ã%' THEN CONVERT(BINARY CONVERT(name USING latin1) USING utf8mb4)
        ELSE name
    END,
    description = CASE 
        WHEN description LIKE '%Ã%' THEN CONVERT(BINARY CONVERT(description USING latin1) USING utf8mb4)
        ELSE description
    END
WHERE name LIKE '%Ã%' OR (description IS NOT NULL AND description LIKE '%Ã%');
