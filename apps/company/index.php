<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// index.php
session_start();
ob_start(); 

require_once __DIR__ . '/inc/db.php';

// Route ohne .php aus der URL holen
$route = $_GET['route'] ?? 'module/dashboard';
$route = rtrim($route, '/');

$isLoggedIn = isset($_SESSION['appuser']);

// GATEKEEPER (Pfade jetzt ohne .php)
if (!$isLoggedIn && $route !== 'module/login') {
    header("Location: /module/login");
    exit;
}

if ($isLoggedIn && $route === 'module/login') {
    header("Location: /module/dashboard");
    exit;
}

// INTERN: Hier wird das .php für das Dateisystem angehängt
$baseDir    = __DIR__;
$targetFile = $baseDir . '/' . $route . '.php';

$moduleContent = '';
if (file_exists($targetFile)) {
    ob_start();
    include $targetFile;
    $moduleContent = ob_get_clean();
} else {
    $moduleContent = "<div class='card'><h2>404</h2><p>Seite '$route' nicht gefunden. ($targetFile)</p></div>";
}

require_once $baseDir . '/inc/header.php';
echo $moduleContent;
require_once $baseDir . '/inc/footer.php';

ob_end_flush();