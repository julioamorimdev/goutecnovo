<?php
/**
 * Script para corrigir encoding dos itens do menu principal
 * Execute este script uma vez via navegador e depois delete-o
 */
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Corrigir Encoding do Menu</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: #666; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Corrigir Encoding do Menu Principal</h1>
    
<?php
try {
    // Buscar todos os itens
    $stmt = db()->query("SELECT id, label, description, badge_text FROM menu_items");
    $items = $stmt->fetchAll();
    
    $fixed = 0;
    $total = count($items);
    
    echo "<p>Verificando {$total} item(s) do menu...</p>\n";
    echo "<hr>\n";
    
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $updates = [];
        $changes = [];
        
        // Verificar e corrigir cada campo de texto
        $fields = ['label', 'description', 'badge_text'];
        foreach ($fields as $field) {
            $value = $item[$field];
            if ($value === null || $value === '') continue;
            
            // Detectar problemas comuns de encoding
            $needsFix = false;
            $original = $value;
            
            // Verificar se contém caracteres corrompidos típicos
            // "Ãrea" = "Área" corrompido (UTF-8 lido como Latin1)
            if (preg_match('/Ã[¡-ÿ]/u', $value) || 
                preg_match('/â€™/', $value) ||
                preg_match('/â€"/', $value) ||
                preg_match('/â€"/', $value)) {
                $needsFix = true;
            }
            
            // Verificar se não é UTF-8 válido
            if (!mb_check_encoding($value, 'UTF-8')) {
                $needsFix = true;
            }
            
            if ($needsFix) {
                // Tentar corrigir: assumir que foi salvo como Latin1 mas deveria ser UTF-8
                // Primeiro, tentar converter de Latin1 para UTF-8
                $corrected = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                
                // Se ainda estiver corrompido, tentar outra abordagem
                if (preg_match('/Ã[¡-ÿ]/u', $corrected)) {
                    // Tentar corrigir manualmente os casos mais comuns
                    $corrected = str_replace('Ã¡', 'á', $corrected);
                    $corrected = str_replace('Ã©', 'é', $corrected);
                    $corrected = str_replace('Ã­', 'í', $corrected);
                    $corrected = str_replace('Ã³', 'ó', $corrected);
                    $corrected = str_replace('Ãº', 'ú', $corrected);
                    $corrected = str_replace('Ã£', 'ã', $corrected);
                    $corrected = str_replace('Ã§', 'ç', $corrected);
                    $corrected = str_replace('Ã', 'Á', $corrected);
                    $corrected = str_replace('Ã‰', 'É', $corrected);
                    $corrected = str_replace('Ã', 'Í', $corrected);
                    $corrected = str_replace('Ã"', 'Ó', $corrected);
                    $corrected = str_replace('Ãš', 'Ú', $corrected);
                    $corrected = str_replace('Ãƒ', 'Ã', $corrected);
                    $corrected = str_replace('Ã‡', 'Ç', $corrected);
                }
                
                // Verificar se a correção resultou em algo válido
                if ($corrected !== $value && mb_check_encoding($corrected, 'UTF-8')) {
                    $updates[$field] = $corrected;
                    $changes[] = [
                        'field' => $field,
                        'before' => $original,
                        'after' => $corrected
                    ];
                }
            }
        }
        
        // Atualizar se houver correções
        if (!empty($updates)) {
            $setParts = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $setParts[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
            $params[':id'] = $id;
            
            $sql = "UPDATE menu_items SET " . implode(', ', $setParts) . " WHERE id = :id";
            $updateStmt = db()->prepare($sql);
            $updateStmt->execute($params);
            $fixed++;
            
            echo "<div class='info'>";
            echo "<strong>Item #{$id} - Label: " . h($item['label']) . "</strong><br>\n";
            foreach ($changes as $change) {
                echo "  Campo '{$change['field']}':<br>\n";
                echo "  <pre>Antes:  " . h($change['before']) . "\nDepois: " . h($change['after']) . "</pre>\n";
            }
            echo "</div>\n";
        }
    }
    
    echo "<hr>\n";
    if ($fixed > 0) {
        echo "<p class='success'><strong>Concluído! {$fixed} item(s) corrigido(s).</strong></p>\n";
    } else {
        echo "<p class='success'>Nenhum item precisou ser corrigido. Todos os dados estão com encoding correto!</p>\n";
    }
    echo "<p><small>Você pode deletar este arquivo agora.</small></p>\n";
    
} catch (Throwable $e) {
    echo "<p class='error'><strong>Erro:</strong> " . h($e->getMessage()) . "</p>\n";
    echo "<pre>" . h($e->getTraceAsString()) . "</pre>\n";
}
?>
</body>
</html>

