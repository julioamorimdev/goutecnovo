<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

$active = $active ?? '';

// Função para verificar se uma categoria está ativa
$isCategoryActive = function(array $items) use ($active): bool {
    return in_array($active, $items);
};

// Definir categorias e seus itens - Reorganizado para melhor distribuição
$categories = [
    'conteudo' => [
        'label' => 'Conteúdo do Site',
        'icon' => 'las la-file-alt',
        'items' => ['menu', 'footer', 'blog', 'feedback', 'announcements', 'downloads']
    ],
    'vendas' => [
        'label' => 'Vendas e Comercial',
        'icon' => 'las la-shopping-bag',
        'items' => ['plans', 'product_packages', 'addons', 'configurable_options', 'billable_items', 'quotations', 'clients', 'client_groups', 'client_custom_fields', 'orders', 'order_statuses', 'invoices', 'batch_pdf_export', 'offline_credit_card', 'gateway_transactions']
    ],
    'marketing' => [
        'label' => 'Marketing',
        'icon' => 'las la-bullhorn',
        'items' => ['promotions', 'tlds', 'domains', 'affiliates']
    ],
    'suporte' => [
        'label' => 'Suporte',
        'icon' => 'las la-headset',
        'items' => ['support_overview', 'tickets', 'support_departments', 'ticket_escalation', 'ticket_spam', 'canned_responses', 'kb_categories', 'kb_articles', 'cancellation_requests', 'disputes']
    ],
    'configuracoes' => [
        'label' => 'Configurações',
        'icon' => 'las la-cog',
        'items' => ['system_settings', 'automation_settings', 'tax_settings', 'storage_settings', 'currencies', 'email_templates']
    ],
    'seguranca' => [
        'label' => 'Segurança',
        'icon' => 'las la-shield-alt',
        'items' => ['admins', 'admin_roles', 'banned_ips', 'banned_email_domains', 'security_questions']
    ],
    'relatorios' => [
        'label' => 'Relatórios e Análises',
        'icon' => 'las la-chart-bar',
        'items' => ['reports', 'daily_performance', 'annual_report']
    ],
    'utilitarios' => [
        'label' => 'Utilitários',
        'icon' => 'las la-tools',
        'items' => ['whois_lookup', 'calendar', 'todos', 'staff_notices', 'database_maintenance', 'database_backups', 'license_data', 'my_account', 'my_notes']
    ],
    'sistema' => [
        'label' => 'Sistema',
        'icon' => 'las la-server',
        'items' => ['settings', 'network_incidents']
    ]
];
?>

<!-- Sidebar desktop -->
<aside class="admin-sidebar bg-dark text-white d-none d-md-block">
    <div class="admin-sidebar__brand border-bottom border-white border-opacity-10">
        <a href="/admin/dashboard.php" class="text-decoration-none d-flex align-items-center gap-3">
            <img src="/admin/assets/img/logo-light.png" alt="GouTec" style="height: 28px;">
        </a>
    </div>

    <div class="admin-sidebar__nav p-3">
        <div class="small text-white-50 mb-2">Navegação</div>
        <nav class="nav flex-column gap-1">
            <!-- Dashboard -->
            <a class="admin-nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin/dashboard.php">
                <i class="las la-tachometer-alt me-2"></i> Dashboard
            </a>

            <!-- Conteúdo do Site -->
            <?php $conteudoActive = $isCategoryActive($categories['conteudo']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $conteudoActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseConteudo" 
                        aria-expanded="<?= $conteudoActive ? 'true' : 'false' ?>"
                        aria-controls="collapseConteudo">
                    <span><i class="<?= $categories['conteudo']['icon'] ?> me-2"></i> <?= $categories['conteudo']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $conteudoActive ? 'show' : '' ?>" id="collapseConteudo">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'menu' ? 'active' : '' ?>" href="/admin/menu.php">
                            <i class="las la-stream me-2"></i> Menu do site
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'footer' ? 'active' : '' ?>" href="/admin/footer.php">
                            <i class="las la-window-minimize me-2"></i> Footer do site
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'blog' ? 'active' : '' ?>" href="/admin/blog.php">
                            <i class="las la-blog me-2"></i> Blog
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'feedback' ? 'active' : '' ?>" href="/admin/feedback.php">
                            <i class="las la-comment-dots me-2"></i> Feedbacks
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vendas e Comercial -->
            <?php $vendasActive = $isCategoryActive($categories['vendas']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $vendasActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseVendas" 
                        aria-expanded="<?= $vendasActive ? 'true' : 'false' ?>"
                        aria-controls="collapseVendas">
                    <span><i class="<?= $categories['vendas']['icon'] ?> me-2"></i> <?= $categories['vendas']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $vendasActive ? 'show' : '' ?>" id="collapseVendas">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'plans' ? 'active' : '' ?>" href="/admin/plans.php">
                            <i class="las la-box me-2"></i> Planos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'addons' ? 'active' : '' ?>" href="/admin/addons.php">
                            <i class="las la-puzzle-piece me-2"></i> Serviços Addons
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'billable_items' ? 'active' : '' ?>" href="/admin/billable_items.php">
                            <i class="las la-list me-2"></i> Itens Faturáveis
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'quotations' ? 'active' : '' ?>" href="/admin/quotations.php">
                            <i class="las la-file-invoice-dollar me-2"></i> Orçamentos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'clients' ? 'active' : '' ?>" href="/admin/clients.php">
                            <i class="las la-users me-2"></i> Clientes
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'orders' ? 'active' : '' ?>" href="/admin/orders.php">
                            <i class="las la-shopping-cart me-2"></i> Pedidos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'invoices' ? 'active' : '' ?>" href="/admin/invoices.php">
                            <i class="las la-file-invoice me-2"></i> Faturas
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'batch_pdf_export' ? 'active' : '' ?>" href="/admin/batch_pdf_export.php">
                            <i class="las la-file-pdf me-2"></i> Exportar PDFs em Lote
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'offline_credit_card' ? 'active' : '' ?>" href="/admin/offline_credit_card.php">
                            <i class="las la-credit-card me-2"></i> Cartão Off-line
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'gateway_transactions' ? 'active' : '' ?>" href="/admin/gateway_transactions.php">
                            <i class="las la-exchange-alt me-2"></i> Log Gateway
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tlds' ? 'active' : '' ?>" href="/admin/tlds.php">
                            <i class="las la-globe me-2"></i> TLDs
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'domains' ? 'active' : '' ?>" href="/admin/domains.php">
                            <i class="las la-link me-2"></i> Registros de Domínios
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'affiliates' ? 'active' : '' ?>" href="/admin/affiliates.php">
                            <i class="las la-user-friends me-2"></i> Afiliados
                        </a>
                    </div>
                </div>
            </div>

            <!-- Suporte -->
            <?php $suporteActive = $isCategoryActive($categories['suporte']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $suporteActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseSuporte" 
                        aria-expanded="<?= $suporteActive ? 'true' : 'false' ?>"
                        aria-controls="collapseSuporte">
                    <span><i class="<?= $categories['suporte']['icon'] ?> me-2"></i> <?= $categories['suporte']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $suporteActive ? 'show' : '' ?>" id="collapseSuporte">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'support_overview' ? 'active' : '' ?>" href="/admin/support_overview.php">
                            <i class="las la-chart-line me-2"></i> Visão Geral
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tickets' ? 'active' : '' ?>" href="/admin/tickets.php">
                            <i class="las la-headset me-2"></i> Tickets
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'canned_responses' ? 'active' : '' ?>" href="/admin/canned_responses.php">
                            <i class="las la-comment-dots me-2"></i> Respostas Predefinidas
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'kb_categories' ? 'active' : '' ?>" href="/admin/kb_categories.php">
                            <i class="las la-folder me-2"></i> Base de Conhecimento - Categorias
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'kb_articles' ? 'active' : '' ?>" href="/admin/kb_articles.php">
                            <i class="las la-file-alt me-2"></i> Base de Conhecimento - Artigos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'cancellation_requests' ? 'active' : '' ?>" href="/admin/cancellation_requests.php">
                            <i class="las la-times-circle me-2"></i> Solicitações de Cancelamento
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'disputes' ? 'active' : '' ?>" href="/admin/disputes.php">
                            <i class="las la-gavel me-2"></i> Disputas
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sistema -->
            <?php $sistemaActive = $isCategoryActive($categories['sistema']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $sistemaActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseSistema" 
                        aria-expanded="<?= $sistemaActive ? 'true' : 'false' ?>"
                        aria-controls="collapseSistema">
                    <span><i class="<?= $categories['sistema']['icon'] ?> me-2"></i> <?= $categories['sistema']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $sistemaActive ? 'show' : '' ?>" id="collapseSistema">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'admins' ? 'active' : '' ?>" href="/admin/admins.php">
                            <i class="las la-user-shield me-2"></i> Administradores
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings_logos.php">
                            <i class="las la-cog me-2"></i> Configurações
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'network_incidents' ? 'active' : '' ?>" href="/admin/network_incidents.php">
                            <i class="las la-network-wired me-2"></i> Falhas na Rede
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'reports' ? 'active' : '' ?>" href="/admin/reports.php">
                            <i class="las la-chart-bar me-2"></i> Relatórios
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'daily_performance' ? 'active' : '' ?>" href="/admin/daily_performance.php">
                            <i class="las la-tachometer-alt me-2"></i> Performance Diária
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'annual_report' ? 'active' : '' ?>" href="/admin/annual_report.php">
                            <i class="las la-calendar-alt me-2"></i> Relatório Anual
                        </a>
                    </div>
                </div>
            </div>

            <!-- Utilitários -->
            <?php $utilitariosActive = $isCategoryActive($categories['utilitarios']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $utilitariosActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseUtilitarios" 
                        aria-expanded="<?= $utilitariosActive ? 'true' : 'false' ?>"
                        aria-controls="collapseUtilitarios">
                    <span><i class="<?= $categories['utilitarios']['icon'] ?> me-2"></i> <?= $categories['utilitarios']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $utilitariosActive ? 'show' : '' ?>" id="collapseUtilitarios">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'whois_lookup' ? 'active' : '' ?>" href="/admin/whois_lookup.php">
                            <i class="las la-globe me-2"></i> Pesquisa WHOIS
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'calendar' ? 'active' : '' ?>" href="/admin/calendar.php">
                            <i class="las la-calendar me-2"></i> Calendário
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'todos' ? 'active' : '' ?>" href="/admin/todos.php">
                            <i class="las la-tasks me-2"></i> Itens a Fazer
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'database_maintenance' ? 'active' : '' ?>" href="/admin/database_maintenance.php">
                            <i class="las la-database me-2"></i> Manutenção do Banco
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'staff_notices' ? 'active' : '' ?>" href="/admin/staff_notices.php">
                            <i class="las la-clipboard-list me-2"></i> Quadro de Avisos
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <hr class="border-white border-opacity-10 my-4">

        <div class="d-flex align-items-center justify-content-between">
            <div class="small text-white-50">
                Logado como<br>
                <span class="text-white fw-semibold"><?= h($_SESSION['admin_username'] ?? '') ?></span>
            </div>
            <a class="btn btn-sm btn-outline-light" href="/admin/logout.php">Sair</a>
        </div>
    </div>
</aside>

<!-- Sidebar mobile (offcanvas) -->
<div class="offcanvas offcanvas-start bg-dark text-white d-md-none" tabindex="-1" id="adminSidebarOffcanvas" aria-labelledby="adminSidebarOffcanvasLabel">
    <div class="offcanvas-header border-bottom border-white border-opacity-10">
        <a href="/admin/dashboard.php" class="text-decoration-none d-flex align-items-center gap-3" id="adminSidebarOffcanvasLabel">
            <img src="/admin/assets/img/logo-light.png" alt="GouTec" style="height: 28px;">
            <span class="fw-semibold text-white">Admin</span>
        </a>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-3">
        <nav class="nav flex-column gap-1">
            <!-- Dashboard -->
            <a class="admin-nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin/dashboard.php">
                <i class="las la-tachometer-alt me-2"></i> Dashboard
            </a>

            <!-- Conteúdo do Site -->
            <?php $conteudoActive = $isCategoryActive($categories['conteudo']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $conteudoActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseConteudoMobile" 
                        aria-expanded="<?= $conteudoActive ? 'true' : 'false' ?>"
                        aria-controls="collapseConteudoMobile">
                    <span><i class="<?= $categories['conteudo']['icon'] ?> me-2"></i> <?= $categories['conteudo']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $conteudoActive ? 'show' : '' ?>" id="collapseConteudoMobile">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'menu' ? 'active' : '' ?>" href="/admin/menu.php">
                            <i class="las la-stream me-2"></i> Menu do site
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'footer' ? 'active' : '' ?>" href="/admin/footer.php">
                            <i class="las la-window-minimize me-2"></i> Footer do site
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'blog' ? 'active' : '' ?>" href="/admin/blog.php">
                            <i class="las la-blog me-2"></i> Blog
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'feedback' ? 'active' : '' ?>" href="/admin/feedback.php">
                            <i class="las la-comment-dots me-2"></i> Feedbacks
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'announcements' ? 'active' : '' ?>" href="/admin/announcements.php">
                            <i class="las la-bullhorn me-2"></i> Anúncios
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'downloads' ? 'active' : '' ?>" href="/admin/downloads.php">
                            <i class="las la-download me-2"></i> Downloads
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vendas e Comercial -->
            <?php $vendasActive = $isCategoryActive($categories['vendas']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $vendasActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseVendasMobile" 
                        aria-expanded="<?= $vendasActive ? 'true' : 'false' ?>"
                        aria-controls="collapseVendasMobile">
                    <span><i class="<?= $categories['vendas']['icon'] ?> me-2"></i> <?= $categories['vendas']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $vendasActive ? 'show' : '' ?>" id="collapseVendasMobile">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'plans' ? 'active' : '' ?>" href="/admin/plans.php">
                            <i class="las la-box me-2"></i> Planos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'addons' ? 'active' : '' ?>" href="/admin/addons.php">
                            <i class="las la-puzzle-piece me-2"></i> Serviços Addons
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'billable_items' ? 'active' : '' ?>" href="/admin/billable_items.php">
                            <i class="las la-list me-2"></i> Itens Faturáveis
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'quotations' ? 'active' : '' ?>" href="/admin/quotations.php">
                            <i class="las la-file-invoice-dollar me-2"></i> Orçamentos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'clients' ? 'active' : '' ?>" href="/admin/clients.php">
                            <i class="las la-users me-2"></i> Clientes
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'orders' ? 'active' : '' ?>" href="/admin/orders.php">
                            <i class="las la-shopping-cart me-2"></i> Pedidos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'invoices' ? 'active' : '' ?>" href="/admin/invoices.php">
                            <i class="las la-file-invoice me-2"></i> Faturas
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'batch_pdf_export' ? 'active' : '' ?>" href="/admin/batch_pdf_export.php">
                            <i class="las la-file-pdf me-2"></i> Exportar PDFs em Lote
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'offline_credit_card' ? 'active' : '' ?>" href="/admin/offline_credit_card.php">
                            <i class="las la-credit-card me-2"></i> Cartão Off-line
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'gateway_transactions' ? 'active' : '' ?>" href="/admin/gateway_transactions.php">
                            <i class="las la-exchange-alt me-2"></i> Log Gateway
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tlds' ? 'active' : '' ?>" href="/admin/tlds.php">
                            <i class="las la-globe me-2"></i> TLDs
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'domains' ? 'active' : '' ?>" href="/admin/domains.php">
                            <i class="las la-link me-2"></i> Registros de Domínios
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'affiliates' ? 'active' : '' ?>" href="/admin/affiliates.php">
                            <i class="las la-user-friends me-2"></i> Afiliados
                        </a>
                    </div>
                </div>
            </div>

            <!-- Suporte -->
            <?php $suporteActive = $isCategoryActive($categories['suporte']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $suporteActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseSuporteMobile" 
                        aria-expanded="<?= $suporteActive ? 'true' : 'false' ?>"
                        aria-controls="collapseSuporteMobile">
                    <span><i class="<?= $categories['suporte']['icon'] ?> me-2"></i> <?= $categories['suporte']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $suporteActive ? 'show' : '' ?>" id="collapseSuporteMobile">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'support_overview' ? 'active' : '' ?>" href="/admin/support_overview.php">
                            <i class="las la-chart-line me-2"></i> Visão Geral
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tickets' ? 'active' : '' ?>" href="/admin/tickets.php">
                            <i class="las la-headset me-2"></i> Tickets
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'canned_responses' ? 'active' : '' ?>" href="/admin/canned_responses.php">
                            <i class="las la-comment-dots me-2"></i> Respostas Predefinidas
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'kb_categories' ? 'active' : '' ?>" href="/admin/kb_categories.php">
                            <i class="las la-folder me-2"></i> Base de Conhecimento - Categorias
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'kb_articles' ? 'active' : '' ?>" href="/admin/kb_articles.php">
                            <i class="las la-file-alt me-2"></i> Base de Conhecimento - Artigos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'cancellation_requests' ? 'active' : '' ?>" href="/admin/cancellation_requests.php">
                            <i class="las la-times-circle me-2"></i> Solicitações de Cancelamento
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'disputes' ? 'active' : '' ?>" href="/admin/disputes.php">
                            <i class="las la-gavel me-2"></i> Disputas
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sistema -->
            <?php $sistemaActive = $isCategoryActive($categories['sistema']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $sistemaActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseSistemaMobile" 
                        aria-expanded="<?= $sistemaActive ? 'true' : 'false' ?>"
                        aria-controls="collapseSistemaMobile">
                    <span><i class="<?= $categories['sistema']['icon'] ?> me-2"></i> <?= $categories['sistema']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $sistemaActive ? 'show' : '' ?>" id="collapseSistemaMobile">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'admins' ? 'active' : '' ?>" href="/admin/admins.php">
                            <i class="las la-user-shield me-2"></i> Administradores
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings_logos.php">
                            <i class="las la-cog me-2"></i> Configurações
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'network_incidents' ? 'active' : '' ?>" href="/admin/network_incidents.php">
                            <i class="las la-network-wired me-2"></i> Falhas na Rede
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'reports' ? 'active' : '' ?>" href="/admin/reports.php">
                            <i class="las la-chart-bar me-2"></i> Relatórios
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'daily_performance' ? 'active' : '' ?>" href="/admin/daily_performance.php">
                            <i class="las la-tachometer-alt me-2"></i> Performance Diária
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'annual_report' ? 'active' : '' ?>" href="/admin/annual_report.php">
                            <i class="las la-calendar-alt me-2"></i> Relatório Anual
                        </a>
                    </div>
                </div>
            </div>

            <!-- Utilitários -->
            <?php $utilitariosActive = $isCategoryActive($categories['utilitarios']['items']); ?>
            <div class="admin-nav-dropdown">
                <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $utilitariosActive ? 'active' : '' ?>" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseUtilitariosMobile" 
                        aria-expanded="<?= $utilitariosActive ? 'true' : 'false' ?>"
                        aria-controls="collapseUtilitariosMobile">
                    <span><i class="<?= $categories['utilitarios']['icon'] ?> me-2"></i> <?= $categories['utilitarios']['label'] ?></span>
                    <i class="las la-angle-down admin-nav-arrow"></i>
                </button>
                <div class="collapse <?= $utilitariosActive ? 'show' : '' ?>" id="collapseUtilitariosMobile">
                    <div class="admin-nav-submenu ps-3 mt-1">
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'whois_lookup' ? 'active' : '' ?>" href="/admin/whois_lookup.php">
                            <i class="las la-globe me-2"></i> Pesquisa WHOIS
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'calendar' ? 'active' : '' ?>" href="/admin/calendar.php">
                            <i class="las la-calendar me-2"></i> Calendário
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'todos' ? 'active' : '' ?>" href="/admin/todos.php">
                            <i class="las la-tasks me-2"></i> Itens a Fazer
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'database_maintenance' ? 'active' : '' ?>" href="/admin/database_maintenance.php">
                            <i class="las la-database me-2"></i> Manutenção do Banco
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'staff_notices' ? 'active' : '' ?>" href="/admin/staff_notices.php">
                            <i class="las la-clipboard-list me-2"></i> Quadro de Avisos
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        <hr class="border-white border-opacity-10 my-4">
        <div class="d-flex align-items-center justify-content-between">
            <div class="small text-white-50">
                Logado como<br>
                <span class="text-white fw-semibold"><?= h($_SESSION['admin_username'] ?? '') ?></span>
            </div>
            <a class="btn btn-sm btn-outline-light" href="/admin/logout.php">Sair</a>
        </div>
    </div>
</div>


