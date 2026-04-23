<?php
/**
 * module/performance.php - Vollständiges Performance-Ranking
 * Logik: Join über driver & person | CSV Name-Split | Filter Ø > 0 | SQL Fix
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// Filter-Parameter
$filterDriver = $_GET['filter_driver'] ?? '';
$filterWeek = $_GET['filter_week'] ?? '';

// --- 1. HILFSFUNKTIONEN ---
function fmt($num) { return number_format((float)($num ?? 0), 2, ',', '.'); }

/**
 * Splittet Namen nach Vorgabe: 1. Wort = Vorname, Letztes Wort = Nachname, Rest = Mittelname
 */
function splitCsvName($fullName) {
    $parts = explode(' ', trim($fullName));
    $data = ['first' => '', 'middle' => '', 'last' => ''];
    if (count($parts) === 1) {
        $data['first'] = $parts[0];
    } else {
        $data['first'] = $parts[0];
        $data['last'] = end($parts);
        if (count($parts) > 2) {
            $data['middle'] = implode(' ', array_slice($parts, 1, -1));
        }
    }
    return $data;
}

// --- 2. DATEN FÜR FILTER LADEN ---
// Fahrer-Select: performance -> driver -> person
$filterDriversList = $pdo->query("
    SELECT DISTINCT d.id, p_name.firstname, p_name.lastname 
    FROM person p_name 
    JOIN driver d ON p_name.id = d.personid
    JOIN performance perf ON d.id = perf.driverid 
    ORDER BY p_name.lastname ASC
")->fetchAll();

// Wochen-Select
$filterWeeksList = $pdo->query("
    SELECT DISTINCT week FROM performance 
    WHERE week != '' AND week IS NOT NULL 
    ORDER BY week DESC
")->fetchAll(PDO::FETCH_COLUMN);

// --- 3. HAUPTABFRAGE ---
$searchSql = "";
$queryParams = [];

if (!empty($filterDriver)) {
    $searchSql .= " AND p.driverid = ? ";
    $queryParams[] = $filterDriver;
}
if (!empty($filterWeek)) {
    $searchSql .= " AND p.week = ? ";
    $queryParams[] = $filterWeek;
}

$sql_list = "
    SELECT p.*, p_name.firstname, p_name.lastname,
           stats.avg_cash, stats.avg_perf_total, stats.avg_rides_total,
           stats.avg_bolt_perf, stats.avg_bolt_rides, stats.avg_bolt_rate,
           stats.avg_uber_perf, stats.avg_uber_rides, stats.avg_uber_rate,
           stats.count_active_weeks
    FROM performance p
    JOIN driver d ON p.driverid = d.id
    JOIN person p_name ON d.personid = p_name.id
    JOIN (
        SELECT driverid, 
               AVG(collectedcashbolt + collectedcashuber) as avg_cash,
               AVG(earningsperformancebolt + earningsperformanceuber) as avg_perf_total,
               AVG(finishedridesbolt + finishedridesuber) as avg_rides_total,
               AVG(earningsperformancebolt) as avg_bolt_perf,
               AVG(finishedridesbolt) as avg_bolt_rides,
               AVG(totalacceptanceratebolt) as avg_bolt_rate,
               AVG(earningsperformanceuber) as avg_uber_perf,
               AVG(finishedridesuber) as avg_uber_rides,
               AVG(totalacceptancerateuber) as avg_uber_rate,
               COUNT(id) as count_active_weeks
        FROM performance
        WHERE (finishedridesbolt + finishedridesuber) > 0
        GROUP BY driverid
        /* FIX: Alias avg_perf_total funktioniert hier nicht, daher Formel ausschreiben */
        HAVING AVG(earningsperformancebolt + earningsperformanceuber) > 0
    ) as stats ON p.driverid = stats.driverid
    WHERE 1=1 $searchSql
    ORDER BY stats.avg_perf_total DESC, p.driverid, p.week DESC LIMIT 500
";

$stmtList = $pdo->prepare($sql_list);
$stmtList->execute($queryParams);
$list = $stmtList->fetchAll();

// --- 4. GLOBALEN DURCHSCHNITT BERECHNEN ---
$gCount = count($list);
$gData = ['cash' => 0, 'perf' => 0, 'rides' => 0, 'bolt_€' => 0, 'bolt_r' => 0, 'bolt_rate' => 0, 'uber_€' => 0, 'uber_r' => 0, 'uber_rate' => 0];

if ($gCount > 0) {
    foreach ($list as $row) {
        $gData['cash'] += ((float)$row['collectedcashbolt'] + (float)$row['collectedcashuber']);
        $gData['perf'] += ((float)$row['earningsperformancebolt'] + (float)$row['earningsperformanceuber']);
        $gData['rides'] += ((int)$row['finishedridesbolt'] + (int)$row['finishedridesuber']);
        $gData['bolt_€'] += (float)$row['earningsperformancebolt'];
        $gData['bolt_r'] += (int)$row['finishedridesbolt'];
        $gData['bolt_rate'] += (float)$row['totalacceptanceratebolt'];
        $gData['uber_€'] += (float)$row['earningsperformanceuber'];
        $gData['uber_r'] += (int)$row['finishedridesuber'];
        $gData['uber_rate'] += (float)$row['totalacceptancerateuber'];
    }
    foreach ($gData as $k => $v) { $gData[$k] = $v / $gCount; }
}
?>

<style>
    /* Suchbereich Styles */
    .inline-filter-form { display: flex; gap: 8px; align-items: center; width: 100%; margin-bottom: 20px; }
    .inline-filter-form select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; background: white; height: 38px; flex: 1; }
    .btn-search { background: #3b82f6; color: white; border: none; padding: 0 20px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; height: 38px; }
    .btn-reset { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; height: 36px; }

    /* Tabellen Styles */
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 6px; border: 1px solid #e2e8f0; background: white; }
    .listtable { width: 100%; border-collapse: collapse; font-size: 11px; white-space: nowrap; }
    .listtable th, .listtable td { padding: 10px 8px; border: 1px solid #eee; text-align: left; }
    .listtable th { background: #f4f4f4; color: #666; font-weight: bold; text-transform: uppercase; font-size: 10px; }
    
    /* Spezial-Zeilen */
    .row-grand-avg { background: #1e293b !important; color: #f8fafc !important; font-weight: bold; }
    .row-grand-avg td { border-bottom: 3px double #334155; padding: 12px 8px; }
    .row-avg-header td { background: #000 !important; color: #fff !important; font-weight: bold; border-bottom: 2px solid #333; }
    
    /* Spalten-Hervorhebung */
    .label-avg { font-size: 9px; color: #f1c40f; display: block; margin-bottom: 2px; text-transform: uppercase; }
    .col-bar { background: #fff9e6 !important; border-left: 2px solid #f1c40f !important; }
    .col-gesamt { background: #f0f7ff !important; border-left: 2px solid #3b82f6 !important; }
    .col-bolt { background: #f0faf3 !important; border-left: 2px solid #32bb78 !important; }
    .col-uber { background: #f9f9f9 !important; border-left: 2px solid #333 !important; }
    
    .row-grand-avg td.col-bar, .row-grand-avg td.col-gesamt, .row-grand-avg td.col-bolt, .row-grand-avg td.col-uber { background: transparent !important; color: white !important; }
</style>

<div class="card" style="padding: 12px 20px;">
    <form method="get" action="/" class="inline-filter-form">
        <input type="hidden" name="route" value="module/performance">
        <select name="filter_driver">
            <option value="">-- Alle aktiven Fahrer --</option>
            <?php foreach($filterDriversList as $fd): ?>
                <option value="<?= $fd['id'] ?>" <?= ($filterDriver == $fd['id']) ? 'selected' : '' ?>>👤 <?= htmlspecialchars($fd['lastname'] . ", " . $fd['firstname']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_week" style="max-width: 180px;">
            <option value="">-- Alle aktiven Wochen --</option>
            <?php foreach($filterWeeksList as $fw): ?>
                <option value="<?= htmlspecialchars($fw) ?>" <?= ($filterWeek == $fw) ? 'selected' : '' ?>>📅 <?= htmlspecialchars($fw) ?></option>
            <?php endforeach; ?>
        </select>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn-search">🔍 Suchen</button>
            <a href="/?route=module/performance" class="btn-reset">✖ Reset</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 20px; padding: 0; overflow: hidden; border-top: 4px solid #1e293b;">
    <div style="padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin:0; font-size: 16px;">📊 Performance Ranking (Top Ø-Performance)</h3>
        <span style="font-size: 11px; color: #64748b; font-weight: bold;"><?= $gCount ?> Einträge geladen</span>
    </div>
    <div class="table-responsive">
        <table class="listtable">
            <thead>
                <tr>
                    <th>Woche</th><th>Fahrer</th>
                    <th class="col-bar">Bar Ges.</th>
                    <th class="col-gesamt">Perf €</th><th class="col-gesamt">Rides</th>
                    <th class="col-bolt">Bolt €</th><th class="col-bolt">Bolt Rides</th><th class="col-bolt">Bolt Rate</th>
                    <th class="col-uber">Uber €</th><th class="col-uber">Uber Rides</th><th class="col-uber">Uber Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($gCount > 0): ?>
                    <tr class="row-grand-avg">
                        <td style="color:#38bdf8;">Ø GESAMT</td>
                        <td>ALLE GEFILTERTEN DATEN</td>
                        <td class="col-bar"><?= fmt($gData['cash']) ?> €</td>
                        <td class="col-gesamt"><?= fmt($gData['perf']) ?> €</td>
                        <td class="col-gesamt"><?= number_format($gData['rides'], 1) ?></td>
                        <td class="col-bolt"><?= fmt($gData['bolt_€']) ?> €</td>
                        <td class="col-bolt"><?= number_format($gData['bolt_r'], 1) ?></td>
                        <td class="col-bolt"><?= number_format($gData['bolt_rate'], 0) ?>%</td>
                        <td class="col-uber"><?= fmt($gData['uber_€']) ?> €</td>
                        <td class="col-uber"><?= number_format($gData['uber_r'], 1) ?></td>
                        <td class="col-uber"><?= number_format($gData['uber_rate'], 0) ?>%</td>
                    </tr>

                    <?php 
                    $lastDriverId = null; 
                    foreach($list as $row): 
                        if ($row['driverid'] !== $lastDriverId): 
                    ?>
                        <tr class="row-avg-header">
                            <td style="color:#f1c40f;">Ø AKTIV</td>
                            <td>👤 <?= htmlspecialchars($row['lastname'].' '.$row['firstname']) ?> (<?= (int)$row['count_active_weeks'] ?> W.)</td>
                            <td class="col-bar"><span class="label-avg">Ø BAR</span><?= fmt($row['avg_cash']) ?> €</td>
                            <td class="col-gesamt"><span class="label-avg">Ø PERF</span><?= fmt($row['avg_perf_total']) ?> €</td>
                            <td class="col-gesamt"><span class="label-avg">Ø RIDES</span><?= number_format((float)$row['avg_rides_total'], 1) ?></td>
                            <td class="col-bolt"><span class="label-avg">Ø BOLT €</span><?= fmt($row['avg_bolt_perf']) ?> €</td>
                            <td class="col-bolt"><span class="label-avg">Ø RIDES</span><?= number_format((float)$row['avg_bolt_rides'], 1) ?></td>
                            <td class="col-bolt"><span class="label-avg">Ø RATE</span><?= number_format((float)$row['avg_bolt_rate'], 0) ?>%</td>
                            <td class="col-uber"><span class="label-avg">Ø UBER €</span><?= fmt($row['avg_uber_perf']) ?> €</td>
                            <td class="col-uber"><span class="label-avg">Ø RIDES</span><?= number_format((float)$row['avg_uber_rides'], 1) ?></td>
                            <td class="col-uber"><span class="label-avg">Ø RATE</span><?= number_format((float)$row['avg_uber_rate'], 0) ?>%</td>
                        </tr>
                    <?php $lastDriverId = $row['driverid']; endif; ?>
                    
                    <tr>
                        <td style="color:#666;"><?= htmlspecialchars($row['week']) ?></td>
                        <td><strong><?= htmlspecialchars($row['lastname'].' '.$row['firstname']) ?></strong></td>
                        <td class="col-bar"><?= fmt((float)$row['collectedcashbolt'] + (float)$row['collectedcashuber']) ?> €</td>
                        <td class="col-gesamt" style="font-weight:bold; color:#1e40af;"><?= fmt((float)$row['earningsperformancebolt'] + (float)$row['earningsperformanceuber']) ?> €</td>
                        <td class="col-gesamt"><?= ((int)$row['finishedridesbolt'] + (int)$row['finishedridesuber']) ?></td>
                        <td class="col-bolt"><?= fmt($row['earningsperformancebolt']) ?> €</td>
                        <td class="col-bolt"><?= $row['finishedridesbolt'] ?></td>
                        <td class="col-bolt"><?= (int)$row['totalacceptanceratebolt'] ?>%</td>
                        <td class="col-uber"><?= fmt($row['earningsperformanceuber']) ?> €</td>
                        <td class="col-uber"><?= $row['finishedridesuber'] ?></td>
                        <td class="col-uber"><?= (int)$row['totalacceptancerateuber'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center; padding: 40px; color: #94a3b8;">Keine aktiven Performance-Daten vorhanden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>