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

// Buscar artigos do blog
$featuredPost = null;
$sidePosts = [];
try {
    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        // Buscar artigo em destaque
        $stmt = db()->prepare("SELECT * FROM blog_posts WHERE is_featured=1 AND is_enabled=1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stmt->execute();
        $featuredPost = $stmt->fetch();
        
        // Buscar artigos laterais (não destacados)
        $stmt = db()->prepare("SELECT * FROM blog_posts WHERE is_featured=0 AND is_enabled=1 ORDER BY sort_order ASC, id ASC LIMIT 3");
        $stmt->execute();
        $sidePosts = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    // Tabela pode não existir ainda ou erro na conexão, usar valores vazios
    $featuredPost = null;
    $sidePosts = [];
}

if (!function_exists('formatDate')) {
    function formatDate(string $date): string {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date; // Retorna a data original se não conseguir converter
        }
        $months = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
        ];
        $day = date('d', $timestamp);
        $month = (int)date('m', $timestamp);
        $year = date('Y', $timestamp);
        return "{$day} de {$months[$month]} de {$year}";
    }
}

// Função para corrigir URLs do blog para usar o subdomínio correto
if (!function_exists('fixBlogUrl')) {
    function fixBlogUrl(string $url): string {
        // Se a URL começa com /central/, sempre converter para usar o subdomínio
        // Isso garante que os links funcionem tanto no site principal quanto no subdomínio
        if (strpos($url, '/central/') === 0) {
            // Remover /central/ e adicionar o subdomínio
            $path = str_replace('/central/', '/', $url);
            return 'https://central.goutec.com.br' . $path;
        }
        // Se já for uma URL completa, retornar como está
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        // Para URLs relativas que não começam com /central/, retornar como está
        return $url;
    }
}
?>

<!-- Blog -->
<section class="bg-dark pt-120 pb-120">
    <div class="pb-40">
        <div class="container">
            <div class="row g-4 justify-content-between align-items-center">
                <div class="col-xxl-5 col-xl-7">
                    <div class="px-3 py-1 border border-primary rounded d-inline-flex align-items-center gap-2 mb-3" data-sal="slide-up" data-sal-duration="500" data-sal-delay="200" data-sal-easing="ease-in-out-sine">
                        <div class="w-2 h-2 rounded-circle bg-primary"></div>
                        <small class="text-primary fw-bold">Notícias e Artigos</small>
                    </div>
                    <h2 class="text-white mb-0">Notícias e Artigos do Blog da GouTec</h2>
                </div>
                <div class="col-lg-5">
                    <div class="text-xl-end" data-sal="slide-up" data-sal-duration="500" data-sal-delay="300" data-sal-easing="ease-in-out-sine">
                        <a href="https://central.goutec.com.br/blog.php" class="btn btn-primary btn-arrow btn-lg fs-14 fw-medium rounded">
                            <span class="btn-arrow__text">
                                Ver mais artigos
                                <span class="btn-arrow__icon">
                                    <i class="las la-arrow-right"></i>
                                </span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($featuredPost || !empty($sidePosts)): ?>
        <div class="container">
            <div class="row">
                <?php if ($featuredPost): ?>
                    <div class="col-xl-7" data-sal="fade" data-sal-duration="500" data-sal-delay="200" data-sal-easing="ease-in-out-sine">
                        <div class="single-blog-post">
                            <img src="<?= h($featuredPost['image']) ?>" alt="<?= h($featuredPost['title']) ?>" class="img-fluid w-100">
                            <div class="blog-post-content px-6 px-md-8 py-9">
                                <div class="d-flex flex-wrap align-items-center gap-2 gap-md-4 mb-3">
                                    <div class="d-flex align-items-center gap-1 lh-1">
                                        <span class="text-white fs-20"><i class="las la-calendar"></i></span>
                                        <p class="text-white fs-14 fw-medium mb-0"><?= formatDate($featuredPost['published_date']) ?></p>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 lh-1">
                                        <span class="text-white fs-20"><i class="las la-edit"></i></span>
                                        <p class="text-white fs-14 fw-medium mb-0"><?= h($featuredPost['author']) ?></p>
                                    </div>
                                </div>
                                <h5 class="mb-6">
                                    <a href="<?= h(fixBlogUrl($featuredPost['url'])) ?>" class="text-decoration-none text-white hover:text-primary transition">
                                        <?= h($featuredPost['title']) ?>
                                    </a>
                                </h5>
                                <a href="<?= h(fixBlogUrl($featuredPost['url'])) ?>" class="text-decoration-none d-inline-flex align-items-center gap-2 text-primary fw-semibold btn-arrow">
                                    <span class="d-inline-block btn-arrow__text">
                                        Ler mais
                                        <span class="btn-arrow__icon">
                                            <i class="las la-arrow-right"></i>
                                        </span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($sidePosts)): ?>
                    <div class="col-xl-5">
                        <div class="row">
                            <?php 
                            $delays = [300, 400, 500];
                            foreach ($sidePosts as $index => $post): 
                                $delay = $delays[$index] ?? 300;
                            ?>
                                <div class="col-12" data-sal="slide-up" data-sal-duration="500" data-sal-delay="<?= $delay ?>" data-sal-easing="ease-in-out-sine">
                                    <div class="side-blog-item px-6 py-7 d-flex align-items-center justify-content-between gap-5 rounded-3 transition">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 text-decoration-none mb-1">
                                                <span class="text-white text-opacity-75 fs-20"><i class="las la-edit"></i></span>
                                                <span class="d-inline-block text-white text-opacity-75 fs-14 fw-medium mb-0"><?= h($post['author']) ?></span>
                                            </div>
                                            <h6 class="text-white mb-4">
                                                <a href="<?= h(fixBlogUrl($post['url'])) ?>" class="d-inline-block text-decoration-none text-white hover:text-primary transition max-text-28">
                                                    <?= h($post['title']) ?>
                                                </a>
                                            </h6>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-white text-opacity-75 fs-20"><i class="las la-calendar"></i></span>
                                                <p class="text-white text-opacity-75 fs-14 fw-medium mb-0"><?= formatDate($post['published_date']) ?></p>
                                            </div>
                                        </div>
                                        <a href="<?= h(fixBlogUrl($post['url'])) ?>" class="arrow-btn d-grid place-content-center w-8 h-8 rounded-circle border border-secondary flex-shrink-0 transition opacity-25">
                                            <span class="text-secondary fs-16 d-inline-block"><i class="las la-arrow-right"></i></span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section><!-- Blog -->
