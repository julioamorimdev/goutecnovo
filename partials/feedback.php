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

// Buscar feedbacks ativos
$feedbacks = [];
try {
    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $feedbacks = db()->query("SELECT * FROM feedback_items WHERE is_enabled=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    }
} catch (Throwable $e) {
    // Tabela pode não existir ainda ou erro na conexão, usar valores vazios
    $feedbacks = [];
}
?>

<!-- Feedback -->
<section class="pt-60 pb-60">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <h2 data-sal="slide-up" data-sal-duration="500" data-sal-delay="200" data-sal-easing="ease-in-out-sine">Feedbacks</h2>
            </div>
        </div>
        <?php if (!empty($feedbacks)): ?>
            <div class="mt-8 position-relative" data-sal="fade" data-sal-duration="1500" data-sal-delay="200" data-sal-easing="ease-in-out-sine">
                <style>
                    .feedback-slider .swiper-slide {
                        height: auto;
                        display: flex;
                    }
                    .feedback-slider .swiper-slide > div {
                        width: 100%;
                        display: flex;
                        flex-direction: column;
                    }
                </style>
                <div class="feedback-slider swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($feedbacks as $fb): ?>
                            <div class="swiper-slide">
                                <div class="bg-white p-7 shadow-sm rounded-3 h-100 d-flex flex-column">
                                    <?php 
                                    $showBrand = !isset($fb['show_brand_image']) || (int)($fb['show_brand_image'] ?? 1) === 1;
                                    if (!empty($fb['brand_image']) && $showBrand): 
                                    ?>
                                        <div class="d-flex align-items-center justify-content-between border-bottom border-secondary pb-5">
                                            <img src="<?= h($fb['brand_image']) ?>" alt="image" class="img-fluid" style="max-height: 40px;">
                                            <img src="/assets/img/shape/feedback-quate.png" alt="image" class="img-fluid">
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-end border-bottom border-secondary pb-5">
                                            <img src="/assets/img/shape/feedback-quate.png" alt="image" class="img-fluid">
                                        </div>
                                    <?php endif; ?>
                                    <h6 class="mt-5 mb-0"><?= h($fb['title']) ?></h6>
                                    <p class="mt-3 mb-0 flex-grow-1" style="min-height: 60px;"><?= h($fb['text']) ?></p>
                                    <div class="d-flex align-items-center gap-4 mt-7">
                                        <img src="<?= h($fb['person_image']) ?>" alt="<?= h($fb['person_name']) ?>" class="img-fluid" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; flex-shrink: 0;">
                                        <div>
                                            <h6 class="fs-16 mb-0"><?= h($fb['person_name']) ?></h6>
                                            <small><?= h($fb['person_role']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="feedback-navs d-flex align-items-center justify-content-between position-relative w-100 z-2 d-none d-md-flex">
                    <span class="feedback-button-next w-10 h-10 rounded-circle bg-dark d-flex align-items-center justify-content-center text-white"><i class="las la-arrow-right"></i></span>
                    <span class="feedback-button-prev w-10 h-10 rounded-circle bg-dark d-flex align-items-center justify-content-center text-white"><i class="las la-arrow-left"></i></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section><!-- Feedback -->
