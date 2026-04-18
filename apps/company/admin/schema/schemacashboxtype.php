<?php
/**
 * admin/schemacashboxtype.php
 * Erstellt oder repariert die Tabelle für Kassentypen
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>⚙️ Schema Check: Cashbox Type</h3>";

try {
    // 1. Basis-Tabelle sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashboxtype (id SERIAL PRIMARY KEY);");

    // 2. Felder definieren (Strikt nach Vorgabe, keine Unterstriche)
    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "TIMESTAMP DEFAULT NULL",
        'updateddate' => "TIMESTAMP DEFAULT NULL"
    ];

    // 3. Vorhandene Spalten abrufen
    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'cashboxtype'")->fetchAll(PDO::FETCH_COLUMN);

    // 4. Fehlende Spalten ergänzen
    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE cashboxtype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    echo "<p>Check abgeschlossen. Die Typen-Tabelle ist bereit für den Einsatz.</p>";
    echo "<br><a href='/cashboxtype' class='btn save'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";