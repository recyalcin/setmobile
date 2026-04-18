<?php
/**
 * admin/schemavehicleservice.php
 * Erstellt oder repariert die Tabelle für Fahrzeug-Services
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🔧 Schema Update: Vehicle Service</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicleservice (id SERIAL PRIMARY KEY);");

    $columns = [
        'vehicleservicetypeid' => "INT DEFAULT NULL",
        'vehicleid'            => "INT DEFAULT NULL",
        'companyid'            => "INT DEFAULT NULL",
        'vehicleservicetaskid' => "INT DEFAULT NULL",
        'datetime'             => "TIMESTAMP DEFAULT NULL",
        'description'          => "TEXT DEFAULT NULL",
        'odometer'             => "INT DEFAULT NULL",
        'totalamount'          => "DECIMAL(10,2) DEFAULT NULL",
        'invoicenumber'        => "VARCHAR(50) DEFAULT NULL",
        'nextserviceat'        => "DATE DEFAULT NULL",
        'nextserviceodometer'  => "INT DEFAULT NULL",
        'note'                 => "TEXT DEFAULT NULL",
        'createdat'            => "TIMESTAMP DEFAULT NULL",
        'updatedat'            => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'vehicleservice'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE vehicleservice ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
    echo "<br><a href='/vehicleservice' class='btn save' style='text-decoration:none; padding:10px 20px; background:#10b981; color:white; border-radius:4px;'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";