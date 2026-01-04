<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

// UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Menu/footer (menu dinâmico via PHP + footer estático)
$includesDir = realpath(__DIR__ . '/includes') ?: (__DIR__ . '/includes');
$menuFile = $includesDir . '/menu.php';
$footerFile = $includesDir . '/footer.html';
$menuHtml = '';
ob_start();
if (is_file($menuFile)) {
    require $menuFile;
} else {
    echo '<!-- menu.php não encontrado -->';
}
$menuHtml = ob_get_clean();
$footerHtml = is_file($footerFile) ? (string)file_get_contents($footerFile) : '<!-- footer.html não encontrado -->';

// Verificar se é para exibir um artigo específico
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$singlePost = null;
$posts = [];

try {
    if (function_exists('db')) {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        if ($articleId > 0) {
            // Buscar artigo específico
            $stmt = db()->prepare("SELECT * FROM blog_posts WHERE id=? AND is_enabled=1");
            $stmt->execute([$articleId]);
            $singlePost = $stmt->fetch();
        } else {
            // Buscar todos os artigos para listagem
            $posts = db()->query("SELECT * FROM blog_posts WHERE is_enabled=1 ORDER BY is_featured DESC, sort_order ASC, id ASC")->fetchAll();
        }
    }
} catch (Throwable $e) {
    $posts = [];
    $singlePost = null;
}

function formatDate(string $date): string {
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
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

function truncateContent(string $content, int $length = 150): string {
    $content = strip_tags($content);
    if (mb_strlen($content) <= $length) {
        return $content;
    }
    return mb_substr($content, 0, $length) . '...';
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
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - GouTec</title>
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
        }
        a{ color:inherit; text-decoration:none; }
        .container{ width:min(1200px, 92vw); margin:0 auto; }
        .app{ min-height:100vh; display:flex; flex-direction:column; }
        #menu, #footer{ width:100%; }

        /* MENU (mesmo estilo do index.php) */
        .topbar{
            position:sticky; top:0; z-index:50;
            backdrop-filter: blur(14px);
            background: linear-gradient(180deg, rgba(7,10,18,.85), rgba(7,10,18,.55));
            border-bottom: 1px solid var(--border);
        }
        .topbar__inner{ display:flex; align-items:center; gap:16px; padding: 14px 0; }
        .brand{ display:flex; align-items:center; gap:12px; }
        .brand__logo{ height: 28px; width:auto; filter: drop-shadow(0 8px 18px rgba(0,0,0,.35)); }
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
        .nav__item--user{ margin-left: 4px; }
        .nav__link--user{ gap: 10px; }
        .nav__user-avatar{
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.16);
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }
        .nav__user-initial{
            font-weight: 950;
            letter-spacing: .02em;
            color: rgba(232,237,247,.95);
        }
        .nav__user-name{
            color: rgba(232,237,247,.92);
            font-weight: 800;
            white-space: nowrap;
        }

        /* BLOG SECTION */
        .blog-section{ padding: 44px 0 60px; }
        .blog-header{
            text-align:center;
            margin-bottom: 40px;
        }
        .blog-header__kicker{
            display:inline-flex; align-items:center; gap:8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(61,214,245,.14);
            border: 1px solid rgba(61,214,245,.22);
            color: var(--primary2);
            font-weight: 800;
            margin-bottom: 16px;
        }
        .blog-header__title{
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 950;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
        }
        .blog-header__desc{
            color: rgba(232,237,247,.72);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .blog-grid{
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }
        .blog-card{
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 16px 60px rgba(0,0,0,.25);
            transition: .18s ease;
        }
        .blog-card:hover{
            transform: translateY(-4px);
            border-color: rgba(61,214,245,.25);
            box-shadow: 0 20px 80px rgba(0,0,0,.35);
        }
        .blog-card__image{
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        .blog-card__content{
            padding: 20px;
        }
        .blog-card__meta{
            display:flex;
            align-items:center;
            gap: 16px;
            margin-bottom: 12px;
            font-size: .9rem;
            color: rgba(232,237,247,.68);
        }
        .blog-card__meta-item{
            display:flex;
            align-items:center;
            gap: 6px;
        }
        .blog-card__meta-item i{
            color: var(--primary2);
        }
        .blog-card__title{
            font-size: 1.25rem;
            font-weight: 950;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        .blog-card__title a{
            color: var(--text);
            transition: color .18s ease;
        }
        .blog-card__title a:hover{
            color: var(--primary2);
        }
        .blog-card__excerpt{
            color: rgba(232,237,247,.72);
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .blog-card__link{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            color: var(--primary2);
            font-weight: 900;
            transition: gap .18s ease;
        }
        .blog-card__link:hover{
            gap: 12px;
        }
        .blog-card__badge{
            display:inline-flex;
            align-items:center;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(255,211,78,.14);
            border: 1px solid rgba(255,211,78,.22);
            color: var(--warn);
            font-size: .75rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .blog-empty{
            text-align:center;
            padding: 60px 20px;
            color: rgba(232,237,247,.68);
        }
        .blog-empty__icon{
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: .5;
        }

        /* Artigo individual */
        .blog-article{
            max-width: 800px;
            margin: 0 auto;
        }
        .blog-article__header{
            margin-bottom: 30px;
        }
        .blog-article__image{
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 20px;
            margin-bottom: 24px;
        }
        .blog-article__meta{
            display:flex;
            align-items:center;
            gap: 20px;
            margin-bottom: 16px;
            font-size: .95rem;
            color: rgba(232,237,247,.68);
        }
        .blog-article__meta-item{
            display:flex;
            align-items:center;
            gap: 8px;
        }
        .blog-article__meta-item i{
            color: var(--primary2);
        }
        .blog-article__title{
            font-size: clamp(2rem, 4vw, 2.5rem);
            font-weight: 950;
            line-height: 1.2;
            margin-bottom: 20px;
        }
        .blog-article__content{
            color: rgba(232,237,247,.85);
            line-height: 1.8;
            font-size: 1.05rem;
        }
        .blog-article__content h1,
        .blog-article__content h2,
        .blog-article__content h3,
        .blog-article__content h4{
            color: var(--text);
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: 900;
        }
        .blog-article__content h3{
            font-size: 1.5rem;
        }
        .blog-article__content ul,
        .blog-article__content ol{
            margin: 15px 0;
            padding-left: 25px;
        }
        .blog-article__content li{
            margin: 8px 0;
        }
        .blog-article__content p{
            margin: 15px 0;
        }
        .blog-article__content strong{
            color: var(--text);
            font-weight: 800;
        }
        .blog-article__back{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            color: var(--primary2);
            font-weight: 900;
            margin-bottom: 30px;
            transition: gap .18s ease;
        }
        .blog-article__back:hover{
            gap: 12px;
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
        }
        /* FOOTER */
        #footer{ margin-top: auto; }
        .footer{ border-top: 1px solid var(--border); background: rgba(0,0,0,.22); padding: 22px 0; }
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
        .footer__center{ display:flex; flex-direction:column; gap:10px; }
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
        .footer__right{ display:flex; gap:8px; }
        .footer__social{
            width: 40px; height: 40px;
            border-radius: 14px;
            display:inline-flex; align-items:center; justify-content:center;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            color: rgba(232,237,247,.92);
            transition: .18s ease;
        }
        .footer__social:hover{
            transform: translateY(-2px);
            background: rgba(255,255,255,.10);
            border-color: rgba(61,214,245,.28);
        }
        .footer__social i{ font-size: 1.25rem; }

        @media (max-width: 768px){
            .blog-grid{
                grid-template-columns: 1fr;
            }
            .footer__inner{ flex-direction:column; align-items:flex-start; }
            .footer__center{ width:100%; }
            .footer__right{ width:100%; justify-content:flex-start; }
        }
    </style>
</head>
<body>
  <div class="app">
    <div id="menu" aria-live="polite"><?= $menuHtml ?></div>

    <main>
      <section class="blog-section">
        <div class="container">
          <?php if ($singlePost): ?>
            <!-- Artigo Individual -->
            <div class="blog-article">
              <a href="https://central.goutec.com.br/blog.php" class="blog-article__back">
                <i class="las la-arrow-left"></i>
                Voltar para lista de artigos
              </a>
              
              <?php if (!empty($singlePost['image'])): ?>
                <img src="<?= h($singlePost['image']) ?>" alt="<?= h($singlePost['title']) ?>" class="blog-article__image" onerror="this.style.display='none'">
              <?php endif; ?>
              
              <div class="blog-article__header">
                <div class="blog-article__meta">
                  <div class="blog-article__meta-item">
                    <i class="las la-calendar"></i>
                    <span><?= formatDate($singlePost['published_date']) ?></span>
                  </div>
                  <div class="blog-article__meta-item">
                    <i class="las la-user"></i>
                    <span><?= h($singlePost['author']) ?></span>
                  </div>
                </div>
                <h1 class="blog-article__title"><?= h($singlePost['title']) ?></h1>
              </div>
              
              <div class="blog-article__content">
                <?= $singlePost['content'] ?: '<p>Conteúdo não disponível.</p>' ?>
              </div>
            </div>
          <?php else: ?>
            <!-- Lista de Artigos -->
            <div class="blog-header">
              <div class="blog-header__kicker">
                <i class="las la-newspaper"></i>
                <span>Blog GouTec</span>
              </div>
              <h1 class="blog-header__title">Artigos e Notícias</h1>
              <p class="blog-header__desc">
                Fique por dentro das últimas novidades sobre hospedagem, servidores, painéis de controle e muito mais.
              </p>
            </div>

            <?php if (empty($posts)): ?>
              <div class="blog-empty">
                <div class="blog-empty__icon">
                  <i class="las la-newspaper"></i>
                </div>
                <p>Nenhum artigo disponível no momento.</p>
              </div>
            <?php else: ?>
              <div class="blog-grid">
                <?php foreach ($posts as $post): ?>
                  <article class="blog-card">
                    <?php if (!empty($post['image'])): ?>
                      <img src="<?= h($post['image']) ?>" alt="<?= h($post['title']) ?>" class="blog-card__image" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="blog-card__content">
                      <?php if ((int)$post['is_featured'] === 1): ?>
                        <div class="blog-card__badge">
                          <i class="las la-star"></i>
                          Destaque
                        </div>
                      <?php endif; ?>
                      <div class="blog-card__meta">
                        <div class="blog-card__meta-item">
                          <i class="las la-calendar"></i>
                          <span><?= formatDate($post['published_date']) ?></span>
                        </div>
                        <div class="blog-card__meta-item">
                          <i class="las la-user"></i>
                          <span><?= h($post['author']) ?></span>
                        </div>
                      </div>
                      <h2 class="blog-card__title">
                        <a href="<?= h(fixBlogUrl($post['url'])) ?>"><?= h($post['title']) ?></a>
                      </h2>
                      <?php if (!empty($post['content'])): ?>
                        <p class="blog-card__excerpt"><?= h(truncateContent($post['content'])) ?></p>
                      <?php endif; ?>
                      <a href="<?= h(fixBlogUrl($post['url'])) ?>" class="blog-card__link">
                        Ler mais
                        <i class="las la-arrow-right"></i>
                      </a>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <div id="footer"><?= $footerHtml ?></div>
  </div>

  <script>
    function setupNav() {
      const toggle = document.querySelector('[data-nav-toggle]');
      const list = document.querySelector('[data-nav-list]');
      if (!toggle || !list) return;

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

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setupNav);
    } else {
      setupNav();
    }
  </script>
</body>
</html>

