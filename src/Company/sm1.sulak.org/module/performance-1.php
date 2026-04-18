<?php
/**
 * performance.php
 * * Template: Basierend auf cashboxjournal.php (Filter- & Listen-Struktur)
 * Logik: Basierend auf performance01.php (Durchschnittsberechnung & Ranking)
 */

// 1. Initialisierung & Konfiguration
$id = ''; 
$kisiid = $_GET['f_kisiid'] ?? ''; 
$week_filter = $_GET['f_week'] ?? '';
$error = ''; $msg = '';
$baseurl = "/?s=268600514930&a=performance";
$importurl = "/?s=268600514930&a=csvperformance";

$fields = [
    'netearningsbolt', 'tollfeesbolt', 'ridertipsbolt', 'earningsperformancebolt', 'collectedcashbolt', 'finishedridesbolt', 'onlinetimebolt', 'totalridedistancebolt', 'totalacceptanceratebolt',
    'netearningsuber', 'tollfeesuber', 'ridertipsuber', 'earningsperformanceuber', 'collectedcashuber', 'finishedridesuber', 'onlinetimeuber', 'totalridedistanceuber', 'totalacceptancerateuber'
];

/**
 * Hilfsfunktionen
 */
function cleanNum($val) {
    if ($val === null || $val === '') return 0;
    $val = str_replace(['"', ' ', '€'], '', $val);
    if (strpos($val, ',') !== false && strpos($val, '.') !== false) { $val = str_replace('.', '', $val); }
    $val = str_replace(',', '.', $val);
    return is_numeric($val) ? (float)$val : 0;
}

function fmt($num) { return number_format((float)($num ?? 0), 2, ',', '.'); }

// 2. CRUD Logik (Speichern / Löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $post_kisiid = $_POST['kisiid']; 
        $post_week = $_POST['week'];
        
        // Performance-Berechnung: Netto - Gebühren - Trinkgeld
        $val_eb = cleanNum($_POST['netearningsbolt']??0) - cleanNum($_POST['tollfeesbolt']??0) - cleanNum($_POST['ridertipsbolt']??0);
        $val_eu = cleanNum($_POST['netearningsuber']??0) - cleanNum($_POST['tollfeesuber']??0) - cleanNum($_POST['ridertipsuber']??0);
        
        $vals = [$post_kisiid, $post_week]; 
        $updateParts = [];
        foreach($fields as $f) { 
            $v = ($f == 'earningsperformancebolt') ? $val_eb : (($f == 'earningsperformanceuber') ? $val_eu : ($_POST[$f] ?? 0));
            $vals[] = $v; 
            $updateParts[] = "`$f` = VALUES(`$f`)"; 
        }
        
        $sql = "INSERT INTO performance (kisiid, week, `" . implode("`, `", $fields) . "`) 
                VALUES (?, ?, " . str_repeat('?,', count($fields)-1) . "?) 
                ON DUPLICATE KEY UPDATE " . implode(", ", $updateParts);
        $pdo->prepare($sql)->execute($vals);
        header("Location: $baseurl"); exit;
    }
    
    if ($_POST['action'] === 'delete' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM performance WHERE id=?")->execute([$_POST['id']]);
        header("Location: $baseurl"); exit;
    }
}

// 3. Daten laden
// Filter-Bedingungen
$whereClauses = [];
$params = [];
if ($kisiid) { $whereClauses[] = "p.kisiid = ?"; $params[] = $kisiid; }
if ($week_filter) { $whereClauses[] = "p.week = ?"; $params[] = $week_filter; }
$whereSql = count($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Haupt-Query mit Ranking-Logik (Ausschluss von 0-Wochen in der AVG-Statistik)
$sql_list = "
    SELECT p.*, k.ad, k.soyad,
           stats.avg_cash, stats.avg_perf_total, stats.avg_rides_total,
           stats.avg_bolt_perf, stats.avg_bolt_rides, stats.avg_bolt_rate,
           stats.avg_uber_perf, stats.avg_uber_rides, stats.avg_uber_rate,
           stats.count_active_weeks
    FROM performance p
    JOIN kisi k ON p.kisiid = k.id
    LEFT JOIN (
        SELECT kisiid, 
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
        GROUP BY kisiid
    ) as stats ON p.kisiid = stats.kisiid
    $whereSql
    ORDER BY stats.avg_perf_total DESC, p.kisiid, p.week DESC
";

$list = $pdo->prepare($sql_list);
$list->execute($params);
$results = $list->fetchAll();

$kisilist = $pdo->query("SELECT id, ad, soyad FROM kisi ORDER BY ad ASC")->fetchAll();
?>

<style>
    .perf-wrapper { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f8f9fa; }
    
    /* Filterbereich Styling */
    .filter-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #dee2e6; }
    .filter-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 12px; font-weight: 600; color: #495057; }
    .form-control { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
    
    /* Button Styling */
    .btn-search { background: #007bff; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .btn-search:hover { background: #0056b3; }
    .btn-reset { background: #6c757d; color: white; text-decoration: none; padding: 9px 20px; border-radius: 4px; font-size: 14px; }
    .btn-import { background: #32bb78; color: white; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; margin-left: auto; }

    /* Tabellen Styling */
    .journal-container { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #dee2e6; }
    .listtable { width: 100%; border-collapse: collapse; font-size: 12px; }
    .listtable th { background: #f1f3f5; padding: 12px 10px; text-align: left; color: #495057; border-bottom: 2px solid #dee2e6; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
    .listtable td { padding: 10px; border-bottom: 1px solid #eee; }
    
    /* Spezial-Reihen */
    .row-avg-header td { background: #212529 !important; color: #fff !important; font-weight: bold; border-bottom: 2px solid #000; }
    .label-avg { font-size: 9px; color: #f1c40f; display: block; text-transform: uppercase; margin-bottom: 2px; }
    
    /* Spalten-Farbakzente */
    .col-bar { background: #fffdf2 !important; border-left: 3px solid #f1c40f !important; }
    .col-gesamt { background: #f0f7ff !important; border-left: 3px solid #007bff !important; }
    .col-bolt { background: #f2faf5 !important; border-left: 3px solid #32bb78 !important; }
    .col-uber { background: #f8f9fa !important; border-left: 3px solid #343a40 !important; }
    
    .badge-weeks { background: #495057; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; }
</style>

<div class="perf-wrapper">
    
    <div class="filter-panel">
        <form method="GET" action="/" class="filter-row">
            <input type="hidden" name="s" value="268600514930">
            <input type="hidden" name="a" value="performance">
            
            <div class="form-group">
                <label>Fahrer</label>
                <select name="f_kisiid" class="form-control">
                    <option value="">Alle Fahrer</option>
                    <?php foreach($kisilist as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kisiid == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['ad'].' '.$k['soyad']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Woche</label>
                <input type="date" name="f_week" class="form-control" value="<?= htmlspecialchars($week_filter) ?>">
            </div>

            <button type="submit" class="btn-search">Filtern</button>
            <a href="<?= $baseurl ?>" class="btn-reset">Reset</a>
            
            <a href="<?= $importurl ?>" class="btn-import">➕ CSV Import Center</a>
        </form>
    </div>

    <div class="journal-container">
        <table class="listtable">
            <thead>
                <tr>
                    <th>Woche / Fahrer</th>
                    <th class="col-bar">Bar-Umsatz</th>
                    <th class="col-gesamt">Perf. Gesamt</th>
                    <th class="col-gesamt">Fahrten</th>
                    <th class="col-bolt">Bolt €</th>
                    <th class="col-bolt">Bolt Rides</th>
                    <th class="col-bolt">Annahme %</th>
                    <th class="col-uber">Uber €</th>
                    <th class="col-uber">Uber Rides</th>
                    <th class="col-uber">Annahme %</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $lastDriverId = null; 
                foreach($results as $row): 
                    // Gruppen-Header für Durchschnittswerte des Fahrers (nur anzeigen wenn nicht gefiltert wird oder neuer Fahrer kommt)
                    if ($row['kisiid'] !== $lastDriverId): 
                ?>
                    <tr class="row-avg-header">
                        <td>
                            <span class="label-avg">Performance Ranking</span>
                            👤 <?= htmlspecialchars($row['ad'].' '.$row['soyad']) ?> 
                            <span class="badge-weeks"><?= (int)$row['count_active_weeks'] ?> aktive Wochen</span>
                        </td>
                        <td class="col-bar"><span class="label-avg">Ø Bar</span><?= fmt($row['avg_cash']) ?> €</td>
                        <td class="col-gesamt"><span class="label-avg">Ø Perf</span><?= fmt($row['avg_perf_total']) ?> €</td>
                        <td class="col-gesamt"><span class="label-avg">Ø Rides</span><?= number_format((float)$row['avg_rides_total'], 1) ?></td>
                        <td class="col-bolt"><span class="label-avg">Ø Bolt €</span><?= fmt($row['avg_bolt_perf']) ?> €</td>
                        <td class="col-bolt"><span class="label-avg">Ø Rides</span><?= number_format((float)$row['avg_bolt_rides'], 1) ?></td>
                        <td class="col-bolt"><span class="label-avg">Ø Rate</span><?= number_format((float)$row['avg_bolt_rate'], 0) ?>%</td>
                        <td class="col-uber"><span class="label-avg">Ø Uber €</span><?= fmt($row['avg_uber_perf']) ?> €</td>
                        <td class="col-uber"><span class="label-avg">Ø Rides</span><?= number_format((float)$row['avg_uber_rides'], 1) ?></td>
                        <td class="col-uber"><span class="label-avg">Ø Rate</span><?= number_format((float)$row['avg_uber_rate'], 0) ?>%</td>
                        <td>-</td>
                    </tr>
                <?php $lastDriverId = $row['kisiid']; endif; ?>
                
                <tr>
                    <td style="color:#666;">
                        <strong>KW <?= date('W', strtotime($row['week'])) ?></strong> (<?= date('d.m.Y', strtotime($row['week'])) ?>)
                    </td>
                    <td class="col-bar"><?= fmt((float)$row['collectedcashbolt'] + (float)$row['collectedcashuber']) ?> €</td>
                    <td class="col-gesamt" style="font-weight:bold;"><?= fmt((float)$row['earningsperformancebolt'] + (float)$row['earningsperformanceuber']) ?> €</td>
                    <td class="col-gesamt"><?= ((int)$row['finishedridesbolt'] + (int)$row['finishedridesuber']) ?></td>
                    <td class="col-bolt"><?= fmt($row['earningsperformancebolt']) ?> €</td>
                    <td class="col-bolt"><?= $row['finishedridesbolt'] ?></td>
                    <td class="col-bolt"><?= (int)$row['totalacceptanceratebolt'] ?>%</td>
                    <td class="col-uber"><?= fmt($row['earningsperformanceuber']) ?> €</td>
                    <td class="col-uber"><?= $row['finishedridesuber'] ?></td>
                    <td class="col-uber"><?= (int)$row['totalacceptancerateuber'] ?>%</td>
                    <td>
                        <a href="<?= $baseurl ?>&editid=<?= $row['id'] ?>" style="color:#007bff; text-decoration:none; font-weight:600;">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($results)): ?>
                    <tr><td colspan="11" style="text-align:center; padding:30px; color:#999;">Keine Daten für die gewählten Filter gefunden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>