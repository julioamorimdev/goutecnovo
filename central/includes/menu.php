<?php
declare(strict_types=1);
// Menu dinâmico da Central
// Garantir que as funções necessárias existam
if (!function_exists('is_client_logged_in')) {
    function is_client_logged_in(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return !empty($_SESSION['client_id'] ?? null);
    }
}
if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

try {
    $isLoggedIn = is_client_logged_in();
    $clientName = $_SESSION['client_name'] ?? '';
    $clientFirstName = '';
    $clientInitial = '?';

    if ($isLoggedIn && !empty($clientName)) {
        $nameParts = explode(' ', trim($clientName));
        $clientFirstName = $nameParts[0] ?? '';
        if (!empty($clientFirstName)) {
            $clientInitial = strtoupper(mb_substr($clientFirstName, 0, 1, 'UTF-8'));
        }
    }
} catch (Throwable $e) {
    // Em caso de erro, assumir que não está logado
    $isLoggedIn = false;
    $clientName = '';
    $clientFirstName = '';
    $clientInitial = '?';
}
?>
<style>
  /* Estilos mínimos do usuário logado (avatar + "Olá, Nome") para funcionar em todas as páginas */
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
</style>
<!-- Menu da Central (gerenciável via Admin) -->
<header class="topbar" role="banner">
  <div class="container topbar__inner">
    <a class="brand" href="/" aria-label="GouTec - Central">
      <img class="brand__logo" src="/admin/assets/img/logo-light.png" alt="GouTec">
    </a>

    <nav class="nav" aria-label="Menu principal">
      <button class="nav__toggle" type="button" aria-label="Abrir menu" aria-expanded="false" data-nav-toggle>
        <i class="las la-bars" aria-hidden="true"></i>
      </button>

      <ul class="nav__list" data-nav-list>
        <?php if ($isLoggedIn): ?>
        <li class="nav__item nav__item--dropdown" data-dropdown>
          <button class="nav__link nav__link--btn" type="button" data-dropdown-toggle aria-expanded="false">
            Cliente <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/client-area">
              <i class="las la-user-circle" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Área do Cliente</div>
                <div class="dropdown__desc">Acesse sua conta</div>
              </div>
            </a>
            <a class="dropdown__item" href="/faturas">
              <i class="las la-file-invoice" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Faturas</div>
                <div class="dropdown__desc">Pagamentos e histórico</div>
              </div>
            </a>
            <a class="dropdown__item" href="/meus-tickets">
              <i class="las la-life-ring" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Meus Tickets</div>
                <div class="dropdown__desc">Acompanhe seus chamados</div>
              </div>
            </a>
          </div>
        </li>
        <?php endif; ?>

        <li class="nav__item nav__item--dropdown" data-dropdown>
          <button class="nav__link nav__link--btn" type="button" data-dropdown-toggle aria-expanded="false">
            Serviços <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/produtos/hospedagem">
              <i class="las la-server" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Hospedagem</div>
                <div class="dropdown__desc">Planos rápidos e estáveis</div>
              </div>
            </a>
            <a class="dropdown__item" href="/produtos/vps">
              <i class="las la-cloud" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">VPS Cloud</div>
                <div class="dropdown__desc">Recursos dedicados sob demanda</div>
              </div>
            </a>
            <a class="dropdown__item" href="/produtos/dominios">
              <i class="las la-globe" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Domínios</div>
                <div class="dropdown__desc">Registro, DNS e transferência</div>
              </div>
            </a>
            <a class="dropdown__item" href="/produtos/ssl">
              <i class="las la-lock" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">SSL</div>
                <div class="dropdown__desc">Segurança e confiança</div>
              </div>
            </a>
          </div>
        </li>

        <li class="nav__item nav__item--dropdown" data-dropdown>
          <button class="nav__link nav__link--btn" type="button" data-dropdown-toggle aria-expanded="false">
            Mais <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/anuncios">
              <i class="las la-bullhorn" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Anúncios</div>
                <div class="dropdown__desc">Comunicados e novidades</div>
              </div>
            </a>
            <a class="dropdown__item" href="/status">
              <i class="las la-network-wired" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Status de Rede</div>
                <div class="dropdown__desc">Incidentes e manutenção</div>
              </div>
            </a>
            <a class="dropdown__item" href="/base-conhecimento">
              <i class="las la-book" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Base de Conhecimento</div>
                <div class="dropdown__desc">Guias e respostas rápidas</div>
              </div>
            </a>
            <a class="dropdown__item" href="/downloads">
              <i class="las la-download" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Downloads</div>
                <div class="dropdown__desc">Ferramentas e arquivos</div>
              </div>
            </a>
          </div>
        </li>

        <?php if (!$isLoggedIn): ?>
        <li class="nav__item nav__item--dropdown" data-dropdown>
          <button class="nav__link nav__link--btn" type="button" data-dropdown-toggle aria-expanded="false">
            Conta <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/entrar">
              <i class="las la-sign-in-alt" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Entrar</div>
                <div class="dropdown__desc">Acesse sua conta</div>
              </div>
            </a>
            <a class="dropdown__item" href="/registrar">
              <i class="las la-user-plus" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Criar conta</div>
                <div class="dropdown__desc">Cadastre-se em poucos passos</div>
              </div>
            </a>
          </div>
        </li>
        <?php endif; ?>

        <li class="nav__item nav__item--dropdown nav__item--align-right" data-dropdown>
          <button class="nav__link nav__link--btn nav__link--support" type="button" data-dropdown-toggle aria-expanded="false">
            Suporte <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/abrir-ticket">
              <i class="las la-headset" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Abrir Ticket</div>
                <div class="dropdown__desc">Fale com a equipe</div>
              </div>
            </a>
            <a class="dropdown__item" href="/contato">
              <i class="las la-envelope" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Contato</div>
                <div class="dropdown__desc">Canais e horários</div>
              </div>
            </a>
            <a class="dropdown__item" href="/base-conhecimento">
              <i class="las la-book" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Base de Conhecimento</div>
                <div class="dropdown__desc">Tutoriais e guias</div>
              </div>
            </a>
            <a class="dropdown__item" href="/status">
              <i class="las la-signal" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Status</div>
                <div class="dropdown__desc">Serviços e incidentes</div>
              </div>
            </a>
          </div>
        </li>

        <?php if ($isLoggedIn): ?>
        <li class="nav__item nav__item--dropdown nav__item--align-right nav__item--user" data-dropdown>
          <button class="nav__link nav__link--btn nav__link--user nav__link--support" type="button" data-dropdown-toggle aria-expanded="false">
            <div class="nav__user-avatar">
              <span class="nav__user-initial"><?= h($clientInitial) ?></span>
            </div>
            <span class="nav__user-name">Olá, <?= h($clientFirstName ?: 'Cliente') ?></span>
            <i class="las la-angle-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown" data-dropdown-menu>
            <a class="dropdown__item" href="/sair">
              <i class="las la-sign-out-alt" aria-hidden="true"></i>
              <div>
                <div class="dropdown__title">Sair</div>
                <div class="dropdown__desc">Encerrar sessão</div>
              </div>
            </a>
          </div>
        </li>
        <?php endif; ?>

        <li class="nav__item nav__item--cart">
          <a class="nav__cart" href="/carrinho" aria-label="Carrinho">
            <i class="las la-shopping-cart" aria-hidden="true"></i>
            <span class="nav__cartBadge" aria-label="0 itens">0</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</header>

