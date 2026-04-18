<?php
/**
 * admin/schematicket.php - Update: sortorder nach ticketstatusid
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🎫 Schema Update: Ticket (Sortierung)</h3>";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
        'dueat'            => "DATETIME DEFAULT NULL",
        'scheduledat'      => "DATETIME DEFAULT NULL",
        'resolvedat'       => "DATETIME DEFAULT NULL",
        'closedat'         => "DATETIME DEFAULT NULL",
        'note'             => "TEXT DEFAULT NULL",
        'createdat'        => "DATETIME DEFAULT NULL",
        'updatedat'        => "DATETIME DEFAULT NULL"
    ];

    $currentCols = $pdo->query("DESCRIBE ticket")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $type) {
        if (!in_array($col, $currentCols)) {
            $after = "";
            if ($col === 'ticketcategoryid') { $after = " AFTER tickettypeid"; }
            if ($col === 'requesterid')      { $after = " AFTER createdbyid"; }
            if ($col === 'sortorder')        { $after = " AFTER ticketstatusid"; } // Positionierung
            if ($col === 'dueat')            { $after = " AFTER description"; }
            if ($col === 'scheduledat')      { $after = " AFTER dueat"; }
            
            $pdo->exec("ALTER TABLE ticket ADD $col $type$after");
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong>$after</p>";
        }
    }
} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";