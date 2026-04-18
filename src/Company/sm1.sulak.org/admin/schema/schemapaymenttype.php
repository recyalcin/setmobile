<?php
/**
 * admin/schemapaymenttype.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Payment Type</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS paymenttype (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'name'      => "VARCHAR(100) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "DATETIME DEFAULT NULL",
        'updatedat' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE paymenttype")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE paymenttype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check für 'paymenttype' abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";