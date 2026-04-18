<?php
/**
 * module/workinghourstrip.php
 * Ultimate Multi-Pass Pipeline (Version 2026.27 - Final Legal Stable)
 * * TEIL A: Datenbank-Integrität & Boundary-Safe 6h-Gap-Analyse
 * TEIL B: 12-STUFEN BERECHNUNGS-PIPELINE (00-04 & 20-06 Fokus)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$selectedMonth = $_POST['target_month'] ?? date('Y-m', strtotime('first day of last month'));

if (isset($_POST['run_pipeline'])) {
    try {
        $queryDate = $selectedMonth . '-%';
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)date('m', strtotime($selectedMonth)), (int)date('Y', strtotime($selectedMonth)));
        
        $firstDayOfMonth = $selectedMonth . '-01';
        $lastDayOfMonth = $selectedMonth . '-' . $daysInMonth;
        $prevMonthDay = date('Y-m-d', strtotime($firstDayOfMonth . ' -1 day'));
        $nextMonthDay = date('Y-m-d', strtotime($lastDayOfMonth . ' +1 day'));

        // --- TEIL A: INITIALISIERUNG & CLEANUP ---

        // 1. Dubletten-Reinigung
        $pdo->prepare("DELETE FROM workinghours WHERE date LIKE ? AND (employeeid, date) IN (SELECT * FROM (SELECT employeeid, date FROM workinghours WHERE date LIKE ? GROUP BY employeeid, date HAVING COUNT(*) > 1) as x)")->execute([$queryDate, $queryDate]);

        // 2. Full Month Init (Nur startkm und endkm Spalten)
        $employees = $pdo->query("SELECT id FROM employee")->fetchAll(PDO::FETCH_COLUMN);
        $initStmt = $pdo->prepare("INSERT INTO workinghours (employeeid, date, firsttripat, lasttripat, workstartat, workendat, hourstotal, startkm, endkm, breakduration) VALUES (?, ?, NULL, NULL, NULL, NULL, 0, 0, 0, 0) ON CONFLICT (employeeid, date) DO UPDATE SET firsttripat=NULL, lasttripat=NULL, workstartat=NULL, workendat=NULL, hourstotal=0, startkm=0, endkm=0, breakduration=0");
        foreach ($employees as $empId) {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $initStmt->execute([$empId, $dateStr]);
            }
        }

        // 3. Gap-Analyse (6h Regel & Same-Day Consolidation)
        $stmt = $pdo->prepare("SELECT t.submittedat, t.arrivedat, e.id AS employee_id FROM trip t JOIN driver d ON t.driverid = d.id JOIN employee e ON d.personid = e.personid WHERE t.submittedat BETWEEN ? AND ? ORDER BY e.id ASC, t.submittedat ASC");
        $stmt->execute([$prevMonthDay . ' 00:00:00', $nextMonthDay . ' 23:59:59']);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $activeShifts = []; 
        foreach ($trips as $t) {
            $eid = $t['employee_id'];
            $f = strtotime($t['submittedat'] ?? '');
            $l = strtotime($t['arrivedat'] ?? ($t['submittedat'] ?? ''));
            if (!$f) continue;
            if (!isset($activeShifts[$eid])) { $activeShifts[$eid] = ['start' => $f, 'end' => $l, 'date' => date('Y-m-d', $f)]; }
            else {
                $gap = $f - $activeShifts[$eid]['end'];
                if ($gap > 21600) {
                    if (date('Y-m-d', $f) === $activeShifts[$eid]['date']) { $activeShifts[$eid]['end'] = max($activeShifts[$eid]['end'], $l); }
                    else {
                        if (date('Y-m', strtotime($activeShifts[$eid]['date'])) === $selectedMonth) {
                            $pdo->prepare("UPDATE workinghours SET firsttripat=?, lasttripat=? WHERE employeeid=? AND date=?")->execute([date('Y-m-d H:i:s', $activeShifts[$eid]['start']), date('Y-m-d H:i:s', $activeShifts[$eid]['end']), $eid, $activeShifts[$eid]['date']]);
                        }
                        $activeShifts[$eid] = ['start' => $f, 'end' => $l, 'date' => date('Y-m-d', $f)];
                    }
                } else { $activeShifts[$eid]['end'] = max($activeShifts[$eid]['end'], $l); }
            }
        }
        foreach ($activeShifts as $eid => $s) {
            if (date('Y-m', strtotime($s['date'])) === $selectedMonth) {
                $pdo->prepare("UPDATE workinghours SET firsttripat=?, lasttripat=? WHERE employeeid=? AND date=?")->execute([date('Y-m-d H:i:s', $s['start']), date('Y-m-d H:i:s', $s['end']), $eid, $s['date']]);
            }
        }

        // --- TEIL B: 12-STUFEN PIPELINE ---

        // 1. Runden (-15/+15)
        $stmt = $pdo->prepare("SELECT id, firsttripat, lasttripat FROM workinghours WHERE date LIKE ? AND firsttripat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tsS = floor((strtotime($r['firsttripat']) - 900) / 1800) * 1800;
            $tsE = ceil((strtotime($r['lasttripat']) + 900) / 1800) * 1800;
            if ($tsE <= $tsS) $tsE += 86400;
            $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=?, hourstotal=? WHERE id=?")->execute([date('Y-m-d H:i:s', (int)$tsS), date('Y-m-d H:i:s', (int)$tsE), ($tsE - $tsS)/3600, $r['id']]);
        }

        // 2. 6 Tage Regel (Null 7., Merge 8.)
        $stmt = $pdo->prepare("SELECT id, employeeid, date, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL ORDER BY employeeid, date ASC");
        $stmt->execute([$queryDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $streak = 0;
        for ($i = 0; $i < count($rows); $i++) {
            if ($i > 0 && $rows[$i]['employeeid'] == $rows[$i-1]['employeeid'] && date('Y-m-d', strtotime($rows[$i-1]['date'].' +1 day')) == $rows[$i]['date']) { $streak++; } else { $streak = 0; }
            if ($streak == 6) {
                if (isset($rows[$i+1]) && $rows[$i+1]['employeeid'] == $rows[$i]['employeeid']) {
                    $newS = min(strtotime($rows[$i]['workstartat']), strtotime($rows[$i+1]['workstartat']));
                    $newE = max(strtotime($rows[$i]['workendat']), strtotime($rows[$i+1]['workendat']));
                    $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=? WHERE id=?")->execute([date('Y-m-d H:i:s', $newS), date('Y-m-d H:i:s', $newE), $rows[$i+1]['id']]);
                }
                $pdo->prepare("UPDATE workinghours SET workstartat=NULL, workendat=NULL, hourstotal=0, startkm=0, endkm=0 WHERE id=?")->execute([$rows[$i]['id']]);
                $streak = -1;
            }
        }

        // 3. 11h Ruhepause (Split)
        $stmt = $pdo->prepare("SELECT id, employeeid, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL ORDER BY employeeid, workstartat ASC");
        $stmt->execute([$queryDate]);
        $rowsRest = $stmt->fetchAll(PDO::FETCH_ASSOC);
        for ($i = 1; $i < count($rowsRest); $i++) {
            if ($rowsRest[$i]['employeeid'] == $rowsRest[$i-1]['employeeid']) {
                $gap = strtotime($rowsRest[$i]['workstartat']) - strtotime($rowsRest[$i-1]['workendat']);
                if ($gap < 39600) {
                    $diff = (39600 - $gap);
                    $newE = strtotime($rowsRest[$i-1]['workendat']) - ($diff / 2);
                    $newS = strtotime($rowsRest[$i]['workstartat']) + ($diff / 2);
                    $pdo->prepare("UPDATE workinghours SET workendat=?, hourstotal=(EXTRACT(EPOCH FROM workendat)-EXTRACT(EPOCH FROM workstartat))/3600 WHERE id=?")->execute([date('Y-m-d H:i:s', $newE), $rowsRest[$i-1]['id']]);
                    $pdo->prepare("UPDATE workinghours SET workstartat=?, hourstotal=(EXTRACT(EPOCH FROM workendat)-EXTRACT(EPOCH FROM workstartat))/3600 WHERE id=?")->execute([date('Y-m-d H:i:s', $newS), $rowsRest[$i]['id']]);
                    $rowsRest[$i]['workstartat'] = date('Y-m-d H:i:s', $newS);
                }
            }
        }

        // 4 & 5. Nachtstunden (Gesetzliche Logik ohne hours2009)
        $stmt = $pdo->prepare("SELECT * FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tsS = strtotime($r['workstartat']); $tsE = strtotime($r['workendat']);
            $m0 = strtotime($r['date'].' +1 day 00:00:00'); $m4 = strtotime($r['date'].' +1 day 04:00:00');
            $t20 = strtotime($r['date'].' 20:00:00'); $m06 = strtotime($r['date'].' +1 day 06:00:00');
            $logicalMidnight = strtotime($r['date'] . ' 24:00:00');

            // 4. 0004stunden
            $raw04 = ($tsS < $logicalMidnight) ? max(0, min($tsE, $m4) - max($tsS, $m0)) / 3600 : 0;

            // 5. 2006stunden
            $raw206Total = max(0, min($tsE, $m06) - max($tsS, $t20)) / 3600;
            if ($tsS < $logicalMidnight) {
                $raw206 = max(0, $raw206Total - $raw04);
            } else {
                $raw206 = $raw206Total;
            }

            $pdo->prepare("UPDATE workinghours SET startkm=?, endkm=? WHERE id=?")->execute([$raw04, $raw206, $r['id']]);
        }

        // 6. Multiplier & Capping
        $stmt = $pdo->prepare("SELECT w.*, e.maxweeklyhours FROM workinghours w JOIN employee e ON w.employeeid = e.id WHERE w.date LIKE ? AND w.workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        $rowsFinal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sums = []; foreach($rowsFinal as $r) { @$sums[$r['employeeid']]['t'] += $r['hourstotal']; @$sums[$r['employeeid']]['d']++; }
        foreach ($rowsFinal as $r) {
            $mWh = (float)($r['maxweeklyhours'] ?? 40);
            $random = 0.95 + (mt_rand() / mt_getrandmax() * 0.1);
            $istTag = $sums[$r['employeeid']]['t'] / max(1, $sums[$r['employeeid']]['d']);
            $mult = ($istTag > 0) ? (($mWh * 52 / 365) / $istTag) * $random : 1;
            
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $newT = ($r['hourstotal'] * $mult >= 4) ? ($r['hourstotal'] * $mult) : $r['hourstotal'];
            
            $new04 = min($r['startkm'], $r['startkm'] * $mult);
            $new206 = min($r['endkm'], $r['endkm'] * $mult);

            $pdo->prepare("UPDATE workinghours SET hourstotal=?, startkm=?, endkm=?, breakduration=? WHERE id=?")
                ->execute([$newT, $new04, $new206, $pres - $newT, $r['id']]);
        }

        // 7. Kompensation < 4h
        $pdo->prepare("UPDATE workinghours SET hourstotal = hourstotal + (breakduration / 2), breakduration = breakduration - (breakduration / 2) WHERE date LIKE ? AND workstartat IS NOT NULL AND hourstotal < 4 AND breakduration > 0")->execute([$queryDate]);

        // 8. Max 10h Regel
        $pdo->prepare("UPDATE workinghours SET breakduration = breakduration + (hourstotal - 10), hourstotal = 10 WHERE date LIKE ? AND workstartat IS NOT NULL AND hourstotal > 10")->execute([$queryDate]);

        // 9. Pause (0.5/1.0h)
        $stmt = $pdo->prepare("SELECT id, hourstotal, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $required = ($r['hourstotal'] >= 9) ? 1.0 : 0.5;
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $pdo->prepare("UPDATE workinghours SET breakduration = ?, hourstotal = ? WHERE id=?")->execute([$required, $pres - $required, $r['id']]);
        }

        // 10 & 11. Sanity Check
        $stmt = $pdo->prepare("SELECT * FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $sumErr = abs($pres - ($r['hourstotal'] + $r['breakduration'])) > 0.01;
            if ($r['hourstotal'] < 0 || $r['breakduration'] < 0 || $sumErr) {
                $tsS = floor((strtotime($r['firsttripat']) - 900) / 1800) * 1800; $tsE = ceil((strtotime($r['lasttripat']) + 900) / 1800) * 1800;
                if ($tsE <= $tsS) $tsE += 86400; $pFb = ($tsE - $tsS) / 3600; $t = min($pFb / 2, 5.5);
                $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=?, hourstotal=?, breakduration=? WHERE id=?")
                    ->execute([date('Y-m-d H:i:s', (int)$tsS), date('Y-m-d H:i:s', (int)$tsE), $t, $pFb - $t, $r['id']]);
            }
        }

        // 12. Runden (0.25h)
        $stmt = $pdo->prepare("SELECT * FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $t = round($r['hourstotal'] / 0.25) * 0.25;
            $pdo->prepare("UPDATE workinghours SET hourstotal=?, startkm=round(startkm/0.25)*0.25, endkm=round(endkm/0.25)*0.25, breakduration=? WHERE id=?")
                ->execute([$t, $pres - $t, $r['id']]);
        }

        $message = "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:10px; border:1px solid #bbf7d0;'>✅ <b>Master-Pipeline v2026.27 abgeschlossen.</b> Nachtstunden ohne hours2009 erfolgreich berechnet.</div>";
    } catch (Exception $e) { $message = "<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:10px; border:1px solid #fecaca;'>❌ <b>Fehler:</b> " . $e->getMessage() . "</div>"; }
}
?>

<div class="card" style="font-family: 'Segoe UI', sans-serif; border: 1px solid #e2e8f0; border-radius:12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); background:#fff; overflow:hidden;">
    <div style="background:#059669; padding:20px; color:white;">
        <h2 style="margin:0; font-size:1.25rem;">🕒 Master Multi-Pass Pipeline v2026.27</h2>
        <p style="margin:5px 0 0 0; font-size:0.85rem; opacity:0.9;">Compliance-Berechnung (00-04 & 20-06) | Ohne hours2009</p>
    </div>
    <div style="padding:25px;">
        <?= $message ?>
        <form method="post" style="display:flex; gap:15px; align-items:center; margin-top:15px;">
            <input type="month" name="target_month" value="<?= $selectedMonth ?>" style="padding:10px; border:1px solid #cbd5e1; border-radius:6px; outline:none; color:#334155;">
            <button type="submit" name="run_pipeline" style="background:#059669; color:white; border:none; padding:11px 25px; border-radius:6px; font-weight:bold; cursor:pointer; transition:0.2s;">
                🚀 Pipeline starten
            </button>
        </form>
    </div>
</div>