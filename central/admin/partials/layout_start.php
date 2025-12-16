<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../../app/bootstrap.php';
require_admin();

$page_title = $page_title ?? 'Admin';
$active = $active ?? '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/main.css">
    <style>
        .admin-shell { min-height: 100vh; display: flex; }
        .admin-sidebar { width: 280px; flex: 0 0 280px; }
        .admin-sidebar__brand { padding: 18px 16px; }
        .admin-content { flex: 1; min-width: 0; }
        .admin-topbar { background: #fff; border-bottom: 1px solid rgba(15, 23, 42, .08); }
        .admin-nav-link { color: rgba(255,255,255,.85); padding: 10px 12px; border-radius: 10px; display:block; text-decoration:none; }
        .admin-nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .admin-nav-link.active { background: rgba(13,110,253,.22); color: #fff; }
        .admin-nav-dropdown { margin-bottom: 4px; }
        .admin-nav-toggle { border: none; background: none; cursor: pointer; }
        .admin-nav-toggle:focus { box-shadow: none; }
        .admin-nav-arrow { transition: transform 0.3s ease; font-size: 0.875rem; }
        .admin-nav-toggle[aria-expanded="true"] .admin-nav-arrow { transform: rotate(180deg); }
        .admin-nav-submenu { display: flex; flex-direction: column; gap: 4px; }
        .admin-nav-subitem { padding-left: 24px; font-size: 0.9rem; }
        .admin-nav-subitem i { font-size: 0.85rem; }
        .admin-page { padding: 16px; }
        @media (min-width: 768px) { .admin-page { padding: 22px; } }
        @media (max-width: 992px) { .admin-sidebar { width: 240px; flex-basis: 240px; } }
        @media (max-width: 768px) { .admin-shell { display:block; } }
    </style>
</head>
<body class="bg-secondary">
<div class="admin-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-topbar px-3 px-md-4 py-3 d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="fw-semibold"><?= h($page_title) ?></div>
                <div class="small text-body-secondary">GouTec • Painel administrativo</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-dark d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarOffcanvas" aria-controls="adminSidebarOffcanvas">
                    <i class="las la-bars"></i>
                </button>
                <a class="btn btn-sm btn-outline-dark" href="/" target="_blank" rel="noopener noreferrer">
                    <i class="las la-external-link-alt me-1"></i> Ver site
                </a>
            </div>
        </div>
        <div class="admin-page">


