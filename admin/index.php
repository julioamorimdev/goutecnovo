<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../app/bootstrap.php';

if (is_admin_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

header('Location: /admin/login.php');
exit;


