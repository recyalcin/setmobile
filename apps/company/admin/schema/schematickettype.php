<?php
/**
 * admin/schematickettype.php - Schema für Ticket-Typen
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🏷️ Schema Update: Ticket-Typen</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickettype (id SERIAL PRIMARY KEY);");

    $columns = [
        'name'      => "VARCHAR(255) DEFAULT NULL",
        'note'      => "TEXT DEFAULT NULL",
        'createdat' => "TIMESTAMP DEFAULT NULL",
        'updatedat' => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tickettype'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $pdo->exec("ALTER TABLE tickettype ADD $col $type");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong></p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";