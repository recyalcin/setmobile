<?php
/**
 * admin/schemaperformance.php - Migration für Performance-Tabelle
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>📊 Schema Update: Performance</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'driverid'                  => "INT DEFAULT 0",
        'week'                      => "VARCHAR(20) DEFAULT NULL",
        'netearningsbolt'           => "DECIMAL(10,2) DEFAULT 0.00",
        'tollfeesbolt'              => "DECIMAL(10,2) DEFAULT 0.00",
        'ridertipsbolt'             => "DECIMAL(10,2) DEFAULT 0.00",
        'collectedcashbolt'         => "DECIMAL(10,2) DEFAULT 0.00",
        'earningsperformancebolt'   => "DECIMAL(10,2) DEFAULT 0.00",
        'finishedridesbolt'         => "INT DEFAULT 0",
        'onlinetimebolt'            => "DECIMAL(10,2) DEFAULT 0.00",
        'totalridedistancebolt'     => "DECIMAL(10,2) DEFAULT 0.00",
        'totalacceptanceratebolt'   => "DECIMAL(10,2) DEFAULT 0.00",
        'netearningsuber'           => "DECIMAL(10,2) DEFAULT 0.00",
        'tollfeesuber'              => "DECIMAL(10,2) DEFAULT 0.00",
        'ridertipsuber'             => "DECIMAL(10,2) DEFAULT 0.00",
        'collectedcashuber'         => "DECIMAL(10,2) DEFAULT 0.00",
        'earningsperformanceuber'   => "DECIMAL(10,2) DEFAULT 0.00",
        'finishedridesuber'         => "INT DEFAULT 0",
        'onlinetimeuber'            => "DECIMAL(10,2) DEFAULT 0.00",
        'totalridedistanceuber'     => "DECIMAL(10,2) DEFAULT 0.00",
        'totalacceptancerateuber'   => "DECIMAL(10,2) DEFAULT 0.00",
        'note'                      => "TEXT DEFAULT NULL",
        'createdat'                 => "DATETIME DEFAULT NULL",
        'updatedat'                 => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE performance")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE performance ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";