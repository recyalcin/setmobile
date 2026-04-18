<?php
/**
 * admin/schemaperson.php - Migration für alle Personenfelder
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👤 Schema Update: Person</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS person (id SERIAL PRIMARY KEY);");

    $columns = [
        'persontypeid'  => "INT DEFAULT 0",
        'firstname'     => "VARCHAR(100) DEFAULT NULL",
        'middlename'    => "VARCHAR(100) DEFAULT NULL",
        'lastname'      => "VARCHAR(100) DEFAULT NULL",
        'email'         => "VARCHAR(150) DEFAULT NULL",
        'phone'         => "VARCHAR(50) DEFAULT NULL",
        'street'        => "VARCHAR(150) DEFAULT NULL",
        'housenr'       => "VARCHAR(20) DEFAULT NULL",
        'pobox'         => "VARCHAR(20) DEFAULT NULL",
        'city'          => "VARCHAR(100) DEFAULT NULL",
        'country'       => "VARCHAR(100) DEFAULT NULL",
        'taxid'         => "VARCHAR(50) DEFAULT NULL",
        'bankname'      => "VARCHAR(150) DEFAULT NULL",
        'iban'          => "VARCHAR(50) DEFAULT NULL",
        'bic'           => "VARCHAR(20) DEFAULT NULL",
        'dateofbirth'   => "DATE DEFAULT NULL",
        'birthcity'     => "VARCHAR(100) DEFAULT NULL",
        'birthcountry'  => "VARCHAR(100) DEFAULT NULL",
        'nationality'   => "VARCHAR(100) DEFAULT NULL",
        'gender'        => "VARCHAR(20) DEFAULT NULL",
        'note'          => "TEXT DEFAULT NULL",
        'createdat'     => "TIMESTAMP DEFAULT NULL",
        'updatedat'     => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'person'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE person ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";