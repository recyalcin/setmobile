<?php
/**
 * module/login.php
 * Authentifizierungs-Modul
 */

// Sicherheitscheck: Verhindert den direkten Aufruf der Datei
if (!isset($pdo)) { 
    die("Direkter Zugriff verweigert. Bitte nutzen Sie die Hauptseite."); 
}

$error = '';

// 1. Automatischer Login über Cookie (Remember Token)
if (!isset($_SESSION['userid']) && isset($_COOKIE['rememberme'])) {
    try {
        $token = $_COOKIE['rememberme'];
        $stmt = $pdo->prepare("SELECT * FROM appuser WHERE remembertoken = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['appuser']   = $user['username'];
            $_SESSION['userid'] = $user['id'];
            header("Location: /dashboard");
            exit;
        }
    } catch (Exception $e) {
        // remembertoken column may not exist yet — skip
    }
}

// 2. Login-Logik verarbeiten
if (isset($_POST['dologin'])) {
    $userIn = $_POST['username'] ?? '';
    $passIn = $_POST['password'] ?? '';
    $keepLoggedIn = isset($_POST['rememberme']);

    // User in der Datenbank suchen
    $stmt = $pdo->prepare("SELECT * FROM appuser WHERE username = ? LIMIT 1");
    $stmt->execute([$userIn]);
    $user = $stmt->fetch();

    // Passwort-Vergleich
    if ($user && password_verify($passIn, $user['password'])) {
        // Session-Daten setzen
        $_SESSION['appuser']   = $user['username'];
        $_SESSION['userid'] = $user['id'];

        // Token generieren, wenn "Angemeldet bleiben" gewählt wurde
        if ($keepLoggedIn) {
            try {
                $newToken = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE appuser SET remembertoken = ? WHERE id = ?");
                $stmt->execute([$newToken, $user['id']]);
                setcookie('rememberme', $newToken, time() + (30 * 24 * 60 * 60), "/", "", false, true);
            } catch (Exception $e) {
                // remembertoken column may not exist yet — skip silently
            }
        }
        
        // Weiterleitung
        header("Location: /dashboard");
        exit;
    } else {
        $error = 'Die Zugangsdaten sind nicht korrekt.';
    }
}
?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
    
    <div class="card" style="width: 100%; max-width: 400px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #2ecc71; margin: 0; font-size: 28px;">SM1 SYSTEM</h1>
            <p style="color: #777; margin-top: 5px;">Bitte melden Sie sich an</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login">
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px; font-size:14px; color:#555;">Benutzername</label>
                <input type="text" name="username" style="margin:0; width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;" required autofocus>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px; font-size:14px; color:#555;">Passwort</label>
                <input type="password" name="password" style="margin:0; width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;" required>
            </div>

            <div style="margin-bottom: 25px; display: flex; align-items: center;">
                <input type="checkbox" name="rememberme" id="rememberme" style="width:auto; margin-right:10px; cursor:pointer;">
                <label for="rememberme" style="font-size:14px; color:#555; cursor:pointer;">30 Tage angemeldet bleiben</label>
            </div>

            <button type="submit" name="dologin" class="btn save" style="width: 100%; padding: 14px; font-size: 16px; background:#2ecc71; color:white; border:none; border-radius:4px; cursor:pointer;">
                Anmelden
            </button>
            
        </form>

        <div style="text-align: center; margin-top: 25px;">
            <a href="#" style="color: #999; text-decoration: none; font-size: 12px;">Passwort vergessen?</a>
        </div>

    </div>
</div>

<style>
.alert {
    padding: 12px;
    border-radius: 6px;
    text-align: center;
    font-size: 14px;
}
.alert.error {
    background: #fff5f5;
    color: #c53030;
    border: 1px solid #feb2b2;
}
</style>