<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

// Garantir UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Se já estiver logado, redirecionar
if (is_client_logged_in()) {
    header('Location: /client-area');
    exit;
}

$error = null;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $stmt = db()->prepare("SELECT id, email, password_hash, first_name, last_name, status FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $client = $stmt->fetch();
            
            if (!$client || $client['status'] !== 'active') {
                $error = 'Email ou senha inválidos.';
            } elseif (empty($client['password_hash']) || !password_verify($password, $client['password_hash'])) {
                $error = 'Email ou senha inválidos.';
            } else {
                // Login bem-sucedido
                session_regenerate_id(true);
                $_SESSION['client_id'] = (int)$client['id'];
                $_SESSION['client_email'] = (string)$client['email'];
                $_SESSION['client_name'] = (string)$client['first_name'] . ' ' . (string)$client['last_name'];
                
                // Se "lembrar" estiver marcado, criar cookie (opcional, por segurança não vamos fazer isso por enquanto)
                // Mas podemos estender o tempo da sessão
                if ($remember) {
                    ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 dias
                }
                
                header('Location: /client-area');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Erro ao processar login. Tente novamente.';
        }
    }
}

// Carregar menu e footer (menu dinâmico via PHP + footer estático)
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
    <title>Entrar - Central GouTec</title>
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

        /* LOGIN PAGE */
        main{ 
            flex:1; 
            padding: 60px 0; 
            display:flex; 
            align-items:center; 
            justify-content:center; 
            min-height: calc(100vh - 200px);
        }
        .login-card{
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 16px 60px rgba(0,0,0,.35);
        }
        .login-title{
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 8px;
            text-align:center;
        }
        .login-desc{
            color: rgba(232,237,247,.72);
            font-size: 1.05rem;
            text-align:center;
            margin-bottom: 32px;
        }
        .form-group{
            margin-bottom: 20px;
        }
        .form-label{
            display:block;
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 8px;
            color: rgba(232,237,247,.92);
        }
        .form-input{
            width:100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 1rem;
            outline:none;
            transition: .2s ease;
        }
        .form-input:focus{
            border-color: rgba(61,214,245,.45);
            box-shadow: 0 0 0 4px rgba(61,214,245,.12);
            background: rgba(255,255,255,.08);
        }
        .form-input::placeholder{
            color: rgba(232,237,247,.55);
        }
        .password-wrapper{
            position:relative;
        }
        .password-toggle{
            position:absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background:transparent;
            border:none;
            color: rgba(232,237,247,.7);
            cursor:pointer;
            padding: 4px;
            display:flex;
            align-items:center;
            justify-content:center;
            transition: .18s ease;
        }
        .password-toggle:hover{
            color: var(--primary2);
        }
        .password-toggle i{
            font-size: 1.2rem;
        }
        .form-error{
            background: rgba(255,107,107,.15);
            border: 1px solid rgba(255,107,107,.30);
            color: rgba(255,200,200,.95);
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            display:flex;
            align-items:center;
            gap: 12px;
        }
        .form-error i{
            font-size: 1.3rem;
        }
        .form-check{
            display:flex;
            align-items:center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .form-check-input{
            width: 20px;
            height: 20px;
            cursor:pointer;
        }
        .form-check-label{
            color: rgba(232,237,247,.85);
            font-size: .95rem;
            cursor:pointer;
        }
        .form-submit{
            width:100%;
            padding: 16px 24px;
            border:none;
            border-radius: 14px;
            background: linear-gradient(90deg, var(--primary2), #5de5ff);
            color: #061017;
            font-weight: 900;
            font-size: 1.1rem;
            cursor:pointer;
            transition: .18s ease;
            display:flex;
            align-items:center;
            justify-content:center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .form-submit:hover{
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(61,214,245,.3);
        }
        .form-submit i{
            font-size: 1.3rem;
        }
        .login-links{
            text-align:center;
            display:flex;
            flex-direction:column;
            gap: 12px;
        }
        .login-link{
            color: var(--primary2);
            text-decoration:none;
            font-weight: 800;
            transition: .18s ease;
        }
        .login-link:hover{
            text-decoration:underline;
        }
        .login-divider{
            text-align:center;
            color: rgba(232,237,247,.55);
            font-size: .9rem;
            margin: 20px 0;
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
            .nav__user-name{
                display:none;
            }
            .nav__user-avatar{
                width: 32px;
                height: 32px;
            }
            .login-card{
                padding: 32px 24px;
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
        <div class="login-card">
          <h1 class="login-title">Entrar</h1>
          <p class="login-desc">Entre na sua conta para continuar.</p>

          <?php if ($error): ?>
          <div class="form-error">
            <i class="las la-exclamation-circle"></i>
            <span><?= h($error) ?></span>
          </div>
          <?php endif; ?>

          <form method="POST" action="" id="loginForm">
            <div class="form-group">
              <label class="form-label" for="email">Endereço de email</label>
              <input type="email" id="email" name="email" class="form-input" 
                     placeholder="seu@email.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Senha de acesso</label>
              <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="••••••••" required>
                <button type="button" class="password-toggle" id="passwordToggle" aria-label="Mostrar senha">
                  <i class="las la-eye"></i>
                </button>
              </div>
            </div>

            <div class="form-check">
              <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
              <label for="remember" class="form-check-label">Lembrar-me</label>
            </div>

            <button type="submit" class="form-submit">
              <i class="las la-sign-in-alt"></i>
              Entrar
            </button>
          </form>

          <div class="login-links">
            <a href="/redefinir-senha" class="login-link">
              <i class="las la-key"></i> Esqueceu senha?
            </a>
            <div class="login-divider">ou</div>
            <a href="/registrar" class="login-link">
              <i class="las la-user-plus"></i> Não registrado? Criar conta
            </a>
          </div>
        </div>
      </div>
    </main>

    <div id="footer"><?= $footerHtml ?></div>
  </div>

  <script>
    // Toggle senha
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordInput = document.getElementById('password');
    
    if (passwordToggle && passwordInput) {
      passwordToggle.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        passwordToggle.querySelector('i').className = type === 'password' ? 'las la-eye' : 'las la-eye-slash';
      });
    }

    // Setup Nav
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

    // Neve
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

    // Inicializar
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
