<?php
/**
 * admin/schematripsource.php - Schema für Trip-Quellen
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: TripSource</h3>";

try {
    // Tabelle erstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS tripsource (id SERIAL PRIMARY KEY);");

    // Definition der Spalten
    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    // Aktuelle Spalten abrufen
    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tripsource'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE tripsource ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";