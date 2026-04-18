<?php
/**
 * admin/schememployee.php
 * Schema für Mitarbeiterverwaltung
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>👥 Schema Update: Employee</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
        'createdat'             => "DATETIME DEFAULT NULL",
        'updatedat'             => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE employee")->fetchAll(PDO::FETCH_COLUMN);

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