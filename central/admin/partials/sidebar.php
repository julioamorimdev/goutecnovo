<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

$active = $active ?? '';

// Função para verificar se uma categoria está ativa
$isCategoryActive = function(array $items) use ($active): bool {
    return in_array($active, $items);
};

// Buscar foto de perfil do admin
$adminPhoto = null;
if (isset($_SESSION['admin_user_id'])) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT profile_photo FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $adminData = $stmt->fetch();
        $adminPhoto = $adminData['profile_photo'] ?? null;
    } catch (Throwable $e) {
        $adminPhoto = null;
    }
}

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

// Função para renderizar categoria
function renderCategory($catKey, $category, $isActive, $isCategoryActive, $active) {
    $categoryId = 'collapse' . ucfirst($catKey);
    $items = $category['items'];
    ?>
    <div class="admin-nav-dropdown menu-category" data-category="<?= h($category['label']) ?>">
        <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $isActive ? 'active' : '' ?>" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#<?= $categoryId ?>" 
                aria-expanded="<?= $isActive ? 'true' : 'false' ?>"
                aria-controls="<?= $categoryId ?>"
                data-search="<?= h(strtolower($category['label'])) ?>">
            <span><i class="<?= $category['icon'] ?> me-2"></i> <?= $category['label'] ?></span>
            <i class="las la-angle-down admin-nav-arrow"></i>
        </button>
        <div class="collapse <?= $isActive ? 'show' : '' ?>" id="<?= $categoryId ?>">
            <div class="admin-nav-submenu ps-3 mt-1">
                <?php
                // Mapear itens para URLs e labels
                $itemMap = [
                    'menu' => ['url' => '/admin/menu.php', 'label' => 'Menu do site', 'icon' => 'las la-stream'],
                    'footer' => ['url' => '/admin/footer.php', 'label' => 'Footer do site', 'icon' => 'las la-window-minimize'],
                    'blog' => ['url' => '/admin/blog.php', 'label' => 'Blog', 'icon' => 'las la-blog'],
                    'feedback' => ['url' => '/admin/feedback.php', 'label' => 'Feedbacks', 'icon' => 'las la-comment-dots'],
                    'announcements' => ['url' => '/admin/announcements.php', 'label' => 'Anúncios', 'icon' => 'las la-bullhorn'],
                    'downloads' => ['url' => '/admin/downloads.php', 'label' => 'Downloads', 'icon' => 'las la-download'],
                    'plans' => ['url' => '/admin/plans.php', 'label' => 'Planos', 'icon' => 'las la-box'],
                    'product_packages' => ['url' => '/admin/product_packages.php', 'label' => 'Pacotes de Produtos', 'icon' => 'las la-boxes'],
                    'addons' => ['url' => '/admin/addons.php', 'label' => 'Addons do Produto', 'icon' => 'las la-puzzle-piece'],
                    'configurable_options' => ['url' => '/admin/configurable_option_groups.php', 'label' => 'Opções Configuráveis', 'icon' => 'las la-sliders-h'],
                    'billable_items' => ['url' => '/admin/billable_items.php', 'label' => 'Itens Faturáveis', 'icon' => 'las la-list'],
                    'quotations' => ['url' => '/admin/quotations.php', 'label' => 'Orçamentos', 'icon' => 'las la-file-invoice-dollar'],
                    'clients' => ['url' => '/admin/clients.php', 'label' => 'Clientes', 'icon' => 'las la-users'],
                    'client_groups' => ['url' => '/admin/client_groups.php', 'label' => 'Grupos de Clientes', 'icon' => 'las la-users-cog'],
                    'client_custom_fields' => ['url' => '/admin/client_custom_fields.php', 'label' => 'Campos Personalizados', 'icon' => 'las la-edit'],
                    'orders' => ['url' => '/admin/orders.php', 'label' => 'Pedidos', 'icon' => 'las la-shopping-cart'],
                    'order_statuses' => ['url' => '/admin/order_statuses.php', 'label' => 'Status dos Pedidos', 'icon' => 'las la-tags'],
                    'invoices' => ['url' => '/admin/invoices.php', 'label' => 'Faturas', 'icon' => 'las la-file-invoice'],
                    'batch_pdf_export' => ['url' => '/admin/batch_pdf_export.php', 'label' => 'Exportar PDFs em Lote', 'icon' => 'las la-file-pdf'],
                    'offline_credit_card' => ['url' => '/admin/offline_credit_card.php', 'label' => 'Cartão Off-line', 'icon' => 'las la-credit-card'],
                    'gateway_transactions' => ['url' => '/admin/gateway_transactions.php', 'label' => 'Log Gateway', 'icon' => 'las la-exchange-alt'],
                    'promotions' => ['url' => '/admin/promotions.php', 'label' => 'Promoções e Cupons', 'icon' => 'las la-tags'],
                    'tlds' => ['url' => '/admin/tlds.php', 'label' => 'TLDs', 'icon' => 'las la-globe'],
                    'domains' => ['url' => '/admin/domains.php', 'label' => 'Registros de Domínios', 'icon' => 'las la-link'],
                    'affiliates' => ['url' => '/admin/affiliates.php', 'label' => 'Afiliados', 'icon' => 'las la-user-friends'],
                    'support_overview' => ['url' => '/admin/support_overview.php', 'label' => 'Visão Geral', 'icon' => 'las la-chart-line'],
                    'tickets' => ['url' => '/admin/tickets.php', 'label' => 'Tickets', 'icon' => 'las la-headset'],
                    'support_departments' => ['url' => '/admin/support_departments.php', 'label' => 'Departamentos', 'icon' => 'las la-building'],
                    'ticket_escalation' => ['url' => '/admin/ticket_escalation.php', 'label' => 'Escalonamento', 'icon' => 'las la-arrow-up'],
                    'ticket_spam' => ['url' => '/admin/ticket_spam.php', 'label' => 'Controle de Spam', 'icon' => 'las la-ban'],
                    'canned_responses' => ['url' => '/admin/canned_responses.php', 'label' => 'Respostas Predefinidas', 'icon' => 'las la-comment-dots'],
                    'kb_categories' => ['url' => '/admin/kb_categories.php', 'label' => 'KB - Categorias', 'icon' => 'las la-folder'],
                    'kb_articles' => ['url' => '/admin/kb_articles.php', 'label' => 'KB - Artigos', 'icon' => 'las la-file-alt'],
                    'cancellation_requests' => ['url' => '/admin/cancellation_requests.php', 'label' => 'Solicitações de Cancelamento', 'icon' => 'las la-times-circle'],
                    'disputes' => ['url' => '/admin/disputes.php', 'label' => 'Disputas', 'icon' => 'las la-gavel'],
                    'system_settings' => ['url' => '/admin/system_settings.php', 'label' => 'Configurações Gerais', 'icon' => 'las la-cog'],
                    'automation_settings' => ['url' => '/admin/automation_settings.php', 'label' => 'Automações', 'icon' => 'las la-robot'],
                    'tax_settings' => ['url' => '/admin/tax_settings.php', 'label' => 'Configuração Fiscal', 'icon' => 'las la-receipt'],
                    'storage_settings' => ['url' => '/admin/storage_settings.php', 'label' => 'Armazenamento', 'icon' => 'las la-hdd'],
                    'currencies' => ['url' => '/admin/currencies.php', 'label' => 'Moedas', 'icon' => 'las la-dollar-sign'],
                    'email_templates' => ['url' => '/admin/email_templates.php', 'label' => 'Modelos de Email', 'icon' => 'las la-envelope'],
                    'admins' => ['url' => '/admin/admins.php', 'label' => 'Administradores', 'icon' => 'las la-user-shield'],
                    'admin_roles' => ['url' => '/admin/admin_roles.php', 'label' => 'Funções Administrativas', 'icon' => 'las la-user-tag'],
                    'banned_ips' => ['url' => '/admin/banned_ips.php', 'label' => 'IPs Banidos', 'icon' => 'las la-ban'],
                    'banned_email_domains' => ['url' => '/admin/banned_email_domains.php', 'label' => 'Domínios de Email Banidos', 'icon' => 'las la-envelope-open'],
                    'security_questions' => ['url' => '/admin/security_questions.php', 'label' => 'Questões de Segurança', 'icon' => 'las la-question-circle'],
                    'reports' => ['url' => '/admin/reports.php', 'label' => 'Relatórios', 'icon' => 'las la-chart-bar'],
                    'daily_performance' => ['url' => '/admin/daily_performance.php', 'label' => 'Performance Diária', 'icon' => 'las la-tachometer-alt'],
                    'annual_report' => ['url' => '/admin/annual_report.php', 'label' => 'Relatório Anual', 'icon' => 'las la-calendar-alt'],
                    'whois_lookup' => ['url' => '/admin/whois_lookup.php', 'label' => 'Pesquisa WHOIS', 'icon' => 'las la-globe'],
                    'calendar' => ['url' => '/admin/calendar.php', 'label' => 'Calendário', 'icon' => 'las la-calendar'],
                    'todos' => ['url' => '/admin/todos.php', 'label' => 'Itens a Fazer', 'icon' => 'las la-tasks'],
                    'staff_notices' => ['url' => '/admin/staff_notices.php', 'label' => 'Quadro de Avisos', 'icon' => 'las la-clipboard-list'],
                    'database_maintenance' => ['url' => '/admin/database_maintenance.php', 'label' => 'Manutenção do Banco', 'icon' => 'las la-database'],
                    'database_backups' => ['url' => '/admin/database_backups.php', 'label' => 'Backups do Banco', 'icon' => 'las la-database'],
                    'license_data' => ['url' => '/admin/license_data.php', 'label' => 'Dados da Licença', 'icon' => 'las la-key'],
                    'my_account' => ['url' => '/admin/my_account.php', 'label' => 'Minha Conta', 'icon' => 'las la-user'],
                    'my_notes' => ['url' => '/admin/my_notes.php', 'label' => 'Minhas Notas', 'icon' => 'las la-sticky-note'],
                    'settings' => ['url' => '/admin/settings_logos.php', 'label' => 'Configurações', 'icon' => 'las la-cog'],
                    'network_incidents' => ['url' => '/admin/network_incidents.php', 'label' => 'Falhas na Rede', 'icon' => 'las la-network-wired'],
                ];
                
                foreach ($items as $item):
                    if (isset($itemMap[$item])):
                        $itemInfo = $itemMap[$item];
                        $searchText = strtolower($category['label'] . ' ' . $itemInfo['label']);
                ?>
                    <a class="admin-nav-link admin-nav-subitem menu-item <?= $active === $item ? 'active' : '' ?>" 
                       href="<?= $itemInfo['url'] ?>" 
                       data-search="<?= h($searchText) ?>"
                       data-category="<?= h($category['label']) ?>">
                        <i class="<?= $itemInfo['icon'] ?> me-2"></i> <?= $itemInfo['label'] ?>
                    </a>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
    </div>
    <?php
}
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
        
        <!-- Barra de Pesquisa -->
        <div class="mb-3">
            <div class="position-relative">
                <input type="text" 
                       id="menuSearch" 
                       class="form-control form-control-sm bg-dark border-secondary text-white" 
                       placeholder="Buscar no menu..." 
                       autocomplete="off"
                       style="padding-left: 36px;">
                <i class="las la-search position-absolute text-white-50" style="left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
            </div>
        </div>
        
        <nav class="nav flex-column gap-1" id="adminNav">
            <!-- Dashboard -->
            <a class="admin-nav-link menu-item <?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin/dashboard.php" data-search="dashboard">
                <i class="las la-tachometer-alt me-2"></i> Dashboard
            </a>

            <?php
            // Renderizar categorias
            foreach ($categories as $catKey => $category):
                $isActive = $isCategoryActive($category['items']);
                renderCategory($catKey, $category, $isActive, $isCategoryActive, $active);
            endforeach;
            ?>
        </nav>

        <hr class="border-white border-opacity-10 my-4">

        <div class="w-100">
            <div class="dropdown">
                <button class="btn btn-link text-white-50 text-decoration-none p-0 w-100 d-flex align-items-center gap-2" type="button" id="adminProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($adminPhoto): ?>
                        <img src="<?= h($adminPhoto) ?>" alt="Foto" class="rounded" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">
                    <?php else: ?>
                        <div class="rounded bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                            <i class="las la-user text-white"></i>
                        </div>
                    <?php endif; ?>
                    <div class="text-start flex-grow-1" style="min-width: 0;">
                        <div class="small text-truncate">Logado como</div>
                        <span class="text-white fw-semibold text-truncate d-block"><?= h($_SESSION['admin_username'] ?? '') ?></span>
                    </div>
                    <i class="las la-angle-down" style="flex-shrink: 0;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100" aria-labelledby="adminProfileDropdown" style="min-width: 200px;">
                    <li><a class="dropdown-item" href="/admin/my_account.php"><i class="las la-user me-2"></i> Minha Conta</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/admin/logout.php"><i class="las la-sign-out-alt me-2"></i> Sair</a></li>
                </ul>
            </div>
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
        <!-- Barra de Pesquisa Mobile -->
        <div class="mb-3">
            <div class="position-relative">
                <input type="text" 
                       id="menuSearchMobile" 
                       class="form-control form-control-sm bg-dark border-secondary text-white" 
                       placeholder="Buscar no menu..." 
                       autocomplete="off"
                       style="padding-left: 36px;">
                <i class="las la-search position-absolute text-white-50" style="left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
            </div>
        </div>
        
        <nav class="nav flex-column gap-1" id="adminNavMobile">
            <!-- Dashboard -->
            <a class="admin-nav-link menu-item <?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin/dashboard.php" data-search="dashboard">
                <i class="las la-tachometer-alt me-2"></i> Dashboard
            </a>

            <?php
            // Renderizar categorias (mobile)
            foreach ($categories as $catKey => $category):
                $isActive = $isCategoryActive($category['items']);
                $categoryId = 'collapse' . ucfirst($catKey) . 'Mobile';
                $items = $category['items'];
            ?>
                <div class="admin-nav-dropdown menu-category" data-category="<?= h($category['label']) ?>">
                    <button class="admin-nav-link admin-nav-toggle w-100 text-start d-flex align-items-center justify-content-between <?= $isActive ? 'active' : '' ?>" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#<?= $categoryId ?>" 
                            aria-expanded="<?= $isActive ? 'true' : 'false' ?>"
                            aria-controls="<?= $categoryId ?>"
                            data-search="<?= h(strtolower($category['label'])) ?>">
                        <span><i class="<?= $category['icon'] ?> me-2"></i> <?= $category['label'] ?></span>
                        <i class="las la-angle-down admin-nav-arrow"></i>
                    </button>
                    <div class="collapse <?= $isActive ? 'show' : '' ?>" id="<?= $categoryId ?>">
                        <div class="admin-nav-submenu ps-3 mt-1">
                            <?php
                            foreach ($items as $item):
                                if (isset($itemMap[$item])):
                                    $itemInfo = $itemMap[$item];
                                    $searchText = strtolower($category['label'] . ' ' . $itemInfo['label']);
                            ?>
                                <a class="admin-nav-link admin-nav-subitem menu-item <?= $active === $item ? 'active' : '' ?>" 
                                   href="<?= $itemInfo['url'] ?>" 
                                   data-search="<?= h($searchText) ?>"
                                   data-category="<?= h($category['label']) ?>">
                                    <i class="<?= $itemInfo['icon'] ?> me-2"></i> <?= $itemInfo['label'] ?>
                                </a>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        <hr class="border-white border-opacity-10 my-4">
        <div class="w-100">
            <div class="dropdown">
                <button class="btn btn-link text-white-50 text-decoration-none p-0 w-100 d-flex align-items-center gap-2" type="button" id="adminProfileDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($adminPhoto): ?>
                        <img src="<?= h($adminPhoto) ?>" alt="Foto" class="rounded" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">
                    <?php else: ?>
                        <div class="rounded bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                            <i class="las la-user text-white"></i>
                        </div>
                    <?php endif; ?>
                    <div class="text-start flex-grow-1" style="min-width: 0;">
                        <div class="small text-truncate">Logado como</div>
                        <span class="text-white fw-semibold text-truncate d-block"><?= h($_SESSION['admin_username'] ?? '') ?></span>
                    </div>
                    <i class="las la-angle-down" style="flex-shrink: 0;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100" aria-labelledby="adminProfileDropdownMobile" style="min-width: 200px;">
                    <li><a class="dropdown-item" href="/admin/my_account.php"><i class="las la-user me-2"></i> Minha Conta</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/admin/logout.php"><i class="las la-sign-out-alt me-2"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Função para filtrar o menu
    function filterMenu(searchInput, navContainer) {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const menuItems = navContainer.querySelectorAll('.menu-item');
        const categories = navContainer.querySelectorAll('.menu-category');
        
        if (searchTerm === '') {
            // Mostrar todos os itens e categorias
            menuItems.forEach(item => {
                item.style.display = '';
                const category = item.closest('.menu-category');
                if (category) {
                    category.style.display = '';
                }
            });
            categories.forEach(cat => {
                cat.style.display = '';
            });
            return;
        }
        
        // Esconder todas as categorias primeiro
        categories.forEach(cat => {
            cat.style.display = 'none';
        });
        
        let hasMatches = false;
        
        // Filtrar itens
        menuItems.forEach(item => {
            const searchText = item.getAttribute('data-search') || '';
            const matches = searchText.includes(searchTerm);
            
            if (matches) {
                item.style.display = '';
                hasMatches = true;
                
                // Mostrar a categoria pai
                const category = item.closest('.menu-category');
                if (category) {
                    category.style.display = '';
                    // Expandir a categoria automaticamente
                    const collapseId = category.querySelector('[data-bs-toggle="collapse"]')?.getAttribute('data-bs-target');
                    if (collapseId) {
                        const collapseElement = category.querySelector(collapseId);
                        if (collapseElement) {
                            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseElement);
                            bsCollapse.show();
                        }
                    }
                }
            } else {
                item.style.display = 'none';
            }
        });
        
        // Filtrar categorias também
        categories.forEach(cat => {
            const categorySearch = cat.getAttribute('data-category')?.toLowerCase() || '';
            if (categorySearch.includes(searchTerm)) {
                cat.style.display = '';
                hasMatches = true;
                // Expandir a categoria
                const collapseId = cat.querySelector('[data-bs-toggle="collapse"]')?.getAttribute('data-bs-target');
                if (collapseId) {
                    const collapseElement = cat.querySelector(collapseId);
                    if (collapseElement) {
                        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseElement);
                        bsCollapse.show();
                    }
                }
            }
        });
        
        // Mostrar mensagem se não houver resultados
        let noResultsMsg = navContainer.querySelector('.no-results-message');
        if (!hasMatches && searchTerm !== '') {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message text-white-50 text-center py-3';
                noResultsMsg.innerHTML = '<i class="las la-search me-2"></i>Nenhum resultado encontrado';
                navContainer.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = '';
        } else if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
    
    // Inicializar busca para desktop
    const searchInputDesktop = document.getElementById('menuSearch');
    const navDesktop = document.getElementById('adminNav');
    if (searchInputDesktop && navDesktop) {
        searchInputDesktop.addEventListener('input', function() {
            filterMenu(searchInputDesktop, navDesktop);
        });
        
        // Limpar busca ao perder foco (opcional)
        searchInputDesktop.addEventListener('blur', function() {
            // Não limpar automaticamente, deixar o usuário ver os resultados
        });
    }
    
    // Inicializar busca para mobile
    const searchInputMobile = document.getElementById('menuSearchMobile');
    const navMobile = document.getElementById('adminNavMobile');
    if (searchInputMobile && navMobile) {
        searchInputMobile.addEventListener('input', function() {
            filterMenu(searchInputMobile, navMobile);
        });
    }
    
    // Limpar busca quando o offcanvas for fechado
    const offcanvas = document.getElementById('adminSidebarOffcanvas');
    if (offcanvas && searchInputMobile) {
        offcanvas.addEventListener('hidden.bs.offcanvas', function() {
            searchInputMobile.value = '';
            if (navMobile) {
                filterMenu(searchInputMobile, navMobile);
            }
        });
    }
})();
</script>
