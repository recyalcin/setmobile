<?php
/**
 * admin/schemabankaccount.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🏦 Schema Update: Bank Account (Person Link)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bankaccount (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'bankaccounttypeid' => "INT DEFAULT NULL",
        'name'              => "VARCHAR(255) DEFAULT NULL",
        'bankname'          => "VARCHAR(255) DEFAULT NULL",
        'iban'              => "VARCHAR(255) DEFAULT NULL",
        'bic'               => "VARCHAR(255) DEFAULT NULL",
        'personid'          => "INT DEFAULT NULL", // Ersetzt owner String durch personid
        'currencyid'        => "INT DEFAULT NULL",
        'isactive'          => "TINYINT(1) DEFAULT 1",
        'note'              => "TEXT DEFAULT NULL",
        'createdat'         => "DATETIME DEFAULT NULL",
        'updatedat'         => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE bankaccount")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'name')       { $after = " AFTER bankaccounttypeid"; }
            if ($col === 'bankname')   { $after = " AFTER name"; }
            if ($col === 'iban')       { $after = " AFTER bankname"; }
            if ($col === 'personid')   { $after = " AFTER bic"; } // Positionierung
            if ($col === 'isactive')   { $after = " AFTER currencyid"; }
            
            $pdo->exec("ALTER TABLE bankaccount ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";