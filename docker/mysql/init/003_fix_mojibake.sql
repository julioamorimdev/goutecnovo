SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Corrige dados gravados com UTF-8 interpretado como latin1 (ex: InÃ­cio).
-- Só aplica em linhas com padrões típicos de "mojibake".
UPDATE menu_items
SET
  label       = CONVERT(BINARY CONVERT(label USING latin1) USING utf8mb4),
  url         = CONVERT(BINARY CONVERT(url USING latin1) USING utf8mb4),
  icon_class  = CONVERT(BINARY CONVERT(icon_class USING latin1) USING utf8mb4),
  description = CONVERT(BINARY CONVERT(description USING latin1) USING utf8mb4),
  badge_text  = CONVERT(BINARY CONVERT(badge_text USING latin1) USING utf8mb4),
  badge_class = CONVERT(BINARY CONVERT(badge_class USING latin1) USING utf8mb4)
WHERE CONCAT_WS('', label, url, icon_class, description, badge_text, badge_class) REGEXP 'Ã.|Â.|â';


