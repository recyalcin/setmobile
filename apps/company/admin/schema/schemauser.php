<?php
/**
 * admin/schemauser.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👤 Schema Update: User</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS appuser (id SERIAL PRIMARY KEY);");

    $columns = [
        'personid'  => "INT DEFAULT NULL",
        'username'  => "VARCHAR(100) DEFAULT NULL",
        'password'  => "VARCHAR(255) DEFAULT NULL",
        'active'    => "SMALLINT DEFAULT 1",
        'note'      => "TEXT DEFAULT NULL", // Neu hinzugefügt
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            
            if ($col === 'personid')  { $after = ""; }
            if ($col === 'username')  { $after = ""; }
            if ($col === 'active')    { $after = ""; }
            if ($col === 'note')      { $after = ""; } // Positionierung hinter active
            if ($col === 'updatedat') { $after = ""; }
            
            $pdo->exec("ALTER TABLE user ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}

echo "</div>";