<?php
/**
 * admin/schemadriveractivity.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Driver Activity (+ Odometer)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS driveractivity (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'driveractivitytypeid' => "INT DEFAULT NULL",
        'driverid'             => "INT DEFAULT NULL",
        'vehicleid'            => "INT DEFAULT NULL",
        'tripid'               => "INT DEFAULT NULL",
        'datetime'             => "DATETIME DEFAULT NULL",
        'lat'                  => "DECIMAL(10, 8) DEFAULT NULL",
        'lng'                  => "DECIMAL(11, 8) DEFAULT NULL",
        'odometer'             => "INT DEFAULT NULL", // NEU: Hinter lng
        'note'                 => "TEXT DEFAULT NULL",
        'createddate'          => "DATETIME DEFAULT NULL",
        'updateddate'          => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE driveractivity")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            // Spezifische Platzierung für odometer, falls gewünscht
            $after = ($col == 'odometer') ? " AFTER lng" : "";
            $pdo->exec("ALTER TABLE driveractivity ADD $col $type $after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";