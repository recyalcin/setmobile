<?php
/**
 * admin/schemajobtitle.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>💼 Schema Update: Job-Titel</h3>";

try {
    // Tabelle erstellen falls nicht vorhanden
    $pdo->exec("CREATE TABLE IF NOT EXISTS jobtitle (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'name'        => "VARCHAR(255) DEFAULT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "DATETIME DEFAULT NULL",
        'updateddate' => "DATETIME DEFAULT NULL"
    ];

    // Aktuelle Spalten abrufen
    $currentCols = $pdo->query("DESCRIBE jobtitle")->fetchAll(PDO::FETCH_COLUMN);

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