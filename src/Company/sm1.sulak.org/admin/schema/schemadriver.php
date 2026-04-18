<?php
/**
 * admin/schemadriver.php
 * Schema-Setup für das Fahrer-Modul
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🪪 Schema Update: Driver (Fahrer)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS driver (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'drivertypeid'              => "INT DEFAULT NULL",
        'personid'                  => "INT DEFAULT NULL",
        'licensecategoryid'         => "INT DEFAULT NULL",
        'licenseissuedate'          => "DATE DEFAULT NULL",
        'licenseexpirydate'         => "DATE DEFAULT NULL",
        'pendorsementissuedate'     => "DATE DEFAULT NULL",
        'pendorsementexpirydate'    => "DATE DEFAULT NULL",
        'note'                      => "TEXT DEFAULT NULL",
        'createdat'                 => "DATETIME DEFAULT NULL",
        'updatedat'                 => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE driver")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE driver ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    
    // Hilfstabellen prüfen (Optional, falls noch nicht vorhanden)
    $pdo->exec("CREATE TABLE IF NOT EXISTS drivertype (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS licensecategory (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB;");

    echo "<p>Datenbank-Check für Fahrer abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";