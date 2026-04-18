<?php
// module/dashboard.php
if (!isset($pdo)) { die("Kein direkter Zugriff."); }
?>

<div class="card">
    <h1>Willkommen im SM1 System</h1>
    <p>Hallo <strong><?= htmlspecialchars((string)$_SESSION['user']) ?></strong>, du bist erfolgreich angemeldet.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 5px solid #28a745;">
            <h3 style="margin:0;">Personen</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0;">
                <?= $pdo->query("SELECT count(*) FROM person")->fetchColumn() ?>
            </p>
            <a href="/person" style="font-size: 13px; color: #28a745;">Verwalten →</a>
        </div>

        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 5px solid #007bff;">
            <h3 style="margin:0;">Mitarbeiter</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0;">
                <?= $pdo->query("SELECT count(*) FROM employee")->fetchColumn() ?>
            </p>
            <a href="/employee" style="font-size: 13px; color: #007bff;">Details →</a>
        </div>
    </div>
</div>