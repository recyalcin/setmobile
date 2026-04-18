<?php
/**
 * admin/schemavehicle.php
 * Erstellt oder repariert die zentrale Fahrzeug-Tabelle
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚗 Schema Update: Vehicle</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'vehicletypeid' => "INT DEFAULT NULL",
        'makeid'        => "INT DEFAULT NULL",
        'modelid'       => "INT DEFAULT NULL",
        'colorid'       => "INT DEFAULT NULL",
        'licenseplate'  => "VARCHAR(50) DEFAULT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createdat'     => "DATETIME DEFAULT NULL",
        'updatedat'     => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE vehicle")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE vehicle ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
    echo "<br><a href='/?route=module/vehicle' class='btn save' style='text-decoration:none; padding:10px 20px; background:#10b981; color:white; border-radius:4px;'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";