<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$active = $active ?? '';

// Função para verificar se uma categoria está ativa
$isCategoryActive = function(array $items) use ($active): bool {
    return in_array($active, $items);
};

// Definir categorias e seus itens
$categories = [
    'conteudo' => [
        'label' => 'Conteúdo do Site',
        'icon' => 'las la-file-alt',
        'items' => ['menu', 'footer', 'blog', 'feedback']
    ],
    'vendas' => [
        'label' => 'Vendas e Comercial',
        'icon' => 'las la-shopping-bag',
        'items' => ['plans', 'clients', 'orders', 'invoices']
    ],
    'suporte' => [
        'label' => 'Suporte',
        'icon' => 'las la-headset',
        'items' => ['tickets']
    ],
    'sistema' => [
        'label' => 'Sistema',
        'icon' => 'las la-cog',
        'items' => ['admins', 'settings']
    ]
];
?>

<!-- Sidebar desktop -->
<aside class="admin-sidebar bg-dark text-white d-none d-md-block">
    <div class="admin-sidebar__brand border-bottom border-white border-opacity-10">
        <a href="/admin/dashboard.php" class="text-decoration-none d-flex align-items-center gap-3">
            <img src="/assets/img/logo-light.png" alt="GouTec" style="height: 28px;">
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
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'clients' ? 'active' : '' ?>" href="/admin/clients.php">
                            <i class="las la-users me-2"></i> Clientes
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'orders' ? 'active' : '' ?>" href="/admin/orders.php">
                            <i class="las la-shopping-cart me-2"></i> Pedidos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'invoices' ? 'active' : '' ?>" href="/admin/invoices.php">
                            <i class="las la-file-invoice me-2"></i> Faturas
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
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tickets' ? 'active' : '' ?>" href="/admin/tickets.php">
                            <i class="las la-headset me-2"></i> Tickets
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
            <img src="/assets/img/logo-light.png" alt="GouTec" style="height: 28px;">
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
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'clients' ? 'active' : '' ?>" href="/admin/clients.php">
                            <i class="las la-users me-2"></i> Clientes
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'orders' ? 'active' : '' ?>" href="/admin/orders.php">
                            <i class="las la-shopping-cart me-2"></i> Pedidos
                        </a>
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'invoices' ? 'active' : '' ?>" href="/admin/invoices.php">
                            <i class="las la-file-invoice me-2"></i> Faturas
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
                        <a class="admin-nav-link admin-nav-subitem <?= $active === 'tickets' ? 'active' : '' ?>" href="/admin/tickets.php">
                            <i class="las la-headset me-2"></i> Tickets
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


