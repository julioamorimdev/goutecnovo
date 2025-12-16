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

// Função auxiliar para buscar configurações do footer
function footer_get_setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            if (function_exists('db')) {
                $stmt = db()->query("SELECT setting_key, setting_value FROM footer_settings");
                foreach ($stmt->fetchAll() as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'] ?? '';
                }
            }
        } catch (Throwable $e) {
            // Tabela pode não existir ainda
        }
    }
    return $settings[$key] ?? $default;
}

if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Buscar seções e links do footer
$sections = [];
$linksBySection = [];
try {
    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $sections = db()->query("SELECT * FROM footer_sections WHERE is_enabled=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
        foreach ($sections as $sec) {
            $stmt = db()->prepare("SELECT * FROM footer_links WHERE section_id=? AND is_enabled=1 ORDER BY sort_order ASC, id ASC");
            $stmt->execute([(int)$sec['id']]);
            $linksBySection[(int)$sec['id']] = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    // Tabelas podem não existir ainda ou erro na conexão, usar valores vazios
    $sections = [];
    $linksBySection = [];
}

// Configurações do footer
$logoUrl = footer_get_setting('logo_url', 'assets/img/logo-dark.png');
$description = footer_get_setting('description', 'Se você tem um site de e-commerce ou um site de negócios, você quer atrair o maior número de visitantes possível ou quando você não quer mais ser limitado por');
$showNewsletter = footer_get_setting('show_newsletter', '1') === '1';
$copyright = footer_get_setting('copyright', '&copy; 2024 GouTec. Todos os direitos reservados');

// Redes sociais
$socialLinks = [
    'twitter' => footer_get_setting('social_twitter', '#'),
    'facebook' => footer_get_setting('social_facebook', '#'),
    'dribbble' => footer_get_setting('social_dribbble', '#'),
    'behance' => footer_get_setting('social_behance', '#'),
];
?>

<!-- Footer -->
<footer class="pt-120 pb-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <img src="<?= h($logoUrl) ?>" alt="image" class="logo__img">
                <p class="mt-10 mb-8"><?= h($description) ?></p>
                <?php if ($showNewsletter): ?>
                    <h6 class="mb-3 fs-16">Inscreva-se no nosso newsletter</h6>
                    <form action="#" class="domain-form-one position-relative">
                        <input type="text" class="form-control p-4 rounded-pill" placeholder="Digite seu email">
                        <div class="domain-submit-box d-flex align-items-center gap-3 position-absolute">
                            <button class="btn px-4 btn-primary rounded-circle" type="submit"><span class="fs-16"><i
                                        class="las la-arrow-right"></i></span></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php 
            // Calcular largura das colunas baseado no número de seções
            $numSections = count($sections);
            if ($numSections > 0) {
                // Calcular classe de coluna baseado no número de seções
                if ($numSections <= 3) {
                    $colSize = (int)(12 / ($numSections + 1));
                    $colClass = 'col-lg-' . $colSize;
                } else {
                    $colClass = 'col-lg-3';
                }
                $colClass .= ' col-md-6';
                
                foreach ($sections as $sec):
                    $secId = (int)$sec['id'];
                    $links = $linksBySection[$secId] ?? [];
            ?>
                <div class="<?= h($colClass) ?>">
                    <div class="ps-xl-10">
                        <h6 class="fs-16 mt-3 mb-10"><?= h($sec['title']) ?></h6>
                        <?php if (!empty($links)): ?>
                            <ul class="list-unstyled d-flex flex-column gap-2">
                                <?php foreach ($links as $link): ?>
                                    <li>
                                        <a href="<?= h($link['url']) ?>" class="text-decoration-none text-body hover:text-primary fs-14">
                                            <?= h($link['label']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                endforeach;
            }
            ?>
        </div>
        <div class="mt-20">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-5 pt-5 border-top">
                <p class="mb-0 fs-14"><?= $copyright ?></p>
                <div class="d-inline-flex align-items-center justify-content-center gap-2">
                    <?php if ($socialLinks['twitter'] !== ''): ?>
                        <a href="<?= h($socialLinks['twitter']) ?>" class="social-icon w-9 h-9 d-inline-flex align-items-center justify-content-center rounded-circle border">
                            <span class="text-body"><i class="lab la-twitter"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($socialLinks['facebook'] !== ''): ?>
                        <a href="<?= h($socialLinks['facebook']) ?>" class="social-icon w-9 h-9 d-inline-flex align-items-center justify-content-center rounded-circle border">
                            <span class="text-body"><i class="lab la-facebook-f"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($socialLinks['dribbble'] !== ''): ?>
                        <a href="<?= h($socialLinks['dribbble']) ?>" class="social-icon w-9 h-9 d-inline-flex align-items-center justify-content-center rounded-circle border">
                            <span class="text-body"><i class="lab la-dribbble"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($socialLinks['behance'] !== ''): ?>
                        <a href="<?= h($socialLinks['behance']) ?>" class="social-icon w-9 h-9 d-inline-flex align-items-center justify-content-center rounded-circle border">
                            <span class="text-body"><i class="lab la-behance"></i></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</footer><!-- Footer -->
