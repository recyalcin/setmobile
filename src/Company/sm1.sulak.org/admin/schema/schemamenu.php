<?php
/**
 * admin/schemamenu.php
 * Update: Ergänzung Feld "note" vor "createdat"
 */
if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

echo "<div class='card'><h3>🚀 Schema Update: Menu</h3>";

try {
    // Basis-Tabelle sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu (menuid INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 1. Migration/Umbenennung alter Zeitstempel-Namen (falls vorhanden)
    $currentCols = $pdo->query("DESCRIBE menu")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('created', $currentCols) || in_array('createddate', $currentCols)) {
        $oldName = in_array('created', $currentCols) ? 'created' : 'createddate';
        $pdo->exec("ALTER TABLE menu CHANGE $oldName createdat TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "<p style='color:orange;'>🔄 Spalte <strong>$oldName</strong> zu <strong>createdat</strong> umbenannt.</p>";
    }

    if (in_array('updated', $currentCols) || in_array('updatedate', $currentCols)) {
        $oldName = in_array('updated', $currentCols) ? 'updated' : 'updatedate';
        $pdo->exec("ALTER TABLE menu CHANGE $oldName updatedat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "<p style='color:orange;'>🔄 Spalte <strong>$oldName</strong> zu <strong>updatedat</strong> umbenannt.</p>";
    }

    // 2. Definitive Zielfelder definieren
    // Hinweis: "note" wird nach "sortorder" eingefügt, damit es vor "createdat" steht
    $columns = [
        'parentid'  => ["type" => "INT NOT NULL DEFAULT 0", "after" => "menuid"],
        'name'      => ["type" => "VARCHAR(100) DEFAULT NULL", "after" => "parentid"],
        'url'       => ["type" => "VARCHAR(100) DEFAULT NULL", "after" => "name"],
        'sortorder' => ["type" => "INT NOT NULL DEFAULT 0", "after" => "url"],
        'note'      => ["type" => "TEXT DEFAULT NULL", "after" => "sortorder"],
        'createdat' => ["type" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "after" => "note"],
        'updatedat' => ["type" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", "after" => "createdat"]
    ];

    // Aktuellen Stand nach Migrationen holen
    $updatedCols = $pdo->query("DESCRIBE menu")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $info) {
        if (!in_array($col, $updatedCols)) {
            $sql = "ALTER TABLE menu ADD $col {$info['type']} AFTER {$info['after']}";
            $pdo->exec($sql);
            echo "<p style='color:green;'>✅ Feld ergänzt: <strong>$col</strong> (Position: nach {$info['after']})</p>";
        }
    }

    echo "<p>Struktur ist nun konsistent: ... > sortorder > <strong>note</strong> > createdat.</p>";

} catch (PDOException $e) { 
    echo "<p style='color:red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>"; 
}
echo "</div>";