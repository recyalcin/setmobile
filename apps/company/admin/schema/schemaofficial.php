<?php
/**
 * admin/schemaofficialtype.php
 * Erstellt oder repariert die Tabelle für die Buchungs-Art (Offiziell/Intern)
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>📊 Schema Check: Official Type</h3>";

try {
    // 1. Basis-Tabelle sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS officialtype (id SERIAL PRIMARY KEY);");

    // 2. Felder definieren (Keine Unterstriche)
    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "TIMESTAMP DEFAULT NULL",
        'updateddate' => "TIMESTAMP DEFAULT NULL"
    ];

    // 3. Vorhandene Spalten abrufen
    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'officialtype'")->fetchAll(PDO::FETCH_COLUMN);

    // 4. Fehlende Spalten ergänzen
    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE officialtype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    // Initial-Daten einfügen, falls die Tabelle leer ist
    $count = $pdo->query("SELECT COUNT(*) FROM officialtype")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO officialtype (name, createddate) VALUES ('Offiziell', NOW()), ('Intern / Nicht Offiziell', NOW())");
        echo "<p style='color:blue;'>ℹ️ Standard-Werte (Offiziell/Intern) wurden angelegt.</p>";
    }

    echo "<p>Check abgeschlossen. Die Tabelle 'officialtype' ist bereit.</p>";
    echo "<br><a href='/officialtype' class='btn save'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";