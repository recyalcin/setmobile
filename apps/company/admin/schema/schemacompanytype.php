<?php
/**
 * admin/schemacompanytype.php
 * Erstellt oder repariert die Tabelle für Unternehmenstypen
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🏢 Schema Update: Company Type</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS companytype (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'companytype'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE companytype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
    echo "<br><a href='/?route=module/companytype' class='btn save' style='text-decoration:none; padding:10px 20px; background:#10b981; color:white; border-radius:4px;'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";