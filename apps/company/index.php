<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/inc/session.php';

ensureCompanySessionStorage();
ob_start();

require_once __DIR__ . '/inc/db.php';

// Route al — hem /dashboard hem /module/dashboard destekle
$route = $_GET['route'] ?? 'dashboard';
$route = rtrim($route, '/');

// Geriye uyumluluk: module/ prefiksi varsa kaldır
if (strpos($route, 'module/') === 0) {
    $route = substr($route, 7);
}
// admin/ prefiksi varsa koru (admin sayfaları ayrı klasörde)
$isAdmin = (strpos($route, 'admin/') === 0);

$isLoggedIn = isset($_SESSION['appuser']);

// GATEKEEPER
if (!$isLoggedIn && $route !== 'login') {
    header("Location: /login");
    exit;
}

if ($isLoggedIn && $route === 'login') {
    header("Location: /dashboard");
    exit;
}

// Dosya yolu belirle
$baseDir = __DIR__;
if ($isAdmin) {
    $targetFile = $baseDir . '/' . $route . '.php';
} else {
    $targetFile = $baseDir . '/module/' . $route . '.php';
}

$moduleContent = '';
if (file_exists($targetFile)) {
    ob_start();
    include $targetFile;
    $moduleContent = ob_get_clean();
} else {
    $moduleContent = "<div class='card'><h2>404</h2><p>Seite '$route' nicht gefunden.</p></div>";
}

require_once $baseDir . '/inc/header.php';
echo $moduleContent;
require_once $baseDir . '/inc/footer.php';

ob_end_flush();
