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

// Buscar todos os TLDS ativos do banco de dados
$tlds = [];
try {
    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        // Buscar todos os TLDS ativos
        $stmt = db()->prepare("SELECT * FROM tlds WHERE is_enabled=1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        $tlds = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    // Tabela pode não existir ainda ou erro na conexão, usar valores vazios
    $tlds = [];
}
?>

<?php if (!empty($tlds)): ?>
    <tbody>
        <?php foreach ($tlds as $tld): ?>
            <tr>
                <th class="shadow-none">
                    <p class="text-body fw-semibold mb-0"><?= h($tld['tld']) ?></p>
                </th>
                <th class="shadow-none">
                    <p class="text-body fw-semibold mb-0">R$ <?= number_format((float)$tld['price_register'], 2, ',', '.') ?></p>
                </th>
                <th class="shadow-none">
                    <p class="text-body fw-semibold mb-0">R$ <?= number_format((float)$tld['price_renew'], 2, ',', '.') ?></p>
                </th>
                <th class="shadow-none">
                    <p class="text-body fw-semibold mb-0">R$ <?= number_format((float)$tld['price_transfer'], 2, ',', '.') ?></p>
                </th>
                <th class="shadow-none">
                    <p class="text-body fw-semibold mb-0"><?= (int)($tld['privacy_protection_available'] ?? 0) === 1 ? 'Grátis' : '-' ?></p>
                </th>
            </tr>
        <?php endforeach; ?>
    </tbody>
<?php endif; ?>

