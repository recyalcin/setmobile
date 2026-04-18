<?php
/**
 * module/workinghours.php - Management von Arbeitszeiten inkl. Trip-Spalten in der Liste
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

// --- 0. LOOKUP: personid ermitteln ---
$sessionUserId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
$loggedInPersonId = 0;

if ($sessionUserId > 0) {
    $stmtUser = $pdo->prepare("SELECT personid FROM user WHERE id = ?");
    $stmtUser->execute([$sessionUserId]);
    $userRow = $stmtUser->fetch();
    if ($userRow) {
        $loggedInPersonId = (int)$userRow['personid'];
    }
}

$message = '';
$redirect = false;

// Hilfsfunktion für korrekte Summenbildung (erkennt negative Werte und Kommas)
function parseToFloat($val) {
    if ($val === null || $val === '' || $val === '-') return 0.0;
    $val = str_replace(',', '.', $val);
    return (float)$val;
}

// --- 1. LOGIK: AKTIONEN (Speichern / Löschen) ---
if (isset($_POST['save_workinghours']) || isset($_POST['duplicate_workinghours'])) {
    $id = (isset($_POST['duplicate_workinghours'])) ? null : ($_POST['id'] ?? null);
    
    $fields = [
        'employeeid', 'date', 'firsttripat', 'lasttripat', 'workstartat', 
        'workendat', 'breakduration', 'hours0004', 'hours2006', 'hourstotal', 
        'recordedat', 'note'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        if (in_array($f, ['date', 'firsttripat', 'lasttripat', 'workstartat', 'workendat', 'recordedat'])) {
            if (!empty($val)) {
                $timestamp = strtotime($val);
                $format = ($f === 'date') ? 'Y-m-d' : 'Y-m-d H:i:s';
                $params[] = $timestamp ? date($format, $timestamp) : null;
            } else {
                $params[] = null;
            }
        } else {
            $params[] = ($val !== '') ? $val : null;
        }
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE workinghours SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/workinghours&msg=updated";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO workinghours ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/workinghours&msg=created";
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM workinghours WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/workinghours&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$employees = $pdo->query("SELECT e.id, p.lastname, p.firstname 
                          FROM employee e 
                          JOIN person p ON e.personid = p.id 
                          ORDER BY p.lastname ASC")->fetchAll();

$edit = null;
$isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
if (isset($_GET['edit']) && !$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM workinghours WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 3. FILTER LOGIK ---
$filter_emp  = $_GET['f_emp'] ?? '';
$filter_from = $_GET['f_from'] ?? '';
$filter_to   = $_GET['f_to'] ?? '';
$filter_q    = $_GET['f_q'] ?? '';

$where = ["1=1"];
$params_list = [];

if ($filter_emp !== '') {
    $where[] = "w.employeeid = ?";
    $params_list[] = $filter_emp;
}
if ($filter_from !== '') {
    $where[] = "w.date >= ?";
    $params_list[] = date('Y-m-d', strtotime($filter_from));
}
if ($filter_to !== '') {
    $where[] = "w.date <= ?";
    $params_list[] = date('Y-m-d', strtotime($filter_to));
}
if ($filter_q !== '') {
    $where[] = "(w.note LIKE ? OR w.id LIKE ?)";
    $params_list[] = "%$filter_q%";
    $params_list[] = "%$filter_q%";
}

$sql_list = "SELECT w.*, p.lastname as emp_lname, p.firstname as emp_fname 
             FROM workinghours w
             LEFT JOIN employee e ON w.employeeid = e.id
             LEFT JOIN person p ON e.personid = p.id
             WHERE " . implode(" AND ", $where) . "
             ORDER BY w.date ASC, w.workstartat ASC LIMIT 500";
$stmtList = $pdo->prepare($sql_list);
$stmtList->execute($params_list);
$list = $stmtList->fetchAll();

// --- 4. PDF EXPORT ---
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $currentEmployee = "Unbekannt";
    foreach($employees as $e) { if($e['id'] == $filter_emp) $currentEmployee = $e['firstname']." ".$e['lastname']; }
    $period = ($filter_from && $filter_to) ? $filter_from." - ".$filter_to : date('m.Y');
    
    $sigDate = date('d.m.Y'); 
    if (!empty($list)) {
        $lastEntry = end($list);
        $sigDate = date('d.m.Y', strtotime($lastEntry['date'] . ' + 1 day'));
        reset($list);
    }

    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { size: A4; margin: 1cm; }
            body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; line-height: 1.15; }
            .header h2 { font-size: 11pt; margin: 0 0 2px 0; }
            .hint { font-size: 7pt; font-style: italic; margin-bottom: 8px; border-bottom: 0.5px solid #000; padding-bottom: 3px; }
            .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
            .data-table th, .data-table td { border: 0.5px solid #000; padding: 2.5px 2px; text-align: center; }
            .data-table th { background: #f2f2f2; font-size: 7.5pt; }
            .sum-row { font-weight: bold; background: #f2f2f2; }
            .footer-sig { margin-top: 25px; width: 100%; }
            .sig-box { border-top: 0.5px solid #000; width: 30%; display: inline-block; text-align: center; font-size: 7pt; padding-top: 2px; margin-right: 8%; }
        </style>
    </head>
    <body onload="window.print()">
        <div class="header">
            <h2>Aufzeichnung der Arbeitszeiten gemäß § 17 Mindestlohngesetz</h2>
            <div class="hint">Wichtiger Hinweis: Die Aufzeichnungen müssen spätestens mit Ablauf des 7. Kalendertages erstellt werden, der auf den Tag der Arbeitsleistung folgt. Sie sind 2 Jahre lang aufzubewahren.</div>
        </div>
        <p><strong>Arbeitgeber:</strong> Mietwagenunternehmen Süleyman Sulak | <strong>Zeitraum:</strong> <?= $period ?><br>
        <strong>Arbeitnehmer:</strong> <?= $currentEmployee ?></p>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Datum</th><th>Tag</th><th>Beginn</th><th>Ende</th><th>Pause</th>
                    <th>Std (00-04)</th><th>Std (20-06)</th><th>Total</th><th>Aufzeichnung</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sP = 0; $s04 = 0; $s20 = 0; $sT = 0;
                $days_de = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
                foreach ($list as $row): 
                    $ts = strtotime($row['date']);
                    $recDate = date('d.m.Y', strtotime($row['date'] . ' + 1 day'));
                    
                    $vP   = parseToFloat($row['breakduration']);
                    $v04  = parseToFloat($row['hours0004']);
                    $v20  = parseToFloat($row['hours2006']);
                    $vT   = parseToFloat($row['hourstotal']);

                    $sP  += $vP; $s04 += $v04; $s20 += $v20; $sT  += $vT;

                    $isEmpty = ($vT == 0 && $v04 == 0 && $v20 == 0 && empty($row['workstartat']));
                ?>
                <tr>
                    <td><?= date('d.m.Y', $ts) ?></td>
                    <td><?= $days_de[date('w', $ts)] ?></td>
                    <td><?= (!$isEmpty && $row['workstartat']) ? date('H:i', strtotime($row['workstartat'])) : '-' ?></td>
                    <td><?= (!$isEmpty && $row['workendat']) ? date('H:i', strtotime($row['workendat'])) : '-' ?></td>
                    <td><?= ($vP == 0) ? '-' : number_format($vP, 2, ',', '.') ?></td>
                    <td><?= ($v04 == 0) ? '-' : number_format($v04, 2, ',', '.') ?></td>
                    <td><?= ($v20 == 0) ? '-' : number_format($v20, 2, ',', '.') ?></td>
                    <td><?= ($vT == 0 && $isEmpty) ? '-' : number_format($vT, 2, ',', '.') ?></td>
                    <td><?= $recDate ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="sum-row">
                    <td colspan="4" align="right">Summe:</td>
                    <td>-</td>
                    <td><?= number_format($s04, 2, ',', '.') ?></td>
                    <td><?= number_format($s20, 2, ',', '.') ?></td>
                    <td><?= number_format($sT, 2, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="footer-sig">
            <p style="margin-bottom: 20px;">Datum: <?= $sigDate ?></p>
            <div class="sig-box">Unterschrift Arbeitnehmer</div>
            <div class="sig-box">Unterschrift Arbeitgeber</div>
        </div>
    </body>
    </html>
    <?php exit;
}

$lastMonthStart = date('01.m.Y', strtotime('first day of last month'));
$lastMonthEnd   = date('t.m.Y', strtotime('last day of last month'));
$days_de_short = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .datepicker-container { position: relative; display: flex; align-items: center; flex: 1; }
    .calendar-icon { position: absolute; right: 10px; color: #64748b; cursor: pointer; }
    .form-container { display: flex; flex-direction: column; gap: 8px; }
    .form-row { display: flex; align-items: center; margin-bottom: 5px; }
    .form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
    .form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; border: 1px solid #cbd5e1; }
    .neu-bg { background: #eff6ff; color: #3b82f6; border-color: #3b82f6; }
    .search-bg { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .data-table td, .data-table th { padding: 8px 6px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .badge { padding: 2px 6px; background:#eff6ff; color:#1e40af; border-radius: 4px; font-size: 10px; font-weight: bold; }
    .sum-row { background: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1; }
</style>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🕒 Arbeitszeit-Management</h3>
        <a href="/?route=module/workinghours&edit=new" class="btn-action neu-bg">+ Neu</a>
    </div>

    <form method="post" action="/?route=module/workinghours" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        <div style="display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Mitarbeiter</label>
                    <select name="employeeid" required>
                        <option value="">-- wählen --</option>
                        <?php foreach($employees as $e): 
                            $sel = ($edit['employeeid'] ?? '') == $e['id'] ? 'selected' : '';
                        ?>
                            <option value="<?= $e['id'] ?>" <?= $sel ?>><?= htmlspecialchars($e['lastname'].", ".$e['firstname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Datum</label><input type="text" class="dt-picker-date" name="date" value="<?= isset($edit['date']) ? date('d.m.Y', strtotime($edit['date'])) : date('d.m.Y') ?>"></div>
                <div class="form-row"><label>Beginn / Ende</label>
                    <input type="text" class="dt-picker" name="workstartat" style="width:48%; margin-right:2%;" value="<?= isset($edit['workstartat']) ? date('d.m.Y H:i', strtotime($edit['workstartat'])) : date('d.m.Y 08:00') ?>">
                    <input type="text" class="dt-picker" name="workendat" style="width:48%;" value="<?= isset($edit['workendat']) ? date('d.m.Y H:i', strtotime($edit['workendat'])) : date('d.m.Y 17:00') ?>">
                </div>
            </div>
            <div>
                <div class="form-row"><label>Pause / Total</label>
                    <input type="text" name="breakduration" style="width:48%; margin-right:2%;" value="<?= htmlspecialchars($edit['breakduration'] ?? '') ?>">
                    <input type="number" step="0.01" name="hourstotal" style="width:48%;" value="<?= htmlspecialchars($edit['hourstotal'] ?? '') ?>">
                </div>
                <div class="form-row"><label>Spezial (0-4 / 20-6)</label>
                    <input type="number" step="0.01" name="hours0004" style="width:48%; margin-right:2%;" value="<?= htmlspecialchars($edit['hours0004'] ?? '') ?>">
                    <input type="number" step="0.01" name="hours2006" style="width:48%;" value="<?= htmlspecialchars($edit['hours2006'] ?? '') ?>">
                </div>
                <div class="form-row"><label>Notiz</label><textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 15px; gap: 10px;">
            <button type="submit" name="save_workinghours" class="btn-action neu-bg" style="padding: 10px 40px;">💾 Speichern</button>
        </div>
    </form>
</div>

<form method="get" action="/" class="search-bg">
    <input type="hidden" name="route" value="module/workinghours">
    <div style="flex: 1;"><label style="font-size:11px; font-weight:bold;">Mitarbeiter</label>
        <select name="f_emp" style="width: 100%; padding: 6px;">
            <option value="">-- Alle --</option>
            <?php foreach($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $filter_emp == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['lastname'].", ".$e['firstname']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="width: 130px;"><label style="font-size:11px; font-weight:bold;">Von</label><input type="text" class="dt-picker-date" id="f_from" name="f_from" value="<?= htmlspecialchars($filter_from) ?>"></div>
    <div style="width: 130px;"><label style="font-size:11px; font-weight:bold;">Bis</label><input type="text" class="dt-picker-date" id="f_to" name="f_to" value="<?= htmlspecialchars($filter_to) ?>"></div>
    <button type="button" onclick="setLastMonth()" class="btn-action">Letzter Monat</button>
    <button type="submit" class="btn-action" style="background:#64748b; color:white;">🔍 Filtern</button>
    <a href="/?route=module/workinghours" class="btn-action">✕</a>
</form>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Vorname</th><th>Nachname</th><th>Datum</th><th>WT</th>
                <th>1. Trip</th><th>L. Trip</th> <th>Beginn</th><th>Ende</th><th>Pause</th><th>0-4h</th><th>20-6h</th><th>Total</th><th style="text-align:right;">✎</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sumP = 0; $sum04 = 0; $sum20 = 0; $sumT = 0;
            foreach ($list as $w): 
                $dt = strtotime($w['date']);
                $vP_l   = parseToFloat($w['breakduration']);
                $v04_l  = parseToFloat($w['hours0004']);
                $v20_l  = parseToFloat($w['hours2006']);
                $vT_l   = parseToFloat($w['hourstotal']);

                $sumP  += $vP_l; $sum04 += $v04_l; $sum20 += $v20_l; $sumT  += $vT_l;

                $isEmptyList = ($vT_l == 0 && $v04_l == 0 && $v20_l == 0 && empty($w['workstartat']));
            ?>
            <tr>
                <td><?= htmlspecialchars($w['emp_fname'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($w['emp_lname'] ?? '-') ?></strong></td>
                <td><?= date('d.m.Y', $dt) ?></td>
                <td><small><?= $days_de_short[date('w', $dt)] ?></small></td>
                <td><?= $w['firsttripat'] ? date('H:i', strtotime($w['firsttripat'])) : '-' ?></td>
                <td><?= $w['lasttripat'] ? date('H:i', strtotime($w['lasttripat'])) : '-' ?></td>
                
                <td><?= (!$isEmptyList && $w['workstartat']) ? date('H:i', strtotime($w['workstartat'])) : '-' ?></td>
                <td><?= (!$isEmptyList && $w['workendat']) ? date('H:i', strtotime($w['workendat'])) : '-' ?></td>
                <td><?= ($vP_l == 0) ? '-' : number_format($vP_l, 2, ',', '.') ?></td>
                <td><?= ($v04_l == 0) ? '-' : number_format($v04_l, 2, ',', '.') ?></td>
                <td><?= ($v20_l == 0) ? '-' : number_format($v20_l, 2, ',', '.') ?></td>
                <td><span class="badge"><?= ($vT_l == 0 && $isEmptyList) ? '-' : number_format($vT_l, 2, ',', '.') . ' h' ?></span></td>
                <td style="text-align:right;"><a href="/?route=module/workinghours&edit=<?= $w['id'] ?>">✎</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="sum-row">
                <td colspan="8" align="right">Summe:</td> <td><?= number_format($sumP, 2, ',', '.') ?></td>
                <td><?= number_format($sum04, 2, ',', '.') ?></td>
                <td><?= number_format($sum20, 2, ',', '.') ?></td>
                <td><span class="badge" style="background:#1e40af; color:white;"><?= number_format($sumT, 2, ',', '.') ?> h</span></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <?php if(!empty($list) && $filter_emp): ?>
        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
            <a href="/?route=module/workinghours&export=pdf&f_emp=<?= $filter_emp ?>&f_from=<?= $filter_from ?>&f_to=<?= $filter_to ?>" target="_blank" class="btn-action" style="background:#ef4444; color:white;">📄 PDF (Mindestlohn)</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
<script>
function setLastMonth() {
    document.getElementById('f_from').value = "<?= $lastMonthStart ?>";
    document.getElementById('f_to').value = "<?= $lastMonthEnd ?>";
}
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".dt-picker", { enableTime: true, dateFormat: "d.m.Y H:i", time_24hr: true, locale: "de" });
    flatpickr(".dt-picker-date", { enableTime: false, dateFormat: "d.m.Y", locale: "de" });
});
</script>