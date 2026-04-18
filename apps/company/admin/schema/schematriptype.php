<?php
/**
 * admin/schematriptype.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Trip Type</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS triptype (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'      => "VARCHAR(100) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'triptype'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE triptype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check für 'triptype' abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";