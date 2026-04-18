<?php
/**
 * admin/schematransactiontype.php
 * Erstellt oder repariert die 'transactiontype' Tabelle
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>⚙️ Schema Check: Transaction Type</h3>";

try {
    // 1. Basis-Tabelle sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactiontype (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Definition der Felder (ohne Unterstriche)
    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "DATETIME DEFAULT NULL",
        'updateddate' => "DATETIME DEFAULT NULL"
    ];

    // 3. Vorhandene Spalten abrufen
    $currentCols = $pdo->query("DESCRIBE transactiontype")->fetchAll(PDO::FETCH_COLUMN);

    // 4. Fehlende Spalten ergänzen
    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE transactiontype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    echo "<p>Check abgeschlossen. Die Typen-Tabelle ist bereit.</p>";
    echo "<br><a href='/module/transactiontype' class='btn save'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";