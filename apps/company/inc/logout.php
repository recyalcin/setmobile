<?php
/**
 * inc/logout.php
 * Beendet die Sitzung sicher und entfernt den Remember-Me-Token.
 */

// Falls die Session noch nicht gestartet wurde
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// db.php eğer $pdo yoksa yükle
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

// 1. Remember-Me-Token in der Datenbank löschen (falls eingeloggt)
if (isset($_SESSION['userid']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("UPDATE appuser SET remembertoken = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['userid']]);
    } catch (Exception $e) {
        // ignore if column doesn't exist
    }
}

// 2. Den Remember-Me-Cookie im Browser löschen
if (isset($_COOKIE['rememberme'])) {
    setcookie('rememberme', '', time() - 3600, "/");
}

// 3. Alle Session-Variablen löschen
$_SESSION = [];

// 4. Das Session-Cookie im Browser löschen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Session auf dem Server zerstören
session_destroy();

// Zurück zur Login-Seite
header("Location: /login?msg=loggedout");
exit;