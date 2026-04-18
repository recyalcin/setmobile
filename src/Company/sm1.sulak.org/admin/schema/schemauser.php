<?php
/**
 * admin/schemauser.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👤 Schema Update: User</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'personid'  => "INT DEFAULT NULL",
        'username'  => "VARCHAR(100) DEFAULT NULL",
        'password'  => "VARCHAR(255) DEFAULT NULL",
        'active'    => "TINYINT(1) DEFAULT 1",
        'note'      => "TEXT DEFAULT NULL", // Neu hinzugefügt
        'createdat' => "DATETIME DEFAULT NULL",
        'updatedat' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE user")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            
            if ($col === 'personid')  { $after = " AFTER id"; }
            if ($col === 'username')  { $after = " AFTER personid"; }
            if ($col === 'active')    { $after = " AFTER password"; }
            if ($col === 'note')      { $after = " AFTER active"; } // Positionierung hinter active
            if ($col === 'updatedat') { $after = " AFTER createdat"; }
            
            $pdo->exec("ALTER TABLE user ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}

echo "</div>";