<?php
/**
 * admin/schematransaction.php
 * Update: Ergänzung officialtypeid
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>💸 Schema Update: Transaction</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction (id SERIAL PRIMARY KEY);");

    $columns = [
        'transactiontypeid' => "INT DEFAULT NULL",
        'officialtypeid'    => "INT DEFAULT NULL", // NEU
        'date'              => "DATE DEFAULT NULL",
        'fromtypeid'        => "INT DEFAULT NULL",
        'fromid'            => "INT DEFAULT NULL",
        'totypeid'          => "INT DEFAULT NULL",
        'toid'              => "INT DEFAULT NULL",
        'amount'            => "DECIMAL(10,2) DEFAULT 0.00",
        'description'       => "VARCHAR(255) DEFAULT NULL",
        'vehicleid'         => "INT DEFAULT NULL",
        'personid'          => "INT DEFAULT NULL",
        'note'              => "TEXT DEFAULT NULL",
        'createddate'       => "TIMESTAMP DEFAULT NULL",
        'updateddate'       => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'transaction'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE transaction ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";