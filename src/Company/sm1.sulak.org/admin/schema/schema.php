<?php
// admin/schema/schema.php (Der neue Loader im Unterordner)
if (!isset($pdo)) { die("Kein Datenbank-Zugriff."); }

$updateLog = [];

/**
 * Hilfsfunktion
 */
function addColumn($pdo, $table, $column, $definition) {
    global $updateLog;
    $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $updateLog[] = ['status' => 'added', 'msg' => "Feld <code>$column</code> zu <code>$table</code> hinzugefügt."];
        return true;
    }
    return false;
}

/**
 * Hilfsfunktion zum Hinzufügen von Indizes
 */
function addIndex($pdo, $table, $indexName, $column) {
    global $updateLog;
    // Prüfen, ob der Index bereits existiert
    $check = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
    
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` (`$column`)");
        $updateLog[] = ['status' => 'added', 'msg' => "Index <code>$indexName</code> für Tabelle <code>$table</code> erstellt."];
        return true;
    }
    return false;
}

?>
<div class="card">
    <h2>System Schema Update</h2>

    <?php
    // Da wir uns bereits in admin/schema/ befinden, ist __DIR__ unser Zielverzeichnis
    $currentDir = __DIR__ . '/';
    $loaderFile = 'schema.php';
    $coreFile   = 'schemaadmin.php';
    
    $processedFiles = [];

    // 1. CORE zuerst laden (schemaadmin.php)
    if (file_exists($currentDir . $coreFile)) {
        $processedFiles[] = $coreFile;
        include $currentDir . $coreFile;
    }

    // 2. Alle anderen schema*.php Dateien laden
    $files = glob($currentDir . 'schema*.php');
    foreach ($files as $file) {
        $fname = basename($file);
        
        // WICHTIG: Überspringe den Loader selbst und die bereits geladene Core-Datei
        if ($fname === $loaderFile || $fname === $coreFile) {
            continue;
        }
        
        $processedFiles[] = $fname;
        include $file;
    }
    ?>

    <h3>Update Report</h3>
    <table>
        <thead>
            <tr>
                <th style="width:200px;">Datei / Modul</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Gescannte Dateien:</strong></td>
                <td>
                    <?php foreach($processedFiles as $f): ?>
                        <code style="background:#eee; padding:2px 5px; border-radius:3px; margin-right:5px;"><?= $f ?></code>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php if (empty($updateLog)): ?>
                <tr>
                    <td colspan="2" style="text-align:center; color:#888; padding:20px;">Alles aktuell. Keine Änderungen durchgeführt.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($updateLog as $log): ?>
                    <tr>
                        <td style="color:#28a745; font-weight:bold;">Änderung</td>
                        <td><?= $log['msg'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top:20px; padding:10px; background:#e5f9e7; color:#1e7e34; text-align:center; border-radius:4px; font-weight:bold;">
        Update-Prozess beendet.
    </div>
</div>