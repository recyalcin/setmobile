<?php
/**
 * admin/schematrip.php - Update: createdat / updatedat
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Trip</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS trip (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'triptypeid'                => "INT DEFAULT NULL",
        'tripstatusid'              => "INT DEFAULT NULL",
        'tripsourceid'              => "INT DEFAULT NULL",
        'vehicleid'                 => "INT DEFAULT NULL",
        'driverid'                  => "INT DEFAULT NULL",
        'submittedat'               => "DATETIME DEFAULT NULL",
        'transmittedat'             => "DATETIME DEFAULT NULL",
        'respondedat'               => "DATETIME DEFAULT NULL",
        'pickedupat'                => "DATETIME DEFAULT NULL",
        'arrivedat'                 => "DATETIME DEFAULT NULL",
        'latvehiclelocationatorder' => "DECIMAL(10, 8) DEFAULT NULL",
        'lngvehiclelocationatorder' => "DECIMAL(11, 8) DEFAULT NULL",
        'latpickuplocation'         => "DECIMAL(10, 8) DEFAULT NULL",
        'lngpickuplocation'         => "DECIMAL(11, 8) DEFAULT NULL",
        'latdropofflocation'        => "DECIMAL(10, 8) DEFAULT NULL",
        'lngdropofflocation'        => "DECIMAL(11, 8) DEFAULT NULL",
        'pickupdistance'            => "DECIMAL(10, 2) DEFAULT NULL",
        'tripdistance'              => "DECIMAL(10, 2) DEFAULT NULL",
        'fare'                      => "DECIMAL(10, 2) DEFAULT NULL",
        'paymenttypeid'             => "INT DEFAULT NULL",
        'note'                      => "TEXT DEFAULT NULL",
        'createdat'                 => "DATETIME DEFAULT NULL",
        'updatedat'                 => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE trip")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE trip ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; }
echo "</div>";