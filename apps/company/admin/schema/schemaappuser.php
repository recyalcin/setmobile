<?php
/**
 * admin/schemaappuser.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👤 Schema Update: App-User & Roles</h3>";

try {
    // 1. Rollen-Tabelle (falls nicht vorhanden)
    $pdo->exec("CREATE TABLE IF NOT EXISTS appuserrole (
        id SERIAL PRIMARY KEY,
        rolename VARCHAR(50) UNIQUE NOT NULL
    );");

    // Standard-Rollen einfügen, falls leer
    $count = $pdo->query("SELECT count(*) FROM appuserrole")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO appuserrole (rolename) VALUES ('Admin'), ('Manager'), ('User')");
    }

    // 2. Appuser-Tabelle
    $pdo->exec("CREATE TABLE IF NOT EXISTS appuser (id SERIAL PRIMARY KEY);");

    $columns = [
        'username'      => "VARCHAR(100) UNIQUE NOT NULL",
        'password'      => "VARCHAR(255) NOT NULL",
        'personid'      => "INT DEFAULT NULL",
        'appuserroleid' => "INT DEFAULT NULL", // Geändert von role -> appuserroleid
        'active'        => "SMALLINT DEFAULT 1",
        'remembertoken' => "VARCHAR(100) DEFAULT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createdat'     => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'updatedat'     => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'appuser'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE appuser ADD COLUMN $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    
    echo "<p>Schema-Check abgeschlossen.</p>";

} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}

echo "</div>";