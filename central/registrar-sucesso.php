<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

// Garantir UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$email = $_GET['email'] ?? '';

// Carregar menu e footer
$includesDir = realpath(__DIR__ . '/includes') ?: (__DIR__ . '/includes');
$menuFile = $includesDir . '/menu.php';
$footerFile = $includesDir . '/footer.html';
ob_start();
if (is_file($menuFile)) {
    require $menuFile;
} else {
    echo '<!-- menu.php não encontrado -->';
}
$menuHtml = ob_get_clean();
$footerHtml = is_file($footerFile) ? (string)file_get_contents($footerFile) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Realizado - Central GouTec</title>
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
            z-index: 999;
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

        /* Responsivo */
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
            .success-card{
                padding: 32px 20px;
            }
        }
    </style>
</head>
<body>
  <!-- Flocos de neve -->
  <div id="snowflakes"></div>

  <div class="app">
    <div id="menu" aria-live="polite"><?= $menuHtml ?></div>

    <main>
      <div class="container">
        <div class="success-card">
          <div class="success-icon">
            <i class="las la-check"></i>
          </div>
          <h1 class="success-title">Cadastro Realizado com Sucesso!</h1>
          <p class="success-message">
            Parabéns! Sua conta foi criada com sucesso na GouTec.
          </p>
          <?php if ($email): ?>
          <div class="success-email">
            <i class="las la-envelope"></i>
            <?= h($email) ?>
          </div>
          <?php endif; ?>
          <p class="success-message">
            Para finalizar, favor validar com o link enviado para o seu e-mail.
            <br><br>
            <strong>Verifique sua caixa de entrada e a pasta de spam.</strong>
          </p>
          <div class="success-actions">
            <a href="/entrar" class="success-btn success-btn--primary">
              <i class="las la-sign-in-alt"></i>
              Fazer Login
            </a>
            <a href="/central/index.php" class="success-btn">
              <i class="las la-home"></i>
              Voltar ao Início
            </a>
          </div>
        </div>
      </div>
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

    function createSnowflake() {
      const container = document.getElementById('snowflakes');
      if (!container) return;
      const snowflake = document.createElement('div');
      snowflake.className = 'snowflake';
      snowflake.innerHTML = '❄';
      snowflake.style.left = Math.random() * 100 + '%';
      snowflake.style.animationDuration = (Math.random() * 3 + 2) + 's';
      snowflake.style.opacity = Math.random() * 0.5 + 0.5;
      snowflake.style.fontSize = (Math.random() * 10 + 10) + 'px';
      container.appendChild(snowflake);
      setTimeout(() => snowflake.remove(), 5000);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        setupNav();
        setInterval(createSnowflake, 300);
        for (let i = 0; i < 12; i++) createSnowflake();
      });
    } else {
      setupNav();
      setInterval(createSnowflake, 300);
      for (let i = 0; i < 12; i++) createSnowflake();
    }
  </script>
</body>
</html>

