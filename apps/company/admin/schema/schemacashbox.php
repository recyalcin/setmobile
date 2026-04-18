<?php
/**
 * admin/schemacashbox.php
 * Erstellt oder repariert die 'cashbox' Tabelle
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🪙 Schema Check: Cashbox</h3>";

try {
    // Basis-Tabelle sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashbox (id SERIAL PRIMARY KEY);");

    // Felder definieren
    $columns = [
        'cashboxtypeid' => "INT DEFAULT NULL",
        'name'          => "VARCHAR(100) NOT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createddate'   => "TIMESTAMP DEFAULT NULL",
        'updateddate'   => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'cashbox'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE cashbox ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    echo "<p>Check abgeschlossen. Die Kassen-Tabelle ist auf dem neuesten Stand.</p>";
    echo "<br><a href='/module/cashbox' class='btn save'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";