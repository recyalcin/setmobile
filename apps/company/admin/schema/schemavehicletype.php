<?php
/**
 * admin/schemavehicletype.php - Migration für Fahrzeugtypen
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚗 Schema Update: Vehicle Type</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicletype (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'vehicletype'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE vehicletype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";