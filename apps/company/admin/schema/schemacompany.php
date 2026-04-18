<?php
/**
 * admin/schemacompany.php
 * Erstellt oder repariert die zentrale Unternehmens-Tabelle
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🏢 Schema Update: Company</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS company (id SERIAL PRIMARY KEY);");

    $columns = [
        'companytypeid'      => "INT DEFAULT NULL",
        'companylegalformid' => "INT DEFAULT NULL",
        'name'               => "VARCHAR(255) DEFAULT NULL",
        'street'             => "VARCHAR(255) DEFAULT NULL",
        'housenr'            => "VARCHAR(20) DEFAULT NULL",
        'pobox'              => "VARCHAR(50) DEFAULT NULL",
        'city'               => "VARCHAR(100) DEFAULT NULL",
        'countryid'          => "INT DEFAULT NULL",
        'phone'              => "VARCHAR(50) DEFAULT NULL",
        'email'              => "VARCHAR(255) DEFAULT NULL",
        'website'            => "VARCHAR(255) DEFAULT NULL",
        'vatid'              => "VARCHAR(50) DEFAULT NULL",
        'taxid'              => "VARCHAR(50) DEFAULT NULL",
        'bankname'           => "VARCHAR(255) DEFAULT NULL",
        'iban'               => "VARCHAR(50) DEFAULT NULL",
        'bic'                => "VARCHAR(20) DEFAULT NULL",
        'note'               => "TEXT DEFAULT NULL",
        'createdat'          => "TIMESTAMP DEFAULT NULL",
        'updatedat'          => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'company'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE company ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
    echo "<br><a href='/?route=module/company' class='btn save' style='text-decoration:none; padding:10px 20px; background:#10b981; color:white; border-radius:4px;'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";