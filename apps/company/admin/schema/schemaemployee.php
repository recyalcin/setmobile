<?php
/**
 * admin/schememployee.php
 * Schema für Mitarbeiterverwaltung
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👥 Schema Update: Employee</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee (id SERIAL PRIMARY KEY);");

    $columns = [
        'employeetypeid'        => "INT DEFAULT NULL",
        'personid'              => "INT DEFAULT NULL",
        'employeenumber'        => "VARCHAR(50) DEFAULT NULL",
        'jobtitleid'            => "INT DEFAULT NULL",
        'departmentid'          => "INT DEFAULT NULL",
        'workinghoursschichtid' => "INT DEFAULT NULL", // NEU
        'businessemail'         => "VARCHAR(255) DEFAULT NULL",
        'businessphone'         => "VARCHAR(100) DEFAULT NULL",
        'hiredate'              => "DATE DEFAULT NULL",
        'terminationdate'       => "DATE DEFAULT NULL",
        'maxweeklyhours'        => "DECIMAL(10,2) DEFAULT NULL",
        'note'                  => "TEXT DEFAULT NULL",
        'createdat'             => "TIMESTAMP DEFAULT NULL",
        'updatedat'             => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'employee'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE employee ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
    echo "<p>Datenbank-Check abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";