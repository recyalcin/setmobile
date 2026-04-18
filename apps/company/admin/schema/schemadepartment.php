<?php
/**
 * admin/schemadepartment.php
 * Erstellt die Tabelle 'department'
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$sql = "CREATE TABLE IF NOT EXISTS department (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    createddate DATETIME,
    updateddate DATETIME
);";

try {
    $pdo->exec($sql);
    echo "<div class='card'><h3>Schema Update</h3><p>Tabelle <strong>department</strong> wurde erfolgreich geprüft/erstellt.</p>
          <a href='/department' class='btn save'>Zum Modul</a></div>";
} catch (PDOException $e) {
    echo "<div class='card' style='color:red;'>Fehler beim Erstellen der Tabelle: " . $e->getMessage() . "</div>";
}