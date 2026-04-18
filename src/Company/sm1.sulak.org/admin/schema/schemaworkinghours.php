<?php
/**
 * admin/schemaworkinghours.php - Schema für Arbeitszeiten (Update: tripat Felder)
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🕒 Schema Update: Arbeitszeiten</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS workinghours (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'employeeid'    => "INT DEFAULT NULL",
        'date'          => "DATE DEFAULT NULL",
        'firsttripat'   => "DATETIME DEFAULT NULL", // Neu: Erster Trip
        'lasttripat'    => "DATETIME DEFAULT NULL",  // Neu: Letzter Trip
        'workstartat'   => "DATETIME DEFAULT NULL",
        'workendat'     => "DATETIME DEFAULT NULL",
        'breakduration' => "VARCHAR(50) DEFAULT NULL",
        'hours0004'     => "DECIMAL(10,2) DEFAULT NULL",
        'hours2006'     => "DECIMAL(10,2) DEFAULT NULL",
        'hourstotal'    => "DECIMAL(10,2) DEFAULT NULL",
        'recordedat'    => "DATETIME DEFAULT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createdat'     => "DATETIME DEFAULT NULL",
        'updatedat'     => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE workinghours")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'date')          { $after = " AFTER employeeid"; }
            if ($col === 'firsttripat')   { $after = " AFTER date"; }
            if ($col === 'lasttripat')    { $after = " AFTER firsttripat"; }
            if ($col === 'workstartat')   { $after = " AFTER lasttripat"; }
            if ($col === 'recordedat')    { $after = " AFTER hourstotal"; }
            
            $pdo->exec("ALTER TABLE workinghours ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";