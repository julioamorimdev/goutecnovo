<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$page_title = 'Configurações';
$active = '';
require_once __DIR__ . '/partials/layout_start.php';

header('Location: /admin/settings_logos.php');
exit;


