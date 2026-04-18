<?php
/**
 * admin/schemacurrency.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>💱 Schema Update: Currency</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS currency (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'code'      => "VARCHAR(10) DEFAULT NULL",
        'symbol'    => "VARCHAR(10) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'currency'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'code')   { $after = ""; }
            if ($col === 'symbol') { $after = ""; }
            if ($col === 'note')   { $after = ""; }
            
            $pdo->exec("ALTER TABLE currency ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";