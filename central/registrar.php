<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

// Garantir UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Helper para escape seguro
function hs(mixed $v): string {
    if ($v === null) return '';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_scalar($v)) return h((string)$v);
    return h((string)json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Estados brasileiros
$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

// Países (lista simplificada)
$paises = [
    'Brasil' => 'Brasil',
    'Argentina' => 'Argentina',
    'Chile' => 'Chile',
    'Colômbia' => 'Colômbia',
    'Paraguai' => 'Paraguai',
    'Uruguai' => 'Uruguai',
    'Estados Unidos' => 'Estados Unidos',
    'Portugal' => 'Portugal',
    'Espanha' => 'Espanha',
    'Outro' => 'Outro'
];

// Códigos de país para telefone
$countryCodes = [
    '+55' => '🇧🇷 Brasil (+55)',
    '+1' => '🇺🇸 EUA/Canadá (+1)',
    '+351' => '🇵🇹 Portugal (+351)',
    '+34' => '🇪🇸 Espanha (+34)',
    '+54' => '🇦🇷 Argentina (+54)',
    '+56' => '🇨🇱 Chile (+56)',
    '+57' => '🇨🇴 Colômbia (+57)',
    '+595' => '🇵🇾 Paraguai (+595)',
    '+598' => '🇺🇾 Uruguai (+598)',
];

$error = null;
$success = false;
$formData = [];

// Processar formulário
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Coletar dados do formulário
    $formData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone_code' => trim($_POST['phone_code'] ?? '+55'),
        'phone' => trim($_POST['phone'] ?? ''),
        'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? ''),
        'company_name' => trim($_POST['company_name'] ?? ''),
        'cnpj' => preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'address_number' => trim($_POST['address_number'] ?? ''),
        'address2' => trim($_POST['address2'] ?? ''),
        'neighborhood' => trim($_POST['neighborhood'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => preg_replace('/[^0-9]/', '', $_POST['postal_code'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'country' => trim($_POST['country'] ?? 'Brasil'),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'newsletter' => isset($_POST['newsletter']) && $_POST['newsletter'] === 'yes',
        'terms' => isset($_POST['terms']) && $_POST['terms'] === '1',
    ];
    
    // Validações
    if (empty($formData['first_name'])) {
        $error = 'O nome é obrigatório.';
    } elseif (empty($formData['last_name'])) {
        $error = 'O sobrenome é obrigatório.';
    } elseif (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (empty($formData['phone'])) {
        $error = 'Telefone é obrigatório.';
    } elseif (empty($formData['cpf']) || strlen($formData['cpf']) !== 11) {
        $error = 'CPF inválido. Deve conter 11 dígitos.';
    } elseif (empty($formData['address'])) {
        $error = 'Endereço é obrigatório.';
    } elseif (empty($formData['address_number'])) {
        $error = 'Número do endereço é obrigatório.';
    } elseif (empty($formData['neighborhood'])) {
        $error = 'Bairro é obrigatório.';
    } elseif (empty($formData['city'])) {
        $error = 'Cidade é obrigatória.';
    } elseif (empty($formData['postal_code']) || strlen($formData['postal_code']) !== 8) {
        $error = 'CEP inválido.';
    } elseif (empty($formData['state'])) {
        $error = 'Estado é obrigatório.';
    } elseif (empty($formData['country'])) {
        $error = 'País é obrigatório.';
    } elseif (empty($formData['password'])) {
        $error = 'Senha é obrigatória.';
    } elseif ($formData['password'] !== $formData['password_confirm']) {
        $error = 'As senhas não coincidem.';
    } elseif (strlen($formData['password']) < 8) {
        $error = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $formData['password'])) {
        $error = 'A senha deve conter pelo menos uma letra maiúscula.';
    } elseif (!preg_match('/[a-z]/', $formData['password'])) {
        $error = 'A senha deve conter pelo menos uma letra minúscula.';
    } elseif (!preg_match('/[0-9]/', $formData['password'])) {
        $error = 'A senha deve conter pelo menos um número.';
    } elseif (!$formData['terms']) {
        $error = 'Você deve concordar com os termos de serviço.';
    }
    
    // Validar CNPJ se fornecido
    if (!$error && !empty($formData['cnpj']) && strlen($formData['cnpj']) !== 14) {
        $error = 'CNPJ inválido.';
    }
    
    // Verificar duplicidade de email e CPF
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Verificar email
            $stmt = db()->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado.';
            }
            
            // Verificar CPF
            if (!$error) {
                $stmt = db()->prepare("SELECT id FROM clients WHERE cpf = ?");
                $stmt->execute([$formData['cpf']]);
                if ($stmt->fetch()) {
                    $error = 'Este CPF já está cadastrado.';
                }
            }
            
            // Verificar CNPJ se fornecido
            if (!$error && !empty($formData['cnpj'])) {
                $stmt = db()->prepare("SELECT id FROM clients WHERE cnpj = ?");
                $stmt->execute([$formData['cnpj']]);
                if ($stmt->fetch()) {
                    $error = 'Este CNPJ já está cadastrado.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Erro ao verificar dados. Tente novamente.';
        }
    }
    
    // Inserir no banco se não houver erro
    if (!$error) {
        try {
            // Tentar adicionar colunas se não existirem (ignora erro se já existirem)
            try {
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS cpf VARCHAR(14) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS cnpj VARCHAR(18) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS address_number VARCHAR(20) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0");
            } catch (Throwable $e) {
                // Ignorar erros de colunas já existentes
            }
            
            // Gerar token de verificação
            $verificationToken = bin2hex(random_bytes(32));
            
            // Formatar CPF e CNPJ
            $cpfFormatted = strlen($formData['cpf']) === 11 
                ? substr($formData['cpf'], 0, 3) . '.' . substr($formData['cpf'], 3, 3) . '.' . substr($formData['cpf'], 6, 3) . '-' . substr($formData['cpf'], 9, 2)
                : $formData['cpf'];
            
            $cnpjFormatted = null;
            if (!empty($formData['cnpj']) && strlen($formData['cnpj']) === 14) {
                $cnpjFormatted = substr($formData['cnpj'], 0, 2) . '.' . substr($formData['cnpj'], 2, 3) . '.' . substr($formData['cnpj'], 5, 3) . '/' . substr($formData['cnpj'], 8, 4) . '-' . substr($formData['cnpj'], 12, 2);
            }
            
            // Formatar CEP
            $cepFormatted = strlen($formData['postal_code']) === 8
                ? substr($formData['postal_code'], 0, 5) . '-' . substr($formData['postal_code'], 5, 3)
                : $formData['postal_code'];
            
            // Formatar telefone completo
            $phoneFull = $formData['phone_code'] . ' ' . $formData['phone'];
            
            // Hash da senha
            $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
            
            // Inserir cliente
            $stmt = db()->prepare("
                INSERT INTO clients (
                    first_name, last_name, company_name, email, phone, cpf, cnpj,
                    address, address_number, address2, neighborhood, city, state, postal_code, country,
                    password_hash, email_verified, email_verification_token, newsletter_subscribed, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $formData['first_name'],
                $formData['last_name'],
                !empty($formData['company_name']) ? $formData['company_name'] : null,
                $formData['email'],
                $phoneFull,
                $cpfFormatted,
                $cnpjFormatted,
                $formData['address'],
                $formData['address_number'],
                !empty($formData['address2']) ? $formData['address2'] : null,
                $formData['neighborhood'],
                $formData['city'],
                $formData['state'],
                $cepFormatted,
                $formData['country'],
                $passwordHash,
                $verificationToken,
                $formData['newsletter'] ? 1 : 0,
            ]);
            
            // Redirecionar para página de sucesso
            header('Location: /central/registrar-sucesso.php?email=' . urlencode($formData['email']));
            exit;
            
        } catch (Throwable $e) {
            $error = 'Erro ao cadastrar. Tente novamente.';
        }
    }
}

// Carregar menu e footer (menu dinâmico via PHP + footer estático)
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
$footerHtml = is_file($footerFile) ? (string)file_get_contents($footerFile) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - Central GouTec</title>
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

        

        /* Avatar do usuário */
        }
        }

        /* Avatar do usuário */
        }
        }
            min-width: 20px; height: 20px;

        /* Avatar do usuário */
        }
        }
            border-radius: 999px;

        /* Avatar do usuário */
        }
        }
            display:flex; align-items:center; justify-content:center;

        /* Avatar do usuário */
        }
        }
            font-size: .75rem; font-weight: 800;

        /* Avatar do usuário */
        }
        }
            color: #061017;

        /* Avatar do usuário */
        }
        }
            background: linear-gradient(90deg, var(--warn), #ffef96);

        /* Avatar do usuário */
        }
        }
            border: 1px solid rgba(0,0,0,.35);

        /* Avatar do usuário */
        }
        }
        }

        /* Avatar do usuário */
        }
        }

        /* REGISTER LAYOUT */
        /* Mais espaço entre o menu e o card do formulário */
        main{ flex:1; padding: 120px 0; }
        .register-layout{
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
        .sidebar__text{
            color: rgba(232,237,247,.72);
            font-size: .95rem;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .sidebar__btn{
            width:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06);
            color: rgba(232,237,247,.92);
            font-weight: 800;
            transition: .18s ease;
            text-decoration:none;
            margin-bottom: 10px;
        }
        .sidebar__btn:hover{
            background: rgba(255,255,255,.09);
            transform: translateY(-2px);
        }
        .sidebar__btn--primary{
            background: linear-gradient(90deg, var(--primary2), #5de5ff);
            color: #061017;
            border-color: rgba(61,214,245,.30);
        }
        .sidebar__btn--primary:hover{
            background: linear-gradient(90deg, #5de5ff, #7df5ff);
        }

        /* FORM */
        .register-form{
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.04));
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 16px 60px rgba(0,0,0,.35);
        }
        .register-title,
        .form__title{
            font-size: clamp(1.8rem, 3.2vw, 2.4rem);
            font-weight: 900;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .register-desc,
        .form__desc{
            color: rgba(232,237,247,.72);
            font-size: 1.05rem;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        .form__section{
            margin-bottom: 32px;
        }
        .form__section-title{
            font-size: 1.2rem;
            font-weight: 900;
            margin-bottom: 20px;
            color: var(--primary2);
            display:flex;
            align-items:center;
            gap: 10px;
        }
        .form__section-title i{
            font-size: 1.3rem;
        }
        .form__row{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .form__grid{
            display:grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 0;
        }
        @media (max-width: 768px){
            .form__grid{
                grid-template-columns: 1fr;
            }
        }
        .form__group{
            display:flex;
            flex-direction:column;
            gap: 8px;
            margin-bottom: 0;
        }
        .form__group--full{
            grid-column: 1 / -1;
        }
        .form__label{
            font-weight: 800;
            font-size: .95rem;
            color: rgba(232,237,247,.92);
        }
        .form__label .optional{
            color: rgba(232,237,247,.55);
            font-weight: 400;
            font-size: .85rem;
        }
        .form__input{
            width:100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 1rem;
            outline:none;
            transition: .2s ease;
        }
        .form__input:focus{
            border-color: rgba(61,214,245,.45);
            box-shadow: 0 0 0 4px rgba(61,214,245,.12);
            background: rgba(255,255,255,.08);
        }
        .form__select{
            width:100%;
            padding: 12px 40px 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 1rem;
            outline:none;
            transition: .2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23e8edf7' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px;
            cursor: pointer;
        }
        .form__select:hover{
            border-color: rgba(255,255,255,.20);
            background-color: rgba(255,255,255,.08);
        }
        .form__select:focus{
            border-color: rgba(61,214,245,.45);
            box-shadow: 0 0 0 4px rgba(61,214,245,.12);
            background-color: rgba(255,255,255,.08);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%233dd6f5' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        }
        .form__input--phone{
            display:flex;
            gap: 8px;
        }
        .form__input--phone select{
            flex: 0 0 auto;
            width: auto;
            min-width: 140px;
        }
        .form__input--phone input{
            flex: 1;
        }
        .form__input::placeholder{
            color: rgba(232,237,247,.45);
        }
        .form__select option{
            background: #0b1220;
            color: var(--text);
            padding: 12px;
        }
        .form__select option:checked{
            background: rgba(61,214,245,.15);
            color: var(--primary2);
        }
        .form__divider{
            grid-column: 1 / -1;
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--primary2);
            margin: 32px 0 20px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,.12);
            display:flex;
            align-items:center;
            gap: 10px;
        }
        .form__divider:first-child{
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }
        .form__divider i{
            font-size: 1.3rem;
        }
        .form__checkbox-group{
            display:flex;
            align-items:flex-start;
            gap: 12px;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
        }
        .form__checkbox{
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor:pointer;
        }
        .form__checkbox-label{
            flex:1;
            color: rgba(232,237,247,.85);
            font-size: .95rem;
            line-height: 1.6;
        }
        .form__checkbox-label a{
            color: var(--primary2);
            text-decoration:underline;
        }
        .form__check{
            display:flex;
            align-items:flex-start;
            gap: 12px;
            cursor:pointer;
        }
        .form__check input[type="checkbox"]{
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor:pointer;
            flex-shrink: 0;
        }
        .form__check span{
            color: rgba(232,237,247,.85);
            font-size: .95rem;
            line-height: 1.6;
        }
        .form__check a{
            color: var(--primary2);
            text-decoration:underline;
        }
        .form__radio{
            display:flex;
            flex-direction:column;
            gap: 12px;
        }
        .form__radio > .form__label{
            margin-bottom: 4px;
        }
        .form__radio label{
            display:flex;
            align-items:center;
            gap: 8px;
            cursor:pointer;
            color: rgba(232,237,247,.85);
            font-size: .95rem;
        }
        .form__radio input[type="radio"]{
            width: 18px;
            height: 18px;
            cursor:pointer;
        }
        .form__error{
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
        .form__error i{
            font-size: 1.3rem;
        }
        .form__submit{
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
            margin-top: 32px;
        }
        .form__submit:hover{
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(61,214,245,.3);
        }
        .form__submit i{
            font-size: 1.3rem;
        }

        /* MODAL */
        .modal{
            display:none;
            position:fixed;
            inset:0;
            background: rgba(0,0,0,.75);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items:center;
            justify-content:center;
            padding: 20px;
        }
        .modal.is-open{
            display:flex;
        }
        .modal__content{
            background: linear-gradient(180deg, rgba(13,18,32,.98), rgba(7,10,18,.98));
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow);
        }
        .modal__title{
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 20px;
        }
        .modal__group{
            margin-bottom: 20px;
        }
        .modal__label{
            display:block;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .modal__input{
            width:100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 1rem;
            outline:none;
        }
        .modal__password-display{
            background: rgba(0,0,0,.3);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 14px;
            padding: 16px;
            margin: 16px 0;
            font-family: monospace;
            font-size: 1.1rem;
            word-break: break-all;
            text-align:center;
        }
        .modal__actions{
            display:flex;
            gap: 10px;
            flex-wrap:wrap;
        }
        .modal__btn{
            flex:1;
            padding: 12px 16px;
            border:none;
            border-radius: 14px;
            font-weight: 800;
            cursor:pointer;
            transition: .18s ease;
        }
        .modal__btn--primary{
            background: linear-gradient(90deg, var(--primary2), #5de5ff);
            color: #061017;
        }
        .modal__btn--secondary{
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.14);
            color: var(--text);
        }
        .modal__btn:hover{
            transform: translateY(-2px);
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
        @media (max-width: 980px){
            .register-layout{
                grid-template-columns: 1fr;
            }
            .sidebar{
                position:static;
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
            .register-form{
                padding: 20px;
            }
        }
    </style>
</head>
<body>
  <!-- Flocos de neve -->
  <div id="snowflakes" aria-hidden="true">
  <?php for ($i = 0; $i < 36; $i++):
      $left = mt_rand(0, 10000) / 100;
      $dur = mt_rand(280, 740) / 100;
      $delay = -mt_rand(0, 700) / 100;
      $op = mt_rand(35, 95) / 100;
      $size = mt_rand(10, 20);
  ?>
    <div class="snowflake" style="left:<?= $left ?>%;animation-duration:<?= $dur ?>s;animation-delay:<?= $delay ?>s;opacity:<?= $op ?>;font-size:<?= $size ?>px;">❄</div>
  <?php endfor; ?>
  </div>

  <div class="app">
    <div id="menu" aria-live="polite"><?= $menuHtml ?></div>

    <main>
      <div class="container">
        <div class="register-layout">
          <aside class="sidebar">
            <div class="sidebar__section">
              <div class="sidebar__title"><i class="las la-user"></i> Já é cadastrado?</div>
              <div class="sidebar__actions">
                <a class="sidebar__btn" href="/entrar"><i class="las la-sign-in-alt"></i> Login</a>
                <a class="sidebar__btn sidebar__btn--ghost" href="/central/redefinir-senha.php"><i class="las la-key"></i> Redefinir Senha</a>
              </div>
            </div>
          </aside>

          <section class="register-form">
            <h1 class="register-title">Criar Conta</h1>
            <p class="register-desc">Preencha os dados abaixo para finalizar seu cadastro.</p>

            <?php if (!empty($error)): ?>
              <div class="form__error"><i class="las la-exclamation-circle"></i> <?= h((string)$error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" novalidate>
              <div class="form__grid">
                <div class="form__group">
                  <label class="form__label" for="first_name">Nome <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="first_name" name="first_name" value="<?= h($formData['first_name'] ?? '') ?>" placeholder="Digite seu nome" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="last_name">Sobrenome <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="last_name" name="last_name" value="<?= h($formData['last_name'] ?? '') ?>" placeholder="Digite seu sobrenome" required>
                </div>
                <div class="form__group form__group--full">
                  <label class="form__label" for="email">Email <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" type="email" id="email" name="email" value="<?= h($formData['email'] ?? '') ?>" placeholder="seu@email.com" required>
                </div>

                <div class="form__group">
                  <label class="form__label" for="phone_code">DDD/País <span class="optional">(obrigatório)</span></label>
                  <select class="form__select" id="phone_code" name="phone_code">
                    <?php foreach ($countryCodes as $code => $label): ?>
                      <option value="<?= h($code) ?>" <?= (($formData['phone_code'] ?? '+55') === $code) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form__group">
                  <label class="form__label" for="phone">Telefone <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="phone" name="phone" value="<?= h($formData['phone'] ?? '') ?>" placeholder="(00) 00000-0000" required>
                </div>

                <div class="form__group form__group--full">
                  <label class="form__label" for="cpf">CPF <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="cpf" name="cpf" value="<?= h($formData['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                </div>

                <div class="form__divider form__group--full"><i class="las la-map-marker-alt"></i> Endereço de Cobrança</div>

                <div class="form__group form__group--full">
                  <label class="form__label" for="company_name">Empresa <span class="optional">(opcional)</span></label>
                  <input class="form__input" id="company_name" name="company_name" value="<?= h($formData['company_name'] ?? '') ?>" placeholder="Nome da empresa">
                </div>
                <div class="form__group form__group--full">
                  <label class="form__label" for="cnpj">CNPJ <span class="optional">(opcional)</span></label>
                  <input class="form__input" id="cnpj" name="cnpj" value="<?= h($formData['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
                </div>
                <div class="form__group form__group--full">
                  <label class="form__label" for="address">Endereço <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="address" name="address" value="<?= h($formData['address'] ?? '') ?>" placeholder="Rua, Avenida, etc." required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="address_number">Número <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="address_number" name="address_number" value="<?= h($formData['address_number'] ?? '') ?>" placeholder="123" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="address2">Complemento <span class="optional">(opcional)</span></label>
                  <input class="form__input" id="address2" name="address2" value="<?= h($formData['address2'] ?? '') ?>" placeholder="Apto, Bloco, etc.">
                </div>
                <div class="form__group form__group--full">
                  <label class="form__label" for="neighborhood">Bairro <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="neighborhood" name="neighborhood" value="<?= h($formData['neighborhood'] ?? '') ?>" placeholder="Nome do bairro" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="city">Cidade <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="city" name="city" value="<?= h($formData['city'] ?? '') ?>" placeholder="Nome da cidade" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="postal_code">CEP <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" id="postal_code" name="postal_code" value="<?= h($formData['postal_code'] ?? '') ?>" placeholder="00000-000" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="state">Estado <span class="optional">(obrigatório)</span></label>
                  <select class="form__select" id="state" name="state" required>
                    <option value="">Selecione o estado</option>
                    <?php foreach ($estados as $uf => $nome): ?>
                      <option value="<?= h($uf) ?>" <?= (($formData['state'] ?? '') === $uf) ? 'selected' : '' ?>><?= h($nome) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form__group">
                  <label class="form__label" for="country">País <span class="optional">(obrigatório)</span></label>
                  <select class="form__select" id="country" name="country" required>
                    <?php foreach ($paises as $k => $nome): ?>
                      <option value="<?= h($k) ?>" <?= (($formData['country'] ?? 'Brasil') === $k) ? 'selected' : '' ?>><?= h($nome) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form__divider form__group--full"><i class="las la-shield-alt"></i> Segurança da Conta</div>

                <div class="form__group">
                  <label class="form__label" for="password">Senha <span class="optional">(mín. 8 caracteres, 1 maiúscula)</span></label>
                  <input class="form__input" type="password" id="password" name="password" placeholder="Digite uma senha segura" required>
                </div>
                <div class="form__group">
                  <label class="form__label" for="password_confirm">Confirmar Senha <span class="optional">(obrigatório)</span></label>
                  <input class="form__input" type="password" id="password_confirm" name="password_confirm" placeholder="Digite a senha novamente" required>
                </div>

                <div class="form__group form__group--full">
                  <div class="form__radio">
                    <div class="form__label">Receber newsletter?</div>
                    <label><input type="radio" name="newsletter" value="yes" <?= !empty($formData['newsletter']) ? 'checked' : '' ?>> Sim</label>
                    <label><input type="radio" name="newsletter" value="no" <?= empty($formData) || empty($formData['newsletter']) ? 'checked' : '' ?>> Não</label>
                  </div>
                </div>

                <div class="form__group form__group--full" style="margin-top: 16px; margin-bottom: 0;">
                  <label class="form__check">
                    <input type="checkbox" name="terms" value="1" <?= !empty($formData['terms']) ? 'checked' : '' ?> required>
                    <span>Eu li e aceito os <a href="/termos" target="_blank" rel="noopener">Termos de Serviço</a></span>
                  </label>
                </div>
              </div>

              <button class="form__submit" type="submit">
                <i class="las la-user-plus"></i> Cadastrar
              </button>
            </form>
          </section>
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

    function maskDigits(el, max) {
      if (!el) return;
      el.addEventListener('input', () => {
        el.value = (el.value || '').replace(/\\D+/g, '').slice(0, max);
      });
    }
    function maskCPF(el) {
      if (!el) return;
      el.addEventListener('input', () => {
        let v = (el.value || '').replace(/\\D+/g, '').slice(0, 11);
        v = v.replace(/(\\d{3})(\\d)/, '$1.$2');
        v = v.replace(/(\\d{3})\\.(\\d{3})(\\d)/, '$1.$2.$3');
        v = v.replace(/(\\d{3})\\.(\\d{3})\\.(\\d{3})(\\d{1,2})$/, '$1.$2.$3-$4');
        el.value = v;
      });
    }
    function maskCNPJ(el) {
      if (!el) return;
      el.addEventListener('input', () => {
        let v = (el.value || '').replace(/\\D+/g, '').slice(0, 14);
        v = v.replace(/(\\d{2})(\\d)/, '$1.$2');
        v = v.replace(/(\\d{2})\\.(\\d{3})(\\d)/, '$1.$2.$3');
        v = v.replace(/\\.(\\d{3})(\\d)/, '.$1/$2');
        v = v.replace(/(\\d{4})(\\d)/, '$1-$2');
        el.value = v;
      });
    }
    function maskCEP(el) {
      if (!el) return;
      el.addEventListener('input', () => {
        let v = (el.value || '').replace(/\\D+/g, '').slice(0, 8);
        v = v.replace(/(\\d{5})(\\d)/, '$1-$2');
        el.value = v;
      });
    }
    function maskPhone(el) {
      if (!el) return;
      el.addEventListener('input', () => {
        let v = (el.value || '').replace(/\\D+/g, '').slice(0, 11);
        if (v.length <= 10) {
          v = v.replace(/(\\d{2})(\\d)/, '($1) $2');
          v = v.replace(/(\\d{4})(\\d)/, '$1-$2');
        } else {
          v = v.replace(/(\\d{2})(\\d)/, '($1) $2');
          v = v.replace(/(\\d{5})(\\d)/, '$1-$2');
        }
        el.value = v;
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        setupNav();
        maskCPF(document.getElementById('cpf'));
        maskCNPJ(document.getElementById('cnpj'));
        maskCEP(document.getElementById('postal_code'));
        maskPhone(document.getElementById('phone'));
      }, { once: true });
    } else {
      setupNav();
      maskCPF(document.getElementById('cpf'));
      maskCNPJ(document.getElementById('cnpj'));
      maskCEP(document.getElementById('postal_code'));
      maskPhone(document.getElementById('phone'));
    }
  </script>
</body>
</html>
