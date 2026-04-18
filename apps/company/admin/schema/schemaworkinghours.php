<?php
/**
 * admin/schemaworkinghours.php - Schema für Arbeitszeiten (Update: tripat Felder)
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🕒 Schema Update: Arbeitszeiten</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS workinghours (id SERIAL PRIMARY KEY);");

    $columns = [
        'employeeid'    => "INT DEFAULT NULL",
        'date'          => "DATE DEFAULT NULL",
        'firsttripat'   => "TIMESTAMP DEFAULT NULL", // Neu: Erster Trip
        'lasttripat'    => "TIMESTAMP DEFAULT NULL",  // Neu: Letzter Trip
        'workstartat'   => "TIMESTAMP DEFAULT NULL",
        'workendat'     => "TIMESTAMP DEFAULT NULL",
        'breakduration' => "VARCHAR(50) DEFAULT NULL",
        'startkm'       => "DECIMAL(10,2) DEFAULT NULL",
        'endkm'         => "DECIMAL(10,2) DEFAULT NULL",
        'hourstotal'    => "DECIMAL(10,2) DEFAULT NULL",
        'recordedat'    => "TIMESTAMP DEFAULT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createdat'     => "TIMESTAMP DEFAULT NULL",
        'updatedat'     => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'workinghours'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'date')          { $after = ""; }
            if ($col === 'firsttripat')   { $after = ""; }
            if ($col === 'lasttripat')    { $after = ""; }
            if ($col === 'workstartat')   { $after = ""; }
            if ($col === 'recordedat')    { $after = ""; }
            
            $pdo->exec("ALTER TABLE workinghours ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";