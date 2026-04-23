<?php
// auth.php
require_once __DIR__ . '/session.php';

ensureCompanySessionStorage();

// Pfad-Sicherheit: Verhindere direkten Aufruf von .php Dateien in Unterordnern
if (count(get_included_files()) <= 1) {
    header("HTTP/1.1 403 Forbidden");
    die("Direktzugriff verweigert.");
}

$route = $_GET['route'] ?? '';
$route = str_replace('.php', '', $route);

// Login-Check
if (!isset($_SESSION['userid']) && $route !== 'login' && $route !== 'module/login') {
    header("location: /login");
    exit;
}
