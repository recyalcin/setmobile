<?php
/**
 * admin/schemaevent.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>📅 Schema Update: Event</h3>";

try {
    // Tabelle erstellen falls nicht vorhanden
    $pdo->exec("CREATE TABLE IF NOT EXISTS event (id SERIAL PRIMARY KEY);");

    // Definition der gewünschten Felder
    $columns = [
        'eventtypeid'      => "INT DEFAULT NULL",
        'name'             => "VARCHAR(255) DEFAULT NULL",
        'date'             => "DATE DEFAULT NULL",
        'timefrom'         => "TIME DEFAULT NULL",
        'timeto'           => "TIME DEFAULT NULL",
        'locationname'     => "VARCHAR(255) DEFAULT NULL",
        'locationmapslink' => "TEXT DEFAULT NULL",
        'note'             => "TEXT DEFAULT NULL",
        'createddate'      => "TIMESTAMP DEFAULT NULL",
        'updateddate'      => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'event'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE event ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check für Events abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";