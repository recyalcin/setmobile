<?php
/**
 * admin/schemadriveractivitytype.php
 * Schema für Fahrer-Aktivitätstypen (Stammdaten)
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>⚙️ Schema Update: Driver Activity Type</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS driveractivitytype (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'        => "VARCHAR(100) NOT NULL",
        'note'        => "TEXT DEFAULT NULL",
        'createddate' => "TIMESTAMP DEFAULT NULL",
        'updateddate' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'driveractivitytype'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE driveractivitytype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }

    // Optional: Standard-Werte einfügen, falls Tabelle leer ist
    $count = $pdo->query("SELECT COUNT(*) FROM driveractivitytype")->fetchColumn();
    if ($count == 0) {
        $defaults = ['Beginnt Arbeit', 'Macht Pause', 'Geht Offline', 'Online', 'Hat Fahrgast'];
        $stmt = $pdo->prepare("INSERT INTO driveractivitytype (name, createddate) VALUES (?, NOW())");
        foreach ($defaults as $d) { $stmt->execute([$d]); }
        echo "<p style='color:blue;'>ℹ️ Standard-Aktivitätstypen wurden angelegt.</p>";
    }

    echo "<p>Datenbank-Check abgeschlossen.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";