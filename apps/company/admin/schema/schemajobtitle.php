<?php
/**
 * admin/schemajobtitle.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>💼 Schema Update: Job-Titel</h3>";

try {
    // Tabelle erstellen falls nicht vorhanden
    $pdo->exec("CREATE TABLE IF NOT EXISTS jobtitle (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'        => "VARCHAR(255) DEFAULT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "TIMESTAMP DEFAULT NULL",
        'updateddate' => "TIMESTAMP DEFAULT NULL"
    ];

    // Aktuelle Spalten abrufen
    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'jobtitle'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE jobtitle ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check für Job-Titel (jobtitle) abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";