<?php
/**
 * admin/schemacurrency.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>💱 Schema Update: Currency</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS currency (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'code'      => "VARCHAR(10) DEFAULT NULL",
        'symbol'    => "VARCHAR(10) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "DATETIME DEFAULT NULL",
        'updatedat' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE currency")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'code')   { $after = " AFTER name"; }
            if ($col === 'symbol') { $after = " AFTER code"; }
            if ($col === 'note')   { $after = " AFTER symbol"; }
            
            $pdo->exec("ALTER TABLE currency ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";