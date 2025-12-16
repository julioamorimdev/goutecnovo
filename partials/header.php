<?php
// Garantir UTF-8 antes de qualquer output
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
    require_once __DIR__ . '/../app/menu.php';

    // Garantir UTF-8 na conexão
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $items = menu_build_tree(menu_fetch_all_enabled());
    } else {
        $items = [];
    }
} catch (Throwable $e) {
    // Se houver erro, usar array vazio
    $items = [];
}

function site_logo_current(string $theme, string $fallback): string {
    try {
        $stmt = db()->prepare("
            SELECT file_path
            FROM site_logos
            WHERE theme = ?
              AND is_deleted = 0
              AND (start_at IS NULL OR start_at <= NOW())
              AND (end_at IS NULL OR end_at >= NOW())
            ORDER BY COALESCE(start_at, created_at) DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$theme]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) return (string)$row['file_path'];
    } catch (Throwable $e) {
        // fallback silencioso
    }
    return $fallback;
}

$logoDarkTheme = site_logo_current('light', 'assets/img/logo-light.png'); // tema escuro do site usa logo "light"
$logoLightTheme = site_logo_current('dark', 'assets/img/logo-dark.png'); // tema claro do site usa logo "dark"

function render_menu_item(array $item): void {
    $label = h($item['label'] ?? '');
    $url = $item['url'] ?: '#';
    $target = !empty($item['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
    $children = $item['children'] ?? [];

    if (!empty($item['custom_html'])) {
        // HTML customizado (admin-only). Renderiza exatamente como está no banco.
        echo $item['custom_html'];
        return;
    }

    if ($children) {
        $layoutType = (string)($item['dropdown_layout'] ?? 'default');

        // Mega menu (simplificado, mas compatível com o CSS do template)
        if ($layoutType === 'mega') {
            echo '<li class="nav-item contain-mega-menu">';
            echo '<a class="nav-link fw-medium" href="#">' . $label . '</a>';
            echo '<div class="contain-mega-menu__content">';
            echo '<div class="container p-0">';
            echo '<div class="row g-0 align-items-center">';
            echo '<div class="col-12">';
            echo '<div class="h-100 pt-32 pb-32 px-6">';
            echo '<span class="h6 d-block fs-18">' . $label . '</span>';
            echo '<ul class="contain-mega-menu__list list-unstyled">';
            foreach ($children as $child) {
                $childLabel = h($child['label'] ?? '');
                $childUrl = $child['url'] ?: '#';
                $childTarget = !empty($child['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                $iconClass = trim((string)($child['icon_class'] ?? ''));
                $desc = trim((string)($child['description'] ?? ''));

                echo '<li>';
                echo '<a href="' . h($childUrl) . '" class="contain-mega-menu__link text-decoration-none d-flex align-items-start gap-2"' . $childTarget . '>';
                echo '<span class="contain-mega-menu__img">';
                if ($iconClass) echo '<i class="' . h($iconClass) . '"></i>';
                echo '</span>';
                echo '<span class="flex-grow-1">';
                echo '<span class="contain-mega-menu__title d-flex">' . $childLabel . '</span>';
                if ($desc) echo '<span class="contain-mega-menu__description">' . h($desc) . '</span>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div></div></div></div>';
            echo '</div>';
            echo '</li>';
            return;
        }

        // Dropdown normal (contain-sub-1)
        $layout = $layoutType === 'xl' ? ' contain-sub-1__content-xl' : '';
        echo '<li class="nav-item contain-sub-1">';
        echo '<a class="nav-link fw-medium" href="#">' . $label . '</a>';
        echo '<ul class="contain-sub-1__content' . $layout . ' list-unstyled">';
        foreach ($children as $child) {
            $childLabel = h($child['label'] ?? '');
            $childUrl = $child['url'] ?: '#';
            $childTarget = !empty($child['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $iconClass = trim((string)($child['icon_class'] ?? ''));
            $desc = trim((string)($child['description'] ?? ''));
            $badgeText = trim((string)($child['badge_text'] ?? ''));
            $badgeClass = trim((string)($child['badge_class'] ?? ''));

            echo '<li>';
            echo '<a href="' . h($childUrl) . '" class="contain-sub-1__link text-decoration-none d-flex align-items-start gap-2"' . $childTarget . '>';
            echo '<span class="contain-sub-1__img">';
            if ($iconClass) {
                echo '<i class="' . h($iconClass) . '"></i>';
            }
            echo '</span>';
            echo '<span class="flex-grow-1">';
            if ($badgeText) {
                echo '<span class="contain-sub-1__title d-flex align-items-center justify-content-between gap-2">';
                echo '<span class="d-inline-block">' . $childLabel . '</span>';
                echo '<span class="' . h($badgeClass ?: 'flex-shrink-0 badge bg-primary-subtle text-primary-emphasis fw-bold py-1') . '">' . h($badgeText) . '</span>';
                echo '</span>';
            } else {
                echo '<span class="contain-sub-1__title d-flex">' . $childLabel . '</span>';
            }
            if ($desc) {
                echo '<span class="contain-sub-1__description">' . h($desc) . '</span>';
            }
            echo '</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</li>';
        return;
    }

    echo '<li class="nav-item">';
    echo '<a class="nav-link fw-medium" href="' . h($url) . '"' . $target . '>' . $label . '</a>';
    echo '</li>';
}
?>

<!-- Header -->
<div class="navbar-overlay bg-body bg-opacity-5">
    <!-- Primary Header -->
    <nav class="navbar navbar-1 navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand logo" href="index.html">
                <img src="<?= h($logoDarkTheme) ?>" alt="GouTec" class="logo__img">
                <img src="<?= h($logoLightTheme) ?>" alt="GouTec" class="logo__img logo__sticky">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryMenu" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primaryMenu">
                <ul class="navbar-nav align-items-lg-center gap-lg-3 ms-auto">
                    <?php foreach ($items as $it) render_menu_item($it); ?>
                </ul>
            </div>
        </div>
    </nav>
    <!-- /Primary Header -->

    <style>
        /* Tema claro opcional via data-navbar-theme="light" */
        .navbar-overlay[data-navbar-theme="light"] {
            background-color: #ffffff !important;
            box-shadow: 0 20px 60px rgba(12, 22, 44, 0.08);
        }

        .navbar-overlay[data-navbar-theme="light"] .navbar {
            --bs-navbar-color: #0f172a;
            --bs-navbar-hover-color: var(--bs-primary);
            --bs-navbar-active-color: var(--bs-primary);
            background-color: transparent;
        }

        /* Força o menu do template (navbar-1) a ficar legível no tema claro */
        .navbar-overlay[data-navbar-theme="light"] .navbar-1 .nav-link {
            color: var(--bs-navbar-color) !important;
            opacity: 1 !important;
        }

        .navbar-overlay[data-navbar-theme="light"] .navbar-1 .nav-link:hover,
        .navbar-overlay[data-navbar-theme="light"] .navbar-1 .nav-link:focus,
        .navbar-overlay[data-navbar-theme="light"] .navbar-1 .nav-link.active {
            color: var(--bs-navbar-hover-color) !important;
            opacity: 1 !important;
        }

        .navbar-overlay[data-navbar-theme="light"] .navbar-brand .logo__img {
            display: none;
        }

        .navbar-overlay[data-navbar-theme="light"] .navbar-brand .logo__img.logo__sticky {
            display: block;
        }

        /* Tema escuro padrão (quando não for light) */
        .navbar-overlay:not([data-navbar-theme="light"]) {
            background-color: #080f2c;
        }
    </style>
</div><!-- Header -->


