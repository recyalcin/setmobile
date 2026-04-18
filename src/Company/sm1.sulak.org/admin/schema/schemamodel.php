<?php
/**
 * admin/schemamodel.php
 * Erstellt/Repariert die Tabelle für Fahrzeugmodelle
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚘 Schema Check: Model (Fahrzeugmodelle)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS model (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'makeid'      => "INT DEFAULT NULL",
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "DATETIME DEFAULT NULL",
        'updateddate' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE model")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE model ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Tabelle 'model' ist bereit.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";