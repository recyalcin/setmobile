<?php
/**
 * admin/schemacolor.php
 * Erstellt/Repariert die Tabelle für Fahrzeugfarben
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🎨 Schema Check: Color (Farben)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS color (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "DATETIME DEFAULT NULL",
        'updateddate' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE color")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE color ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Tabelle 'color' ist bereit.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";