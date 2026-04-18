<?php
/**
 * module/dbmanager.php
 * Datenbank-Struktur-Übersicht & CSV-Export
 * Orientiert am Aufbau von usermanager.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$currentRoute = "module/dbmanager";
// PostgreSQL: Get current database name
$databaseName = $pdo->query("SELECT current_database()")->fetchColumn();

// PostgreSQL: Get all tables from information_schema
function get_postgres_tables($pdo) {
    $result = $pdo->query(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);
    return $result;
}

// PostgreSQL: Get table structure with column info
function get_postgres_columns($pdo, $table) {
    $query = "
        SELECT
            column_name AS Field,
            data_type AS Type,
            CASE WHEN is_nullable = 'YES' THEN 'YES' ELSE 'NO' END AS Null,
            CASE WHEN constraint_type = 'PRIMARY KEY' THEN 'PRI' ELSE '' END AS Key,
            column_default AS Default,
            '' AS Extra
        FROM information_schema.columns
        LEFT JOIN information_schema.key_column_usage USING (table_name, column_name)
        LEFT JOIN information_schema.table_constraints USING (constraint_name)
        WHERE table_schema = 'public' AND table_name = ?
        ORDER BY ordinal_position
    ";
    return $pdo->prepare($query)->execute([$table]) ? $pdo->prepare($query)->execute([$table]) : [];
}

// --- 1. LOGIK: CSV EXPORT ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="db_structure_' . $databaseName . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // Spaltenüberschriften für die CSV
    fputcsv($output, ['Table', 'Field', 'Type', 'Null', 'Key', 'Default', 'Extra']);

    $tables = get_postgres_tables($pdo);
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("
            SELECT
                column_name AS Field,
                data_type AS Type,
                CASE WHEN is_nullable = 'YES' THEN 'YES' ELSE 'NO' END AS Null,
                column_default AS Default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            // Füge den Tabellennamen vorne an jede Zeile an
            $row = ['Table' => $table];
            $row['Field'] = $column['Field'] ?? '';
            $row['Type'] = $column['Type'] ?? '';
            $row['Null'] = $column['Null'] ?? '';
            $row['Key'] = '';
            $row['Default'] = $column['Default'] ?? '';
            $row['Extra'] = '';
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// --- 2. DATEN FÜR DIE ANZEIGE LADEN ---
$tables = get_postgres_tables($pdo);
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
                // PostgreSQL column query
                $colStmt = $pdo->prepare("
                    SELECT
                        column_name AS Field,
                        data_type AS Type,
                        is_nullable,
                        column_default AS Default
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = ?
                    ORDER BY ordinal_position
                ");
                $colStmt->execute([$table]);
                $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <!-- PostgreSQL doesn't expose Key info in information_schema like MySQL DESCRIBE does -->
                    </td>
                    <td style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($col['Default'] ?? 'NULL') ?></td>
                    <td style="text-align:right;">
                        <?php if($col['is_nullable'] === 'NO'): ?>
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