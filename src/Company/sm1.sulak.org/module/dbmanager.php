<?php
/**
 * module/dbmanager.php
 * Datenbank-Struktur-Übersicht & CSV-Export
 * Orientiert am Aufbau von usermanager.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$currentRoute = "module/dbmanager";
$databaseName = $pdo->query("SELECT DATABASE()")->fetchColumn();

// --- 1. LOGIK: CSV EXPORT ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="db_structure_' . $databaseName . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Spaltenüberschriften für die CSV
    fputcsv($output, ['Table', 'Field', 'Type', 'Null', 'Key', 'Default', 'Extra']);

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            // Füge den Tabellennamen vorne an jede Zeile an
            $row = array_merge(['Table' => $table], $column);
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// --- 2. DATEN FÜR DIE ANZEIGE LADEN ---
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #6366f1;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0;">🛠 Datenbank-Manager</h3>
            <p style="font-size: 13px; color: #64748b; margin: 5px 0 0 0;">
                Aktive Datenbank: <strong><?= htmlspecialchars($databaseName) ?></strong>
            </p>
        </div>
        <a href="/?route=<?= $currentRoute ?>&export=csv" class="btn save" style="background: #6366f1; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            📥 Struktur als CSV exportieren
        </a>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tabelle</th>
                <th>Feld / Spalte</th>
                <th>Datentyp</th>
                <th>Key</th>
                <th>Default</th>
                <th style="text-align:right;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tables as $table): ?>
                <?php 
                $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
                $firstRow = true;
                foreach ($columns as $col): 
                ?>
                <tr>
                    <td style="background: #f8fafc; font-weight: bold;">
                        <?= $firstRow ? '<code style="color:#4338ca; font-size:14px;">' . htmlspecialchars($table) . '</code>' : '' ?>
                    </td>
                    <td><code><?= htmlspecialchars($col['Field']) ?></code></td>
                    <td style="font-size: 12px; color: #475569;"><?= htmlspecialchars($col['Type']) ?></td>
                    <td>
                        <?php if($col['Key']): ?>
                            <span class="badge" style="background:#e0e7ff; color:#4338ca;"><?= htmlspecialchars($col['Key']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($col['Default'] ?? 'NULL') ?></td>
                    <td style="text-align:right;">
                        <?php if($col['Null'] === 'NO'): ?>
                            <span title="Pflichtfeld" style="color: #ef4444;">●</span>
                        <?php else: ?>
                            <span title="Optional" style="color: #cbd5e1;">○</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                $firstRow = false;
                endforeach; ?>
                <tr style="height: 10px;"><td colspan="6" style="border:none;"></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.data-table th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 13px; color: #475569; border-bottom: 2px solid #e2e8f0; }
.data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
.badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
.btn.save { color: white; padding: 10px 20px; border-radius: 4px; font-weight: bold; font-size: 14px; transition: opacity 0.2s; }
.btn.save:hover { opacity: 0.9; }
code { font-family: monospace; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; }
</style>