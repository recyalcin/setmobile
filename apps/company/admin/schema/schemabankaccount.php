<?php
/**
 * admin/schemabankaccount.php
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🏦 Schema Update: Bank Account (Person Link)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bankaccount (id SERIAL PRIMARY KEY);");

    $columns = [
        'bankaccounttypeid' => "INT DEFAULT NULL",
        'name'              => "VARCHAR(255) DEFAULT NULL",
        'bankname'          => "VARCHAR(255) DEFAULT NULL",
        'iban'              => "VARCHAR(255) DEFAULT NULL",
        'bic'               => "VARCHAR(255) DEFAULT NULL",
        'personid'          => "INT DEFAULT NULL", // Ersetzt owner String durch personid
        'currencyid'        => "INT DEFAULT NULL",
        'isactive'          => "SMALLINT DEFAULT 1",
        'note'              => "TEXT DEFAULT NULL",
        'createdat'         => "TIMESTAMP DEFAULT NULL",
        'updatedat'         => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'bankaccount'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'name')       { $after = ""; }
            if ($col === 'bankname')   { $after = ""; }
            if ($col === 'iban')       { $after = ""; }
            if ($col === 'personid')   { $after = ""; } // Positionierung
            if ($col === 'isactive')   { $after = ""; }
            
            $pdo->exec("ALTER TABLE bankaccount ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";