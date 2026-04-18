<?php
/**
 * router.php — PHP built-in server router
 * Translates /module/xxx and /admin/xxx paths to ?route= params
 * Usage: php -S localhost:8080 -t apps/company apps/company/router.php
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ltrim($uri, '/');

// Serve static files directly (css, js, images, fonts)
if ($path !== '' && file_exists(__DIR__ . '/' . $path) && !is_dir(__DIR__ . '/' . $path)) {
    return false;
}

// /login  → module/login
if ($path === 'login') {
    $_GET['route'] = 'module/login';
    require __DIR__ . '/index.php';
    return;
}

// /module/xxx  or  /admin/xxx  → route=module/xxx or route=admin/xxx
if (preg_match('#^(module|admin)/([a-zA-Z0-9_/-]+)$#', $path, $m)) {
    $_GET['route'] = $m[1] . '/' . $m[2];
    require __DIR__ . '/index.php';
    return;
}

// inc/logout.php  → serve directly
if ($path === 'inc/logout.php' && file_exists(__DIR__ . '/inc/logout.php')) {
    require __DIR__ . '/inc/logout.php';
    return;
}

// Everything else → index.php
require __DIR__ . '/index.php';
