<?php
/**
 * admin/schemaemployeetype.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$sql = "CREATE TABLE IF NOT EXISTS employeetype (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    note TEXT,
    createddate DATETIME,
    updateddate DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo "<div class='card'><h3>Schema Update</h3><p>Tabelle <strong>employeetype</strong> wurde erfolgreich aktualisiert.</p>
          <a href='/module/employeetype' class='btn save'>Zum Modul</a></div>";
} catch (PDOException $e) {
    echo "<div class='card' style='color:red;'>Fehler: " . $e->getMessage() . "</div>";
}