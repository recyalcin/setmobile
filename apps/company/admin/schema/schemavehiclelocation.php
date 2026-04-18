<?php
/**
 * admin/schemavehiclelocation.php
 * Schema für Fahrzeug-Standorte (Sekundengenau)
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Vehicle Location</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehiclelocation (id SERIAL PRIMARY KEY);");

    $columns = [
        'vehicleid'   => "INT DEFAULT NULL",
        'driverid'    => "INT DEFAULT NULL",
        'tripid'      => "INT DEFAULT NULL",
        'datetime'    => "TIMESTAMP DEFAULT NULL", // DATETIME speichert Sekunden
        'lat'         => "DECIMAL(10, 8) DEFAULT NULL",
        'lng'         => "DECIMAL(11, 8) DEFAULT NULL",
        'speed'       => "DECIMAL(5, 2) DEFAULT NULL",
        'heading'     => "INT DEFAULT NULL",
        'accuracy'    => "DECIMAL(5, 2) DEFAULT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "TIMESTAMP DEFAULT NULL",
        'updateddate' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'vehiclelocation'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE vehiclelocation ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";