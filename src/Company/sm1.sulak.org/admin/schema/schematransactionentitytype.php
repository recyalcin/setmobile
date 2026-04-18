<?php
/**
 * admin/schematransactionentitytype.php
 * Definiert die Arten von Akteuren (Person, Fahrzeug, Firma, etc.)
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🔗 Schema Check: Transaction Entity Type</h3>";

try {
    // Basis-Tabelle
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactionentitytype (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Felder (Strikt nach deiner Vorgabe)
    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'tablename'   => "VARCHAR(100) DEFAULT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "DATETIME DEFAULT NULL",
        'updateddate' => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE transactionentitytype")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE transactionentitytype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    echo "<p>Struktur ist bereit. Du kannst jetzt Entitäten definieren.</p>";
    echo "<br><a href='/module/transactionentitytype' class='btn save'>Zum Modul</a>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";