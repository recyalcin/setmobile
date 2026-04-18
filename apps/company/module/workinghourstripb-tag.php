<?php
/**
 * module/workinghourstripb.php
 * SEQUENTIAL COMPLIANCE MASTER ENGINE v2026.Z
 */

global $pdo;
$db = $pdo;

if (!$db instanceof PDO) {
    die("Fehler: Datenbankverbindung (PDO) nicht gefunden.");
}

$selectedMonth = $_POST['target_month'] ?? date('Y-m', strtotime('first day of last month'));

if (isset($_POST['run_pipeline'])) {
    try {
        $db->beginTransaction();

        $year = (int)date('Y', strtotime($selectedMonth));
        $month = (int)date('m', strtotime($selectedMonth));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // --- Hilfsfunktion: Überlappung berechnen ---
        $calcOverlap = function($start, $end, $winS, $winE) {
            return max(0, min($end, $winE) - max($start, $winS)) / 3600;
        };

        // --- BASIS-DATEN LADEN ---
        $employees = $db->query("
            SELECT e.id as emp_id, d.id as driver_id, s.name as schicht_name, e.maxweeklyhours
            FROM employee e 
            JOIN driver d ON e.personid = d.personid
            LEFT JOIN workinghoursschicht s ON e.workinghoursschichtid = s.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        // =========================================================================
        // VORBEREITUNG: MATRIX INITIALISIERUNG
        // =========================================================================
        foreach ($employees as $emp) {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $currentDate = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $db->prepare("
                    INSERT INTO workinghours (employeeid, date, hourstotal)
                    VALUES (?, ?, 0)
                    ON CONFLICT (employeeid, date) DO UPDATE SET
                    workstartat=NULL, workendat=NULL, firsttripat=NULL, lasttripat=NULL,
                    breakduration=0, startkm=0, endkm=0, hourstotal=0, recordedat=NULL
                ")->execute([$emp['emp_id'], $currentDate]);
            }
        }

        // =========================================================================
        // STEP 1 & 2: FIRST- & LASTTRIPAT
        // =========================================================================
        foreach ($employees as $emp) {
            $rows = $db->prepare("SELECT id, date FROM workinghours WHERE employeeid = ? AND date LIKE ?");
            $rows->execute([$emp['emp_id'], $selectedMonth . '-%']);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $startWin = ($emp['schicht_name'] === '12:00 - 11:59') ? $row['date'].' 12:00:00' : $row['date'].' 00:00:00';
                $endWin   = ($emp['schicht_name'] === '12:00 - 11:59') ? date('Y-m-d', strtotime($row['date'].' + 1 day')).' 11:59:59' : $row['date'].' 23:59:59';

                $f = $db->prepare("SELECT MIN(submittedat) FROM trip WHERE driverid = ? AND submittedat BETWEEN ? AND ?");
                $f->execute([$emp['driver_id'], $startWin, $endWin]);
                $fTrip = $f->fetchColumn();

                $l = $db->prepare("SELECT MAX(COALESCE(arrivedat, submittedat)) FROM trip WHERE driverid = ? AND submittedat BETWEEN ? AND ?");
                $l->execute([$emp['driver_id'], $startWin, $endWin]);
                $lTrip = $l->fetchColumn();

                $db->prepare("UPDATE workinghours SET firsttripat = ?, lasttripat = ? WHERE id = ?")->execute([$fTrip, $lTrip, $row['id']]);
            }
        }

        // =========================================================================
        // STEP 3 & 4: ÜBERHANG-MANAGEMENT
        // =========================================================================
        $prevMonthLast = date('Y-m-t', strtotime($selectedMonth . " -1 month"));
        foreach ($employees as $emp) {
            $p = $db->prepare("SELECT lasttripat FROM workinghours WHERE employeeid = ? AND date = ?");
            $p->execute([$emp['emp_id'], $prevMonthLast]);
            $prevE = $p->fetchColumn();
            if ($prevE && date('Y-m-d', strtotime($prevE)) == $selectedMonth.'-01') {
                $db->prepare("UPDATE workinghours SET firsttripat = ? WHERE employeeid = ? AND date = ? AND (firsttripat < ? OR firsttripat IS NULL)")
                   ->execute([$prevE, $emp['emp_id'], $selectedMonth.'-01', $prevE]);
            }
        }

        // =========================================================================
        // STEP 5: RUNDUNG (0.5h Basis)
        // =========================================================================
        $db->prepare("UPDATE workinghours SET
            workstartat = to_timestamp(ROUND((EXTRACT(EPOCH FROM firsttripat) - 900) / 1800) * 1800),
            workendat = to_timestamp(ROUND((EXTRACT(EPOCH FROM lasttripat) + 900) / 1800) * 1800)
            WHERE date LIKE ? AND firsttripat IS NOT NULL")->execute([$selectedMonth . '-%']);

        // =========================================================================
        // STEP 6 & 7: TAMPON & 24h CHECK
        // =========================================================================
        $rows = $db->prepare("SELECT id, workstartat, workendat, date, employeeid FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $rows->execute([$selectedMonth . '-%']);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $newS = $r['workstartat']; $newE = $r['workendat'];
            if ((strtotime($newE) - strtotime($newS)) > 86400) { $newE = date('Y-m-d H:i:s', strtotime($newE) - 86400); }
            $empInfo = current(array_filter($employees, fn($e) => $e['emp_id'] == $r['employeeid']));
            if ($empInfo['schicht_name'] === '12:00 - 11:59') {
                $limS = $r['date'].' 13:00:00'; $limE = date('Y-m-d', strtotime($r['date'].' +1 day')).' 11:00:00';
            } else {
                $limS = $r['date'].' 01:00:00'; $limE = $r['date'].' 23:00:00';
            }
            if ($newS < $limS) $newS = $limS; if ($newE > $limE) $newE = $limE;
            $db->prepare("UPDATE workinghours SET workstartat = ?, workendat = ? WHERE id = ?")->execute([$newS, $newE, $r['id']]);
        }

        // =========================================================================
        // STEP 8: ITERATIVE 6+1 REGEL
        // =========================================================================
        foreach ($employees as $emp) {
            $violation = true;
            while ($violation) {
                $violation = false;
                $load = $db->prepare("SELECT id, workstartat, workendat FROM workinghours WHERE employeeid = ? AND date LIKE ? ORDER BY date ASC");
                $load->execute([$emp['emp_id'], $selectedMonth . '-%']);
                $days = $load->fetchAll(PDO::FETCH_ASSOC);
                $streak = []; $lastFreeIdx = -1;
                foreach ($days as $idx => $d) {
                    if ($d['workstartat']) {
                        $streak[] = $d;
                        if (count($streak) == 7) {
                            $violation = true;
                            $targetId = ($lastFreeIdx !== -1) ? $d['id'] : null;
                            if (!$targetId) {
                                $minD = 9999999;
                                foreach ($streak as $s) {
                                    $dur = strtotime($s['workendat']) - strtotime($s['workstartat']);
                                    if ($dur < $minD) { $minD = $dur; $targetId = $s['id']; }
                                }
                            }
                            $db->prepare("UPDATE workinghours SET workstartat=NULL, workendat=NULL, breakduration=0, startkm=0, endkm=0, hourstotal=0, recordedat=NULL WHERE id = ?")->execute([$targetId]);
                            break; 
                        }
                    } else { $streak = []; $lastFreeIdx = $idx; }
                }
            }
        }

        // =========================================================================
        // STEP 9: 11h RUHEPAUSE & PLUSSTUNDEN
        // =========================================================================
        foreach ($employees as $emp) {
            $load = $db->prepare("SELECT id, workstartat, workendat FROM workinghours WHERE employeeid = ? AND date LIKE ? ORDER BY date ASC");
            $load->execute([$emp['emp_id'], $selectedMonth . '-%']);
            $days = $load->fetchAll(PDO::FETCH_ASSOC);
            for ($i = 1; $i < count($days); $i++) {
                $v = $days[$i-1]; $a = $days[$i];
                if (!$v['workendat'] || !$a['workstartat']) continue;
                $rest = (strtotime($a['workstartat']) - strtotime($v['workendat'])) / 3600;
                if ($rest < 11) {
                    $fehl = (11 - $rest) * 3600;
                    $newVEnd = date('Y-m-d H:i:s', strtotime($v['workendat']) - ($fehl/2));
                    $newAStart = date('Y-m-d H:i:s', strtotime($a['workstartat']) + ($fehl/2));
                    $newAEnd = $a['workendat'];
                    if (strtotime($newAEnd) < strtotime($newAStart)) {
                        $plus = strtotime($a['workstartat']) - strtotime($a['workendat']);
                        $newAEnd = date('Y-m-d H:i:s', strtotime($newAEnd) + $fehl + $plus);
                    }
                    $db->prepare("UPDATE workinghours SET workendat=? WHERE id=?")->execute([$newVEnd, $v['id']]);
                    $db->prepare("UPDATE workinghours SET workstartat=?, workendat=? WHERE id=?")->execute([$newAStart, $newAEnd, $a['id']]);
                    $days[$i]['workstartat'] = $newAStart; $days[$i]['workendat'] = $newAEnd;
                }
            }
        }

        // =========================================================================
        // STEP 10-13: NACHTSTUNDEN & MULTIPLIER
        // =========================================================================
        foreach ($employees as $emp) {
            $load = $db->prepare("SELECT * FROM workinghours WHERE employeeid = ? AND date LIKE ? AND workstartat IS NOT NULL");
            $load->execute([$emp['emp_id'], $selectedMonth . '-%']);
            $workDays = $load->fetchAll(PDO::FETCH_ASSOC);
            if (empty($workDays)) continue;

            $totalHT_Pre = 0; 
            foreach ($workDays as $wd) {
                $s = strtotime($wd['workstartat']); $e = strtotime($wd['workendat']); $d = date('Y-m-d', $s);
                $w0004 = [[$d." 00:00:00", $d." 04:00:00"], [date('Y-m-d', strtotime($d.' + 1 day'))." 00:00:00", date('Y-m-d', strtotime($d.' + 1 day'))." 04:00:00"]];
                $w2024 = [[$d." 20:00:00", $d." 23:59:59"]];
                $w0406 = [[$d." 04:00:00", $d." 06:00:00"], [date('Y-m-d', strtotime($d.' + 1 day'))." 04:00:00", date('Y-m-d', strtotime($d.' + 1 day'))." 06:00:00"]];
                
                $h0004 = 0; foreach($w0004 as $w) $h0004 += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1]));
                $h2024 = 0; foreach($w2024 as $w) $h2024 += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1])+1);
                $h0406 = 0; foreach($w0406 as $w) $h0406 += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1]));
                
                $curHT = ($e - $s) / 3600;
                $totalHT_Pre += $curHT;
                $db->prepare("UPDATE workinghours SET startkm=?, endkm=?, hourstotal=? WHERE id=?")
                   ->execute([min(4, $h0004), min(6, $h2024 + $h0406), $curHT, $wd['id']]);
            }

            $avgCurrent = $totalHT_Pre / count($workDays);
            $targetDaily = ($emp['maxweeklyhours'] * 52 / 365);
            $randomFactor = 0.95 + (mt_rand() / mt_getrandmax() * 0.1); 
            $multiplier = ($avgCurrent > 0) ? ($targetDaily / $avgCurrent) * $randomFactor : 1;

            $load2 = $db->prepare("SELECT * FROM workinghours WHERE employeeid = ? AND date LIKE ? AND workstartat IS NOT NULL");
            $load2->execute([$emp['emp_id'], $selectedMonth . '-%']);
            foreach ($load2->fetchAll(PDO::FETCH_ASSOC) as $wd2) {
                $newHT = $wd2['hourstotal'] * $multiplier;
                $newH0004 = $wd2['startkm'] * $multiplier;
                $newH2006 = $wd2['endkm'] * $multiplier;
                
                $s = strtotime($wd2['workstartat']); $e = strtotime($wd2['workendat']); $d = date('Y-m-d', $s);
                $h0004m = 0; foreach([[$d." 00:00:00", $d." 04:00:00"], [date('Y-m-d', strtotime($d.' + 1 day'))." 00:00:00", date('Y-m-d', strtotime($d.' + 1 day'))." 04:00:00"]] as $w) $h0004m += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1]));
                $h2024m = 0; foreach([[$d." 20:00:00", $d." 23:59:59"]] as $w) $h2024m += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1])+1);
                $h0406m = 0; foreach([[$d." 04:00:00", $d." 06:00:00"], [date('Y-m-d', strtotime($d.' + 1 day'))." 04:00:00", date('Y-m-d', strtotime($d.' + 1 day'))." 06:00:00"]] as $w) $h0406m += $calcOverlap($s, $e, strtotime($w[0]), strtotime($w[1]));
                
                if($newH0004 > $h0004m) $newH0004 = $h0004m;
                if($newH2006 > ($h2024m + $h0406m)) $newH2006 = ($h2024m + $h0406m);

                $db->prepare("UPDATE workinghours SET hourstotal=?, startkm=?, endkm=? WHERE id=?")
                   ->execute([$newHT, $newH0004, $newH2006, $wd2['id']]);
            }
        }

        // =========================================================================
        // FINAL ADJUSTMENTS (0.25 Rounding, Short Days, 10h Cap)
        // =========================================================================
        $final = $db->prepare("SELECT * FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $final->execute([$selectedMonth . '-%']);
        foreach ($final->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $ht = $f['hourstotal'];
            $s = strtotime($f['workstartat']); $e = strtotime($f['workendat']);
            $bd = (($e - $s) / 3600) - $ht;
            if ($ht < 4 && $bd > 0) { $ht += ($bd / 2); }
            if ($ht > 10) { $ht = 10.0; }
            $newBD = (($e - $s) / 3600) - $ht;

            $db->prepare("UPDATE workinghours SET
                hourstotal = ROUND($ht * 4) / 4,
                breakduration = ROUND($newBD * 4) / 4,
                startkm = ROUND(startkm * 4) / 4,
                endkm = ROUND(endkm * 4) / 4,
                workstartat = to_timestamp(ROUND(EXTRACT(EPOCH FROM workstartat) / 900) * 900),
                workendat = to_timestamp(ROUND(EXTRACT(EPOCH FROM workendat) / 900) * 900)
                WHERE id = ?")->execute([$f['id']]);
        }

        $db->commit();
        $message = "<div style='background:#f0fdf4; color:#166534; padding:15px; border-radius:10px;'>✅ <b>Engine v2026.Z:</b> Pipeline erfolgreich abgeschlossen.</div>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "<div style='background:#fef2f2; color:#991b1b; padding:15px; border-radius:10px;'>❌ <b>Fehler:</b> " . $e->getMessage() . "</div>";
    }
}
?>

<div class="card" style="font-family: sans-serif; border: 1px solid #e2e8f0; border-radius:16px; background:#fff; max-width: 800px; margin: 30px auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
    <div style="background: #0f172a; padding:20px; color:white; border-radius:16px 16px 0 0;">
        <h2 style="margin:0; font-size:1.1rem;">Compliance Master Engine v2026.Z</h2>
    </div>
    <div style="padding:25px;">
        <?= $message ?>
        <form method="post" style="margin-top:20px; display:flex; gap:10px;">
            <input type="month" name="target_month" value="<?= htmlspecialchars($selectedMonth) ?>" style="flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            <button type="submit" name="run_pipeline" style="background:#2563eb; color:white; border:none; padding:10px 25px; border-radius:8px; font-weight:bold; cursor:pointer;">🚀 Pipeline starten</button>
        </form>
    </div>
</div>