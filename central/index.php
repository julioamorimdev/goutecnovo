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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central do Cliente - GouTec</title>
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
        .snowflake {
            position: absolute;
            top: -10px;
            color: #fff;
            font-size: 1em;
            font-family: Arial;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.8);
            animation: fall linear infinite;
            pointer-events: none;
            z-index: 1;
        }
        @keyframes fall {
            to { transform: translateY(100vh) rotate(360deg); }
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

        .topbar__search{
            flex:1; min-width:220px; position:relative;
            display:flex; align-items:center;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 12px;
            padding: 10px 12px;
            transition: .2s ease;
        }
        .topbar__search:focus-within{
            border-color: rgba(61,214,245,.45);
            box-shadow: 0 0 0 4px rgba(61,214,245,.12);
            background: rgba(255,255,255,.08);
        }
        .topbar__searchIcon{ position:absolute; left:12px; color: rgba(232,237,247,.7); }
        .topbar__searchInput{
            width:100%;
            padding-left: 30px;
            border:none; outline:none;
            background:transparent;
            color: var(--text);
            font-size: .95rem;
        }
        .topbar__searchInput::placeholder{ color: rgba(232,237,247,.55); }

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

        /* HERO / CARDS (igual ao index.html estável) */
        .hero{ padding: 44px 0 20px; }
        .hero__grid{ display:grid; grid-template-columns: 1.15fr .85fr; gap: 18px; align-items:stretch; }
        .hero__card{
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 16px 60px rgba(0,0,0,.35);
        }
        .hero__kicker{
            color: rgba(232,237,247,.78);
            font-size: .95rem;
            display:flex; gap:10px; align-items:center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .pill{
            display:inline-flex; align-items:center; gap:8px;
            padding: 8px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            color: rgba(232,237,247,.86);
            font-weight: 800;
        }
        .hero__title{
            font-size: clamp(1.8rem, 3.4vw, 2.4rem);
            font-weight: 950;
            letter-spacing: -0.02em;
            margin: 10px 0 10px;
        }
        .hero__subtitle{ color: rgba(232,237,247,.72); font-size: 1.05rem; line-height: 1.6; }
        .domain{
            margin-top: 18px;
            display:flex; gap: 10px; flex-wrap: wrap;
        }
        .domain__inputWrap{
            flex: 1;
            min-width: 260px;
            display:flex;
            align-items:center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
        }
        .domain__inputIcon{ color: rgba(232,237,247,.7); }
        .domain__input{
            width:100%;
            border:none; outline:none;
            background:transparent;
            color: rgba(232,237,247,.92);
            font-size: 1rem;
        }
        .btn{
            border:none;
            cursor:pointer;
            border-radius: 14px;
            padding: 12px 14px;
            font-weight: 900;
            display:inline-flex; align-items:center; gap: 10px;
            transition: .18s ease;
            text-decoration:none;
            white-space:nowrap;
        }
        .btn--primary{
            background: linear-gradient(90deg, var(--primary2), #5de5ff);
            color: #061017;
        }
        .btn--ghost{
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            color: rgba(232,237,247,.92);
        }
        .btn:hover{ transform: translateY(-1px); }
        .tlds{ margin-top: 16px; display:flex; gap: 10px; flex-wrap: wrap; }
        .tld{
            display:flex; align-items:center; gap: 10px;
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
        }
        .tld__icon{
            width: 34px; height: 34px;
            border-radius: 12px;
            display:flex; align-items:center; justify-content:center;
            background: rgba(61,214,245,.12);
            border: 1px solid rgba(61,214,245,.22);
        }
        .tld__text{ font-weight: 900; }
        .side__stats{ display:flex; flex-direction:column; gap: 12px; }
        .stat{
            display:flex; gap: 12px; align-items:flex-start;
            padding: 12px;
            border-radius: 16px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.10);
        }
        .stat i{ font-size: 1.4rem; color: rgba(61,214,245,.95); margin-top:2px; }
        .stat__title{ font-weight: 950; margin-bottom: 2px; }
        .stat__desc{ color: rgba(232,237,247,.68); font-size: .92rem; line-height: 1.45; }

        .section{ padding: 24px 0; }
        .section__head{ display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; margin-bottom: 14px; }
        .section__title{ font-size: 1.25rem; font-weight: 950; }
        .section__desc{ color: rgba(232,237,247,.68); margin-top: 4px; }
        .grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
        .card{
            grid-column: span 4;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            box-shadow: 0 16px 60px rgba(0,0,0,.25);
            transition: .18s ease;
        }
        .card:hover{ transform: translateY(-3px); border-color: rgba(61,214,245,.25); }
        .card__icon{
            width: 46px; height: 46px;
            border-radius: 16px;
            display:flex; align-items:center; justify-content:center;
            background: rgba(109,94,252,.14);
            border: 1px solid rgba(109,94,252,.22);
            margin-bottom: 10px;
        }
        .card__icon i{ font-size: 1.5rem; }
        .card__title{ font-weight: 950; font-size: 1.05rem; margin-bottom: 4px; }
        .card__text{ color: rgba(232,237,247,.68); line-height: 1.55; }
        .card__cta{ margin-top: 10px; font-weight: 900; color: rgba(61,214,245,.95); display:inline-flex; gap: 8px; align-items:center; }

        @media (max-width: 980px){
            .hero__grid{ grid-template-columns: 1fr; }
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
            .card{ grid-column: span 12; }
        }
    </style>
</head>
<body>
  <!-- Flocos de neve -->
  <div id="snowflakes"></div>

  <div class="app">
    <div id="menu" aria-live="polite"><?= $menuHtml ?></div>

    <main>
      <section class="hero">
        <div class="container hero__grid">
          <div class="hero__card">
            <div class="hero__kicker">
              <span class="pill"><i class="las la-moon"></i> Central GouTec • Tema Dark</span>
              <span class="pill" style="background:rgba(43,213,118,.14);border-color:rgba(43,213,118,.22);"><i class="las la-bolt"></i> Rápido • Seguro</span>
            </div>
            <h1 class="hero__title">Registre ou transfira seu domínio em minutos.</h1>
            <p class="hero__subtitle">Pesquise disponibilidade, registre agora ou transfira para a GouTec com facilidade.</p>

            <div class="domain" role="search">
              <div class="domain__inputWrap">
                <i class="las la-globe domain__inputIcon" aria-hidden="true"></i>
                <input id="domainInput" class="domain__input" type="text" placeholder="ex: minhaempresa.com.br" autocomplete="off" inputmode="url">
              </div>
              <button class="btn btn--primary" type="button" id="btnCheck">
                <i class="las la-search" aria-hidden="true"></i> Procurar
              </button>
              <button class="btn btn--ghost" type="button" id="btnTransfer">
                <i class="las la-exchange-alt" aria-hidden="true"></i> Transferir
              </button>
            </div>

            <div class="tlds" aria-label="Principais extensões">
              <div class="tld"><div class="tld__icon"><i class="las la-flag"></i></div><div class="tld__text">.com.br</div></div>
              <div class="tld"><div class="tld__icon"><i class="las la-globe"></i></div><div class="tld__text">.com</div></div>
              <div class="tld"><div class="tld__icon"><i class="las la-sitemap"></i></div><div class="tld__text">.net</div></div>
              <div class="tld"><div class="tld__icon"><i class="las la-leaf"></i></div><div class="tld__text">.org</div></div>
              <div class="tld"><div class="tld__icon"><i class="las la-briefcase"></i></div><div class="tld__text">.biz</div></div>
            </div>
          </div>

          <div class="hero__card">
            <div class="side__stats">
              <div class="stat">
                <i class="las la-headset" aria-hidden="true"></i>
                <div>
                  <div class="stat__title">Suporte completo</div>
                  <div class="stat__desc">Tickets, base de conhecimento e status de rede.</div>
                </div>
              </div>
              <div class="stat">
                <i class="las la-shopping-cart" aria-hidden="true"></i>
                <div>
                  <div class="stat__title">Carrinho e pedidos</div>
                  <div class="stat__desc">Fluxo simples para comprar e contratar serviços.</div>
                </div>
              </div>
              <div class="stat">
                <i class="las la-user-shield" aria-hidden="true"></i>
                <div>
                  <div class="stat__title">Conta centralizada</div>
                  <div class="stat__desc">Domínios, serviços, faturas e tickets em um lugar.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section__head">
            <div>
              <div class="section__title">Produtos e Serviços</div>
              <div class="section__desc">Acesse os produtos com atalhos e navegue pela vitrine completa.</div>
            </div>
          </div>
          <div class="grid">
            <a class="card" href="/stores/index.php?categoria=hospedagens">
              <div class="card__icon"><i class="las la-box"></i></div>
              <div class="card__title">Hospedagem</div>
              <div class="card__text">Planos otimizados para performance, segurança e estabilidade.</div>
              <div class="card__cta">Ver produtos <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/stores/index.php?categoria=servidores-vps">
              <div class="card__icon"><i class="las la-cloud"></i></div>
              <div class="card__title">VPS Cloud</div>
              <div class="card__text">Recursos dedicados, escalabilidade e controle total do servidor.</div>
              <div class="card__cta">Ver produtos <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/stores/index.php">
              <div class="card__icon"><i class="las la-globe"></i></div>
              <div class="card__title">Domínios & SSL</div>
              <div class="card__text">Registre, transfira e proteja com certificados SSL.</div>
              <div class="card__cta">Ver produtos <i class="las la-arrow-right"></i></div>
            </a>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section__head">
            <div>
              <div class="section__title">Como podemos ajudar hoje?</div>
              <div class="section__desc">Atalhos rápidos para as áreas mais usadas (estilo WHMCS).</div>
            </div>
          </div>
          <div class="grid">
            <a class="card" href="/anuncios">
              <div class="card__icon"><i class="las la-bullhorn"></i></div>
              <div class="card__title">Anúncios</div>
              <div class="card__text">Novidades e comunicados importantes.</div>
              <div class="card__cta">Abrir <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/status">
              <div class="card__icon"><i class="las la-network-wired"></i></div>
              <div class="card__title">Status de Rede</div>
              <div class="card__text">Incidentes, manutenção e disponibilidade.</div>
              <div class="card__cta">Abrir <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/base-conhecimento">
              <div class="card__icon"><i class="las la-book"></i></div>
              <div class="card__title">Base de Conhecimento</div>
              <div class="card__text">Tutoriais, guias e respostas rápidas.</div>
              <div class="card__cta">Abrir <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/downloads">
              <div class="card__icon"><i class="las la-download"></i></div>
              <div class="card__title">Downloads</div>
              <div class="card__text">Arquivos, ferramentas e materiais.</div>
              <div class="card__cta">Abrir <i class="las la-arrow-right"></i></div>
            </a>
            <a class="card" href="/abrir-ticket">
              <div class="card__icon"><i class="las la-headset"></i></div>
              <div class="card__title">Abrir Ticket</div>
              <div class="card__text">Fale com nossa equipe de suporte.</div>
              <div class="card__cta">Abrir <i class="las la-arrow-right"></i></div>
            </a>
          </div>
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

    function setupDomainButtons() {
      const input = document.getElementById('domainInput');
      const btnCheck = document.getElementById('btnCheck');
      const btnTransfer = document.getElementById('btnTransfer');
      function getDomain() { return (input?.value || '').trim(); }
      function toast(msg) {
        const t = document.createElement('div');
        t.style.position = 'fixed';
        t.style.left = '50%';
        t.style.bottom = '22px';
        t.style.transform = 'translateX(-50%)';
        t.style.background = 'rgba(13,18,32,.92)';
        t.style.border = '1px solid rgba(255,255,255,.14)';
        t.style.backdropFilter = 'blur(12px)';
        t.style.color = 'rgba(232,237,247,.92)';
        t.style.padding = '10px 12px';
        t.style.borderRadius = '14px';
        t.style.boxShadow = '0 16px 60px rgba(0,0,0,.55)';
        t.style.zIndex = 9999;
        t.innerHTML = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 2200);
      }
      btnCheck?.addEventListener('click', () => {
        const d = getDomain();
        toast(d ? ('Verificando disponibilidade de <b>' + d + '</b>... (demo)') : 'Digite um domínio para procurar.');
      });
      btnTransfer?.addEventListener('click', () => {
        const d = getDomain();
        toast(d ? ('Iniciando transferência de <b>' + d + '</b>... (demo)') : 'Digite um domínio para transferir.');
      });
    }

    // Criar flocos de neve
    function createSnowflake() {
      const snowflake = document.createElement('div');
      snowflake.className = 'snowflake';
      snowflake.innerHTML = '❄';
      snowflake.style.left = Math.random() * 100 + '%';
      snowflake.style.animationDuration = (Math.random() * 3 + 2) + 's';
      snowflake.style.opacity = Math.random() * 0.5 + 0.5;
      snowflake.style.fontSize = (Math.random() * 10 + 10) + 'px';
      document.getElementById('snowflakes').appendChild(snowflake);
      setTimeout(() => snowflake.remove(), 5000);
    }

    setInterval(createSnowflake, 300);

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        setupNav();
        setupDomainButtons();
      });
    } else {
      setupNav();
      setupDomainButtons();
    }
  </script>
</body>
</html>


