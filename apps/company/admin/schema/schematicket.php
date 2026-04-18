<?php
/**
 * admin/schematicket.php - Update: sortorder nach ticketstatusid
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🎫 Schema Update: Ticket (Sortierung)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket (id SERIAL PRIMARY KEY);");

    $columns = [
        'tickettypeid'     => "INT DEFAULT NULL",
        'ticketcategoryid' => "INT DEFAULT NULL",
        'createdbyid'      => "INT DEFAULT NULL",
        'requesterid'      => "INT DEFAULT NULL",
        'assignedtoid'     => "INT DEFAULT NULL",
        'priorityid'       => "INT DEFAULT NULL",
        'ticketstatusid'   => "INT DEFAULT NULL",
        'sortorder'        => "INT DEFAULT NULL", // Neu: Sortier-Reihenfolge
        'subject'          => "VARCHAR(255) DEFAULT NULL",
        'description'      => "TEXT DEFAULT NULL",
        'dueat'            => "TIMESTAMP DEFAULT NULL",
        'scheduledat'      => "TIMESTAMP DEFAULT NULL",
        'resolvedat'       => "TIMESTAMP DEFAULT NULL",
        'closedat'         => "TIMESTAMP DEFAULT NULL",
        'note'             => "TEXT DEFAULT NULL",
        'createdat'        => "TIMESTAMP DEFAULT NULL",
        'updatedat'        => "TIMESTAMP DEFAULT NULL"
    ];

    $currentCols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'ticket'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'ticketcategoryid') { $after = ""; }
            if ($col === 'requesterid')      { $after = ""; }
            if ($col === 'sortorder')        { $after = ""; } // Positionierung
            if ($col === 'dueat')            { $after = ""; }
            if ($col === 'scheduledat')      { $after = ""; }
            
            $pdo->exec("ALTER TABLE ticket ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";