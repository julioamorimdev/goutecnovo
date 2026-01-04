<?php
declare(strict_types=1);
// Garantir encoding UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Tratar erros silenciosamente para não quebrar o HTML
error_reporting(0);
ini_set('display_errors', '0');

try {
    require_once __DIR__ . '/../app/bootstrap.php';
} catch (Throwable $e) {
    // Se houver erro no bootstrap, usar valores padrão
    // Não exibir erro para não quebrar o HTML
}

if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Buscar TLDS destacados do banco de dados
$featuredTlds = [];
try {
    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        // Buscar TLDS destacados e ativos
        $stmt = db()->prepare("SELECT * FROM tlds WHERE is_featured=1 AND is_enabled=1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        $featuredTlds = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    // Tabela pode não existir ainda ou erro na conexão, usar valores vazios
    $featuredTlds = [];
}

// Função para formatar preço
if (!function_exists('formatPrice')) {
    function formatPrice(float $price): string {
        return 'R$' . number_format($price, 2, ',', '.');
    }
}

// Função para obter classe CSS baseada no TLD
if (!function_exists('getTldColorClass')) {
    function getTldColorClass(string $tld): string {
        $colors = [
            '.com.br' => 'text-primary',
            '.com' => 'text-danger',
            '.net' => 'text-warning',
            '.org' => 'text-warning',
            '.info' => 'text-success',
            '.biz' => 'text-info',
        ];
        return $colors[$tld] ?? 'text-secondary';
    }
}
?>

<?php if (!empty($featuredTlds)): ?>
<!-- Select de TLDS para o formulário de busca -->
<select class="border-0 bg-transparent" id="tld-select">
    <?php foreach ($featuredTlds as $tld): ?>
        <option value="<?= h($tld['tld']) ?>"><?= h($tld['tld']) ?></option>
    <?php endforeach; ?>
</select>
<?php else: ?>
<!-- Fallback se não houver TLDS destacados -->
<select class="border-0 bg-transparent" id="tld-select">
    <option value=".com.br">.com.br</option>
    <option value=".com">.com</option>
    <option value=".net">.net</option>
    <option value=".org">.org</option>
</select>
<?php endif; ?>


