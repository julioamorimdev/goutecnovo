<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// Garantir UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

/**
 * Escape seguro para valores que podem vir do banco como int/float/array (evita TypeError com strict_types).
 */
function hs(mixed $v): string {
    if ($v === null) return '';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_scalar($v)) return h((string)$v);
    return h((string)json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Obter categoria do parâmetro
$categorySlug = $_GET['categoria'] ?? null;

// Buscar categorias e planos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Buscar todas as categorias ativas
    $categories = db()->query("
        SELECT * FROM plan_categories 
        WHERE is_enabled = 1 
        ORDER BY sort_order ASC, id ASC
    ")->fetchAll();
    
    // Buscar planos
    if ($categorySlug) {
        // Buscar categoria específica
        $catStmt = db()->prepare("SELECT id FROM plan_categories WHERE slug = ? AND is_enabled = 1");
        $catStmt->execute([$categorySlug]);
        $category = $catStmt->fetch();
        
        if ($category) {
            $categoryId = (int)$category['id'];
            $plansStmt = db()->prepare("
                SELECT p.*, pc.name as category_name, pc.slug as category_slug, pc.icon_class as category_icon
                FROM plans p
                LEFT JOIN plan_categories pc ON p.category_id = pc.id
                WHERE p.category_id = ? AND p.is_enabled = 1
                ORDER BY p.sort_order ASC, p.id ASC
            ");
            $plansStmt->execute([$categoryId]);
            $plans = $plansStmt->fetchAll();
            $currentCategoryStmt = db()->prepare("SELECT * FROM plan_categories WHERE id = ?");
            $currentCategoryStmt->execute([$categoryId]);
            $currentCategory = $currentCategoryStmt->fetch();
        } else {
            $plans = [];
            $currentCategory = null;
        }
    } else {
        // Buscar todos os planos ativos
        $plansStmt = db()->query("
            SELECT p.*, pc.name as category_name, pc.slug as category_slug, pc.icon_class as category_icon
            FROM plans p
            LEFT JOIN plan_categories pc ON p.category_id = pc.id
            WHERE p.is_enabled = 1
            ORDER BY pc.sort_order ASC, p.sort_order ASC, p.id ASC
        ");
        $plans = $plansStmt->fetchAll();
        $currentCategory = null;
    }
} catch (Throwable $e) {
    $categories = [];
    $plans = [];
    $currentCategory = null;
}

// Cores para os cards (ciclo)
$cardColors = [
    ['bg' => 'rgba(109,94,252,.18)', 'border' => 'rgba(109,94,252,.25)', 'icon' => 'rgba(109,94,252,.14)', 'iconBorder' => 'rgba(109,94,252,.22)', 'btn' => 'linear-gradient(90deg, #6d5efc, #8b7aff)'],
    ['bg' => 'rgba(61,214,245,.18)', 'border' => 'rgba(61,214,245,.25)', 'icon' => 'rgba(61,214,245,.14)', 'iconBorder' => 'rgba(61,214,245,.22)', 'btn' => 'linear-gradient(90deg, #3dd6f5, #5de5ff)'],
    ['bg' => 'rgba(43,213,118,.18)', 'border' => 'rgba(43,213,118,.25)', 'icon' => 'rgba(43,213,118,.14)', 'iconBorder' => 'rgba(43,213,118,.22)', 'btn' => 'linear-gradient(90deg, #2bd576, #4ae586)'],
    ['bg' => 'rgba(255,211,78,.18)', 'border' => 'rgba(255,211,78,.25)', 'icon' => 'rgba(255,211,78,.14)', 'iconBorder' => 'rgba(255,211,78,.22)', 'btn' => 'linear-gradient(90deg, #ffd34e, #ffe370)'],
    ['bg' => 'rgba(255,107,107,.18)', 'border' => 'rgba(255,107,107,.25)', 'icon' => 'rgba(255,107,107,.14)', 'iconBorder' => 'rgba(255,107,107,.22)', 'btn' => 'linear-gradient(90deg, #ff6b6b, #ff8b8b)'],
    ['bg' => 'rgba(156,136,255,.18)', 'border' => 'rgba(156,136,255,.25)', 'icon' => 'rgba(156,136,255,.14)', 'iconBorder' => 'rgba(156,136,255,.22)', 'btn' => 'linear-gradient(90deg, #9c88ff, #b5a5ff)'],
];

// Carregar menu e footer (server-side) para evitar dependência de fetch no navegador
$includesDir = realpath(__DIR__ . '/../includes') ?: (__DIR__ . '/../includes');
$menuFile = $includesDir . '/menu.php';
$footerFile = $includesDir . '/footer.html';
$menuHtml = '';
ob_start();
if (is_file($menuFile)) {
    require $menuFile;
} else {
    echo '<!-- menu.php não encontrado em ' . h($menuFile) . ' -->';
}
$menuHtml = ob_get_clean();
$footerHtml = is_file($footerFile) ? (string)file_get_contents($footerFile) : '';

// Fallback visível caso o footer não exista/esteja vazio no ambiente atual
if (trim($footerHtml) === '') {
    $footerHtml = '
    <footer class="footer" role="contentinfo">
      <div class="container footer__inner">
        <div class="footer__left">
          <img class="footer__logo" src="/admin/assets/img/logo-light.png" alt="GouTec">
          <div class="footer__copy">© ' . date('Y') . ' GouTec. Todos os direitos reservados.</div>
        </div>
        <div class="footer__center">
          <div class="footer__actions">
            <a class="footer__btn" href="/contato"><i class="las la-envelope" aria-hidden="true"></i> Contato</a>
            <a class="footer__btn footer__btn--ghost" href="/termos"><i class="las la-file-contract" aria-hidden="true"></i> Termos de Serviços</a>
          </div>
        </div>
      </div>
    </footer>
    ';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentCategory ? h($currentCategory['name']) : 'Produtos' ?> - Central GouTec</title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        :root{
            --bg0:#070a12;
            --bg1:#0b1220;
            --panel:rgba(255,255,255,.06);
            --border:rgba(255,255,255,.12);
            --text:#e8edf7;
            --muted:rgba(232,237,247,.72);
            --primary:#6d5efc;
            --primary2:#3dd6f5;
            --good:#2bd576;
            --warn:#ffd34e;
            --shadow: 0 18px 60px rgba(0,0,0,.55);
        }
        *{ box-sizing:border-box; margin:0; padding:0; }
        html,body{ height:100%; }
        body{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background:
              radial-gradient(900px 500px at 15% 12%, rgba(109,94,252,.18) 0%, transparent 65%),
              radial-gradient(800px 420px at 85% 18%, rgba(61,214,245,.14) 0%, transparent 60%),
              radial-gradient(700px 420px at 60% 88%, rgba(43,213,118,.10) 0%, transparent 65%),
              linear-gradient(180deg, var(--bg0), var(--bg1));
            color: var(--text);
            overflow-x:hidden;
            position: relative;
        }

        /* Efeito de neve */
        #snowflakes{
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 999; /* acima do conteúdo */
            overflow: hidden;
        }
        .snowflake {
            position: absolute;
            top: -10px;
            color: #fff;
            font-size: 1em;
            font-family: Arial;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.8);
            animation: fall linear infinite;
            pointer-events: none;
            z-index: 999;
        }

        @keyframes fall {
            to {
                transform: translateY(110vh) rotate(360deg);
            }
        }

        a{ color:inherit; text-decoration:none; }
        /* manter igual ao /central/index.html para o menu/footer ficarem idênticos */
        .container{ width:min(1200px, 92vw); margin:0 auto; }
        .app{ min-height:100vh; display:flex; flex-direction:column; }
        #menu, #footer{ width:100%; }

        /* MENU (estiliza o HTML do includes/menu.html) */
        .topbar{
            position:sticky; top:0; z-index:50;
            backdrop-filter: blur(14px);
            background: linear-gradient(180deg, rgba(7,10,18,.85), rgba(7,10,18,.55));
            border-bottom: 1px solid var(--border);
        }
        .topbar__inner{ display:flex; align-items:center; gap:16px; padding: 14px 0; }
        .brand{ display:flex; align-items:center; gap:12px; }
        .brand__logo{ height: 28px; width:auto; filter: drop-shadow(0 8px 18px rgba(0,0,0,.35)); }

        /* Barra de busca removida */

        .nav{ display:flex; align-items:center; gap:10px; margin-left:auto; }
        .nav__toggle{
            display:none;
            border:1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            color: var(--text);
            border-radius: 10px;
            padding: 9px 10px;
        }
        .nav__list{ list-style:none; display:flex; align-items:center; gap:8px; }
        .nav__item{ position:relative; }
        .nav__link{
            display:flex; align-items:center; gap:10px;
            padding: 10px 12px;
            border-radius: 12px;
            color: rgba(232,237,247,.9);
            border: 1px solid transparent;
            transition: .18s ease;
            white-space:nowrap;
        }
        .nav__link i{ opacity:.9; }
        .nav__link:hover{
            background: rgba(255,255,255,.06);
            border-color: rgba(255,255,255,.10);
        }
        .nav__link--btn{ cursor:pointer; background:transparent; font: inherit; }
        .nav__link--btn .la-angle-down{ opacity:.75; }
        .nav__link--support{
            background: linear-gradient(90deg, rgba(109,94,252,.18), rgba(61,214,245,.14));
            border-color: rgba(109,94,252,.25);
        }

        .dropdown{
            position:absolute; left:0; top: 100%;
            width: auto;
            min-width: 260px;
            max-width: min(360px, 92vw);
            border:1px solid rgba(255,255,255,.14);
            background: rgba(13,18,32,.92);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 10px;
            box-shadow: var(--shadow);
            display:none;
            max-height: 70vh;
            overflow-y:auto;
        }
        .nav__item--dropdown:hover > .dropdown{ display:block; }
        .nav__item--dropdown.is-open > .dropdown{ display:block; }
        .nav__item--align-right > .dropdown{
            left: auto;
            right: 0;
        }
        /* Ponte no PAI (funciona mesmo quando o dropdown ainda está hidden) */
        .nav__item--dropdown::after{
            content:"";
            position:absolute;
            left:0; right:0;
            top:100%;
            height:24px; /* ponte maior para movimentos diagonais */
        }

        .dropdown__item{
            display:flex; gap:12px; align-items:flex-start;
            padding: 10px 10px;
            border-radius: 12px;
            border: 1px solid transparent;
            transition: .18s ease;
        }
        .dropdown__item i{
            font-size: 1.25rem;
            color: rgba(61,214,245,.95);
            margin-top:2px;
            width: 22px;
            text-align:center;
        }
        .dropdown__item:hover{
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.10);
        }
        .dropdown__title{ font-weight: 800; font-size: .95rem; }
        .dropdown__desc{ color: rgba(232,237,247,.62); font-size: .85rem; margin-top: 2px; }

        .nav__cart{
            display:inline-flex; align-items:center; justify-content:center;
            width: 42px; height: 42px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            position:relative;
            transition: .18s ease;
        }
        .nav__cart:hover{ transform: translateY(-1px); background: rgba(255,255,255,.09); }
        .nav__cart i{ font-size: 1.3rem; }
        .nav__cartBadge{
            position:absolute; top:-6px; right:-6px;
            min-width: 20px; height: 20px;
            border-radius: 999px;
            display:flex; align-items:center; justify-content:center;
            font-size: .75rem; font-weight: 800;
            color: #061017;
            background: linear-gradient(90deg, var(--warn), #ffef96);
            border: 1px solid rgba(0,0,0,.35);
        }

        /* Avatar do usuário */
        .nav__link--user{
            display:flex;
            align-items:center;
            gap: 10px;
            padding: 8px 12px;
        }
        .nav__user-avatar{
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary2), var(--primary));
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,.15);
        }
        .nav__user-initial{
            font-size: 1.1rem;
            font-weight: 900;
            color: #061017;
            line-height: 1;
        }
        .nav__user-name{
            font-weight: 800;
            font-size: .95rem;
            color: rgba(232,237,247,.95);
        }
        .nav__item--user .nav__link--btn .la-angle-down{
            margin-left: 4px;
        }

        /* STORE LAYOUT */
        main{ flex:1; padding: 26px 0; }
        .store-layout{
            display:grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            align-items:start;
        }

        /* SIDEBAR */
        .sidebar{
            position:sticky;
            top: 80px;
            display:flex;
            flex-direction:column;
            gap: 16px;
        }

        .sidebar__section{
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 36px rgba(0,0,0,.28);
        }

        .sidebar__title{
            font-size: 1.1rem;
            font-weight: 900;
            margin-bottom: 14px;
            color: var(--text);
            display:flex;
            align-items:center;
            gap: 10px;
        }

        .sidebar__title i{
            color: var(--primary2);
            font-size: 1.2rem;
        }

        .category-list{
            list-style:none;
            display:flex;
            flex-direction:column;
            gap: 8px;
        }

        .category-item{
            display:flex;
            align-items:center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid transparent;
            background: rgba(255,255,255,.04);
            transition: .18s ease;
            text-decoration:none;
            color: var(--text);
        }

        .category-item:hover{
            background: rgba(255,255,255,.08);
            border-color: rgba(61,214,245,.25);
            transform: translateX(4px);
        }

        .category-item.active{
            background: rgba(61,214,245,.12);
            border-color: rgba(61,214,245,.30);
        }

        .category-item i{
            font-size: 1.2rem;
            color: rgba(61,214,245,.95);
            width: 24px;
            text-align:center;
        }

        .category-item__name{
            font-weight: 800;
            font-size: .95rem;
        }

        .action-list{
            list-style:none;
            display:flex;
            flex-direction:column;
            gap: 8px;
        }

        .action-item{
            display:flex;
            align-items:center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.05);
            transition: .18s ease;
            text-decoration:none;
            color: var(--text);
        }

        .action-item:hover{
            background: rgba(255,255,255,.09);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }

        .action-item i{
            font-size: 1.2rem;
            color: var(--good);
            width: 24px;
            text-align:center;
        }

        .action-item__text{
            font-weight: 800;
            font-size: .95rem;
        }

        /* PRODUCTS GRID */
        .products-header{
            margin-bottom: 24px;
        }

        .products-header__title{
            font-size: clamp(1.8rem, 3.2vw, 2.4rem);
            font-weight: 900;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .products-header__desc{
            color: rgba(232,237,247,.72);
            font-size: 1.05rem;
        }

        .products-grid{
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .product-card{
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 16px 60px rgba(0,0,0,.35);
            transition: .18s ease;
            position:relative;
            overflow:hidden;
            display:flex;
            flex-direction:column;
        }

        .product-card:hover{
            transform: translateY(-4px);
            border-color: rgba(61,214,245,.30);
        }

        .product-card__icon{
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin-bottom: 16px;
            font-size: 1.8rem;
        }

        .product-card__title{
            font-size: 1.4rem;
            font-weight: 950;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .product-card__desc{
            color: rgba(232,237,247,.65);
            font-size: .95rem;
            line-height: 1.6;
            margin-bottom: 16px;
            flex:1;
        }

        .product-card__features{
            list-style:none;
            margin-bottom: 20px;
            display:flex;
            flex-direction:column;
            gap: 8px;
        }

        .product-card__feature{
            display:flex;
            align-items:center;
            gap: 10px;
            color: rgba(232,237,247,.85);
            font-size: .9rem;
        }

        .product-card__feature i{
            font-size: .85rem;
            color: var(--good);
        }

        .product-card__price{
            margin-bottom: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        .product-card__price-label{
            color: rgba(232,237,247,.65);
            font-size: .85rem;
            margin-bottom: 4px;
        }

        .product-card__price-value{
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
        }

        .product-card__price-period{
            color: rgba(232,237,247,.65);
            font-size: .9rem;
            margin-left: 4px;
        }

        .product-card__btn{
            width:100%;
            border:none;
            cursor:pointer;
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 800;
            font-size: 1rem;
            display:flex;
            align-items:center;
            justify-content:center;
            gap: 10px;
            transition: .18s ease;
            color: #061017;
            text-decoration:none;
        }

        .product-card__btn:hover{
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,.3);
        }

        .product-card__btn i{
            font-size: 1.2rem;
        }

        .empty-state{
            grid-column: 1 / -1;
            text-align:center;
            padding: 60px 20px;
            color: rgba(232,237,247,.65);
        }

        .empty-state i{
            font-size: 4rem;
            margin-bottom: 20px;
            opacity:.5;
        }

        .empty-state__title{
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 10px;
        }

        /* Responsivo */
        @media (max-width: 980px){
            .store-layout{
                grid-template-columns: 1fr;
            }
            .sidebar{
                position:static;
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }
        }
        @media (max-width: 860px){
            .topbar__inner{ justify-content: space-between; }
            .nav{ margin-left:auto; }
            .nav__toggle{ display:inline-flex; }
            .nav__list{
                position: fixed;
                right: 14px; left: 14px;
                top: 70px;
                display:none;
                flex-direction:column;
                align-items:stretch;
                gap: 6px;
                padding: 10px;
                background: rgba(13,18,32,.92);
                border: 1px solid rgba(255,255,255,.14);
                border-radius: 18px;
                box-shadow: var(--shadow);
            }
            .nav__list.is-open{ display:flex; }
            .dropdown{ position: static; width: 100%; box-shadow:none; background: rgba(255,255,255,.05); }
            .nav__item--dropdown:hover > .dropdown{ display:none; }
            .nav__item--dropdown.is-open > .dropdown{ display:block; }
            .nav__user-name{
                display:none;
            }
            .nav__user-avatar{
                width: 32px;
                height: 32px;
            }
            .products-grid{
                grid-template-columns: 1fr;
            }
        }

        /* FOOTER (estiliza o HTML do includes/footer.html) */
        /* Footer deve sempre ficar visível no fim */
        #footer{
            margin-top: auto;
            flex: 0 0 auto;
            min-height: 1px;
            display:block;
            position:relative;
            z-index:2;
        }
        #footer *{ position:relative; z-index:2; }
        .footer{
            border-top: 1px solid var(--border);
            background: rgba(0,0,0,.22);
            padding: 22px 0;
            color: rgba(232,237,247,.92);
            display:block;
        }
        .footer__inner{ display:flex; align-items:center; justify-content:space-between; gap: 14px; flex-wrap:wrap; }
        .footer__logo{
            display:block;
            height:auto;
            max-height:32px;
            max-width:180px;
            width:auto;
            object-fit:contain;
            opacity:.95;
        }
        .footer__copy{ color: rgba(232,237,247,.62); font-size:.92rem; margin-top:6px; }
        .footer__left{ display:flex; flex-direction:column; gap:4px; }
        .footer__actions{ display:flex; gap:10px; flex-wrap:wrap; }
        .footer__btn{
            display:inline-flex; align-items:center; gap:8px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            color: rgba(232,237,247,.92);
            font-weight: 800;
            transition: .18s ease;
        }
        .footer__btn:hover{ transform: translateY(-1px); background: rgba(255,255,255,.09); }
        .footer__btn--ghost{ background: rgba(255,255,255,.04); }
        .footer__lang{ display:flex; align-items:center; gap:8px; color: rgba(232,237,247,.72); margin-top:10px; }
        .footer__select{
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.14);
            color: rgba(232,237,247,.92);
            border-radius: 12px;
            padding: 8px 10px;
            outline:none;
        }
        .footer__select option{ color:#0b1220; }
        .footer__social{
            width: 40px; height: 40px;
            border-radius: 14px;
            display:inline-flex; align-items:center; justify-content:center;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            transition: .18s ease;
            margin-left: 8px;
            color: rgba(232,237,247,.92);
        }
        .footer__social:hover{
            transform: translateY(-2px);
            background: rgba(255,255,255,.10);
            border-color: rgba(61,214,245,.28);
        }
        .footer__social i{ font-size: 1.25rem; }
    </style>
</head>
<body>
  <!-- Flocos de neve -->
  <div id="snowflakes" aria-hidden="true">
    <?php
      // Neve server-side (não depende de JS)
      for ($i = 0; $i < 42; $i++) {
          $left = mt_rand(0, 10000) / 100; // 0..100
          $dur = mt_rand(280, 720) / 100;  // 2.80..7.20s
          $delay = -1 * (mt_rand(0, 700) / 100); // 0..-7s (começa em posições diferentes)
          $opacity = mt_rand(35, 100) / 100; // 0.35..1
          $size = mt_rand(10, 20); // px
          echo '<div class="snowflake" style="left:' . $left . '%;animation-duration:' . $dur . 's;animation-delay:' . $delay . 's;opacity:' . $opacity . ';font-size:' . $size . 'px;">❄</div>';
      }
    ?>
  </div>

  <div class="app">
    <div id="menu" aria-live="polite"><?= $menuHtml ?></div>

    <main>
      <div class="container">
        <div class="store-layout">
          <!-- SIDEBAR -->
          <aside class="sidebar">
            <!-- Categorias -->
            <div class="sidebar__section">
              <h2 class="sidebar__title">
                <i class="las la-th-large"></i>
                Categorias
              </h2>
              <ul class="category-list">
                <li>
                  <a href="/stores/index.php" class="category-item <?= !$categorySlug ? 'active' : '' ?>">
                    <i class="las la-th"></i>
                    <span class="category-item__name">Todas</span>
                  </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                <li>
                  <a href="/stores/index.php?categoria=<?= h($cat['slug']) ?>" 
                     class="category-item <?= ($categorySlug === $cat['slug']) ? 'active' : '' ?>">
                    <i class="<?= hs($cat['icon_class'] ?? 'las la-server') ?>"></i>
                    <span class="category-item__name"><?= h($cat['name']) ?></span>
                  </a>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Ações -->
            <div class="sidebar__section">
              <h2 class="sidebar__title">
                <i class="las la-bolt"></i>
                Ações Rápidas
              </h2>
              <ul class="action-list">
                <li>
                  <a href="/registrar-dominio" class="action-item">
                    <i class="las la-globe"></i>
                    <span class="action-item__text">Registrar Domínio</span>
                  </a>
                </li>
                <li>
                  <a href="/transferir-dominio" class="action-item">
                    <i class="las la-exchange-alt"></i>
                    <span class="action-item__text">Transferir Domínio</span>
                  </a>
                </li>
                <li>
                  <a href="/carrinho" class="action-item">
                    <i class="las la-shopping-cart"></i>
                    <span class="action-item__text">Visualizar Carrinho</span>
                  </a>
                </li>
                <li>
                  <a href="/abrir-ticket" class="action-item">
                    <i class="las la-headset"></i>
                    <span class="action-item__text">Pedir Ajuda</span>
                  </a>
                </li>
              </ul>
            </div>
          </aside>

          <!-- PRODUTOS -->
          <div class="products-content">
            <div class="products-header">
              <h1 class="products-header__title">
                <?= $currentCategory ? h($currentCategory['name']) : 'Todos os Produtos' ?>
              </h1>
              <p class="products-header__desc">
                <?= $currentCategory ? h($currentCategory['description'] ?? '') : 'Escolha o plano ideal para seu projeto' ?>
              </p>
            </div>

            <?php if (empty($plans)): ?>
              <div class="empty-state">
                <i class="las la-box-open"></i>
                <div class="empty-state__title">Nenhum produto encontrado</div>
                <p>Tente selecionar outra categoria ou verifique novamente mais tarde.</p>
              </div>
            <?php else: ?>
              <div class="products-grid">
                <?php foreach ($plans as $index => $plan): 
                  $colorIndex = $index % count($cardColors);
                  $colors = $cardColors[$colorIndex];
                  
                  // Processar features (JSON ou texto)
                  $features = [];
                  if (!empty($plan['features'])) {
                    $featuresData = json_decode($plan['features'], true);
                    if (is_array($featuresData)) {
                      $features = $featuresData;
                    } else {
                      // Se for string, tentar quebrar por linhas
                      $features = array_filter(array_map('trim', explode("\n", $plan['features'])));
                    }
                  }
                  
                  // Adicionar features padrão se não houver
                  if (empty($features)) {
                    if ($plan['disk_space']) $features[] = $plan['disk_space'] . ' de espaço';
                    if ($plan['bandwidth']) $features[] = $plan['bandwidth'] . ' de banda';
                    if ($plan['domains'] !== null) $features[] = ($plan['domains'] === 0 ? 'Ilimitados' : $plan['domains']) . ' domínios';
                    if ($plan['email_accounts'] !== null) $features[] = ($plan['email_accounts'] === 0 ? 'Ilimitadas' : $plan['email_accounts']) . ' contas de email';
                    if ($plan['databases'] !== null) $features[] = ($plan['databases'] === 0 ? 'Ilimitados' : $plan['databases']) . ' bancos de dados';
                  }
                  
                  // Calcular preço mensal (converter string para float)
                  if (!empty($plan['price_monthly'])) {
                    $price = (float)$plan['price_monthly'];
                  } elseif (!empty($plan['price_annual'])) {
                    $price = (float)$plan['price_annual'] / 12;
                  } else {
                    $price = 0.0;
                  }
                  $currency = $plan['currency'] ?? 'BRL';
                  $icon = $plan['category_icon'] ?? 'las la-server';
                ?>
                <div class="product-card" style="
                  border-color: <?= $colors['border'] ?>;
                  background: linear-gradient(180deg, <?= $colors['bg'] ?>, rgba(255,255,255,.04));
                ">
                  <div class="product-card__icon" style="
                    background: <?= $colors['icon'] ?>;
                    border: 1px solid <?= $colors['iconBorder'] ?>;
                    color: <?= $colors['iconBorder'] ?>;
                  ">
                    <i class="<?= hs($icon) ?>"></i>
                  </div>
                  
                  <h3 class="product-card__title"><?= h($plan['name']) ?></h3>
                  
                  <?php if ($plan['short_description']): ?>
                  <p class="product-card__desc"><?= h($plan['short_description']) ?></p>
                  <?php endif; ?>
                  
                  <?php if (!empty($features)): ?>
                  <ul class="product-card__features">
                    <?php foreach (array_slice($features, 0, 5) as $feature): ?>
                    <li class="product-card__feature">
                      <i class="las la-check-circle"></i>
                      <span><?= hs($feature) ?></span>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                  
                  <div class="product-card__price">
                    <div class="product-card__price-label">A partir de</div>
                    <div class="product-card__price-value">
                      R$ <?= number_format($price, 2, ',', '.') ?>
                      <span class="product-card__price-period">/mês</span>
                    </div>
                  </div>
                  
                  <a href="/carrinho?adicionar=<?= hs($plan['id']) ?>" class="product-card__btn" style="background: <?= $colors['btn'] ?>;">
                    <i class="las la-shopping-cart"></i>
                    Assinar Agora
                  </a>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>

    <div id="footer" style="background: rgba(0,0,0,.22); border-top: 1px solid rgba(255,255,255,.12); color: rgba(232,237,247,.92);">
      <?= $footerHtml ?>
    </div>
  </div>

  <script>
    console.log('[store] script carregado:', window.location.pathname);

    function setupNav() {
      const toggle = document.querySelector('[data-nav-toggle]');
      const list = document.querySelector('[data-nav-list]');
      if (!toggle || !list) {
        console.warn('[store] nav: elementos não encontrados (menu pode não ter carregado?)');
        return;
      }

      toggle.addEventListener('click', () => {
        const open = list.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
      });

      // dropdown no mobile (click)
      document.querySelectorAll('[data-dropdown]').forEach(dd => {
        const btn = dd.querySelector('[data-dropdown-toggle]');
        if (!btn) return;
        btn.addEventListener('click', (ev) => {
          if (window.matchMedia('(max-width: 860px)').matches) {
            ev.preventDefault();
            const isOpen = dd.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', String(isOpen));
          }
        });
      });
    }

    function boot() {
      console.log('[store] boot()');
      setupNav();
    }

    // Garantir boot mesmo se DOMContentLoaded já tiver disparado
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
      boot();
    }
  </script>
</body>
</html>

