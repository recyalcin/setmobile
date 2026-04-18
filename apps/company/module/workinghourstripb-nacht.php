<?php
/**
 * module/workinghourstripb-nacht.php
 * SEQUENTIAL COMPLIANCE MASTER ENGINE v2026.Z
 * Schicht-Fokus: Nacht (13:00 - 11:00 Folgetag)
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

        // --- Hilfsfunktionen für die Pipeline ---

        // Lädt Daten für einen Fahrer komplett neu aus der DB (Cache-Refresh)
        $sync = function($empId, $monthStr) use ($db) {
            $stmt = $db->prepare("SELECT * FROM workinghours WHERE employeeid = ? AND date LIKE ? ORDER BY date ASC");
            $stmt->execute([$empId, $monthStr . '-%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        // Speichert eine Zeile zurück
        $save = function($row) use ($db) {
            $stmt = $db->prepare("UPDATE workinghours SET
                workstartat=?, workendat=?, breakduration=?, startkm=?, endkm=?,
                hourstotal=?, firsttripat=?, lasttripat=?, recordedat=NOW()
                WHERE id=?");
            $stmt->execute([
                $row['workstartat'], $row['workendat'], $row['breakduration'],
                $row['startkm'], $row['endkm'], $row['hourstotal'],
                $row['firsttripat'], $row['lasttripat'], $row['id']
            ]);
        };

        // Überlappungs-Rechner
        $calcOverlap = function($start, $end, $winS, $winE) {
            if (!$start || !$end) return 0;
            $s = strtotime($start); $e = strtotime($end);
            $ws = strtotime($winS); $we = strtotime($winE);
            return max(0, min($e, $we) - max($s, $ws)) / 3600;
        };

        // Fahrer-Liste laden
        $employees = $db->query("
            SELECT e.id as emp_id, d.id as driver_id, e.maxweeklyhours 
            FROM employee e 
            JOIN driver d ON e.personid = d.personid
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($employees as $emp) {
            $empId = $emp['emp_id'];

            // 1. VORBEREITUNG: Initialisierung/Reset
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $db->prepare("INSERT INTO workinghours (employeeid, date, hourstotal) VALUES (?, ?, 0)
                    ON CONFLICT (employeeid, date) DO UPDATE SET workstartat=NULL, workendat=NULL, breakduration=0, startkm=0, endkm=0, hourstotal=0, firsttripat=NULL, lasttripat=NULL, recordedat=NULL")
                    ->execute([$empId, $date]);
            }

            // STEP: FIRSTTRIPAT (Window 13:00 - 11:00 Folgetag)
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                $winS = $row['date'] . " 13:00:00";
                $winE = date('Y-m-d', strtotime($row['date'] . " +1 day")) . " 11:00:00";
                $st = $db->prepare("SELECT MIN(submittedat) FROM trip WHERE driverid = ? AND submittedat BETWEEN ? AND ?");
                $st->execute([$emp['driver_id'], $winS, $winE]);
                $row['firsttripat'] = $st->fetchColumn() ?: null;
                $save($row);
            }

            // STEP: LASTTRIPAT (Window 13:00 - 11:00 Folgetag)
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                $winS = $row['date'] . " 13:00:00";
                $winE = date('Y-m-d', strtotime($row['date'] . " +1 day")) . " 11:00:00";
                $st = $db->prepare("SELECT MAX(COALESCE(arrivedat, submittedat)) FROM trip WHERE driverid = ? AND submittedat BETWEEN ? AND ?");
                $st->execute([$emp['driver_id'], $winS, $winE]);
                $row['lasttripat'] = $st->fetchColumn() ?: null;
                $save($row);
            }

            // STEP: MONATSGRENZE VORMONAT (Ausschließen)
            $data = $sync($empId, $selectedMonth);
            $prevMonthDay = date('Y-m-t', strtotime($selectedMonth . " -1 month"));
            $st = $db->prepare("SELECT lasttripat FROM workinghours WHERE employeeid = ? AND date = ?");
            $st->execute([$empId, $prevMonthDay]);
            $prevLast = $st->fetchColumn();
            if ($prevLast && date('Y-m-d', strtotime($prevLast)) == $selectedMonth.'-01') {
                if ($data[0]['firsttripat'] < $prevLast) $data[0]['firsttripat'] = $prevLast;
            }
            $save($data[0]);

            // STEP: RUNDEN (0.5h Basis)
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if ($row['firsttripat'] && $row['lasttripat']) {
                    $row['workstartat'] = date('Y-m-d H:i:s', round((strtotime($row['firsttripat']) - 900) / 1800) * 1800);
                    $row['workendat']   = date('Y-m-d H:i:s', round((strtotime($row['lasttripat']) + 900) / 1800) * 1800);
                }
                $save($row);
            }

            // STEP: TAMPON (Nachtschicht 13:00 - 11:00)
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if (!$row['workstartat']) continue;
                $d = $row['date'];
                $limS = strtotime("$d 13:00:00");
                $limE = strtotime(date('Y-m-d', strtotime($d . " +1 day")) . " 11:00:00");
                if (strtotime($row['workstartat']) < $limS) $row['workstartat'] = date('Y-m-d H:i:s', $limS);
                if (strtotime($row['workendat']) > $limE)   $row['workendat']   = date('Y-m-d H:i:s', $limE);
                $save($row);
            }

            // STEP: 24h CHECK
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if ($row['workstartat'] && $row['workendat']) {
                    if (strtotime($row['workendat']) - strtotime($row['workstartat']) > 86400) {
                        $row['workendat'] = date('Y-m-d H:i:s', strtotime($row['workendat']) - 86400);
                    }
                }
                $save($row);
            }

            // STEP: 6+1 REGEL (Iterativ)
            $violation = true;
            while ($violation) {
                $violation = false;
                $data = $sync($empId, $selectedMonth);
                $streak = 0; $lastFreeIdx = -1;
                foreach ($data as $idx => $row) {
                    if ($row['workstartat']) {
                        $streak++;
                        if ($streak == 7) {
                            $violation = true;
                            $targetId = ($lastFreeIdx != -1 && isset($data[$lastFreeIdx + 7])) ? $data[$lastFreeIdx + 7]['id'] : null;
                            if (!$targetId) {
                                $minD = 999999;
                                for ($k = $idx - 6; $k <= $idx; $k++) {
                                    $dur = strtotime($data[$k]['workendat']) - strtotime($data[$k]['workstartat']);
                                    if ($dur < $minD) { $minD = $dur; $targetId = $data[$k]['id']; }
                                }
                            }
                            $db->prepare("UPDATE workinghours SET workstartat=NULL, workendat=NULL, hourstotal=0, breakduration=0, startkm=0, endkm=0 WHERE id=?")->execute([$targetId]);
                            break; 
                        }
                    } else { $streak = 0; $lastFreeIdx = $idx; }
                }
            }

            // STEP: 11h RUHEPAUSE
            $data = $sync($empId, $selectedMonth);
            for ($i = 1; $i < count($data); $i++) {
                if ($data[$i-1]['workendat'] && $data[$i]['workstartat']) {
                    $rest = (strtotime($data[$i]['workstartat']) - strtotime($data[$i-1]['workendat'])) / 3600;
                    if ($rest < 11) {
                        $diff = (11 - $rest) * 3600;
                        $data[$i-1]['workendat'] = date('Y-m-d H:i:s', strtotime($data[$i-1]['workendat']) - ($diff/2));
                        $data[$i]['workstartat'] = date('Y-m-d H:i:s', strtotime($data[$i]['workstartat']) + ($diff/2));
                        if (strtotime($data[$i]['workendat']) < strtotime($data[$i]['workstartat'])) {
                             $plus = strtotime($data[$i]['workstartat']) - strtotime($data[$i]['workendat']);
                             $data[$i]['workendat'] = date('Y-m-d H:i:s', strtotime($data[$i]['workendat']) + $diff + $plus);
                        }
                        $save($data[$i-1]); $save($data[$i]);
                        $data = $sync($empId, $selectedMonth);
                    }
                }
            }

            // STEP: NACHTSTUNDEN (00-04, 20-06)
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if (!$row['workstartat']) continue;
                $s = $row['workstartat']; $e = $row['workendat'];
                $d1 = $row['date']; $d2 = date('Y-m-d', strtotime($d1 . " +1 day"));
                $h0004 = $calcOverlap($s, $e, "$d1 00:00:00", "$d1 04:00:00") + $calcOverlap($s, $e, "$d2 00:00:00", "$d2 04:00:00");
                $h2024 = $calcOverlap($s, $e, "$d1 20:00:00", "$d1 23:59:59");
                $h0406 = $calcOverlap($s, $e, "$d1 04:00:00", "$d1 06:00:00") + $calcOverlap($s, $e, "$d2 04:00:00", "$d2 06:00:00");
                $row['startkm'] = min(4, $h0004);
                $row['hours2024'] = $h2024; // Hilfsvariable
                $row['endkm'] = min(6, $h2024 + $h0406);
                $row['hourstotal'] = (strtotime($e) - strtotime($s)) / 3600;
                $save($row);
            }

            // STEP: MULTIPLIER
            $data = $sync($empId, $selectedMonth);
            $workDays = array_filter($data, fn($r) => $r['workstartat'] != null);
            if (count($workDays) > 0) {
                $avgHT = array_sum(array_column($workDays, 'hourstotal')) / count($workDays);
                $target = ($emp['maxweeklyhours'] * 52 / 365);
                $multiplier = ($avgHT > 0) ? ($target / $avgHT) * (0.95 + 0.1 * (mt_rand() / mt_getrandmax())) : 1;
                foreach ($data as $row) {
                    if ($row['workstartat']) {
                        $row['hourstotal'] *= $multiplier;
                        $row['startkm'] *= $multiplier;
                        $row['endkm'] *= $multiplier;
                    }
                    $save($row);
                }
            }

            // STEP: NACHTSTUNDEN PRÜFEN & BREAKS
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if (!$row['workstartat']) continue;
                $s = $row['workstartat']; $e = $row['workendat'];
                $d1 = $row['date']; $d2 = date('Y-m-d', strtotime($d1 . " +1 day"));
                $max04 = $calcOverlap($s, $e, "$d1 00:00:00", "$d1 04:00:00") + $calcOverlap($s, $e, "$d2 00:00:00", "$d2 04:00:00");
                $max2006 = $calcOverlap($s, $e, "$d1 20:00:00", "$d1 23:59:59") + $calcOverlap($s, $e, "$d1 04:00:00", "$d1 06:00:00") + $calcOverlap($s, $e, "$d2 04:00:00", "$d2 06:00:00");
                if ($row['startkm'] > $max04) $row['startkm'] = $max04;
                if ($row['endkm'] > $max2006) $row['endkm'] = $max2006;
                $row['breakduration'] = ((strtotime($e) - strtotime($s)) / 3600) - $row['hourstotal'];
                $save($row);
            }

            // STEP: RUNDEN 0.25
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                $row['hourstotal'] = round($row['hourstotal'] * 4) / 4;
                $row['breakduration'] = round($row['breakduration'] * 4) / 4;
                $row['startkm'] = round($row['startkm'] * 4) / 4;
                $row['endkm'] = round($row['endkm'] * 4) / 4;
                $save($row);
            }

            // STEP: <4h FIX & 10h CAP
            $data = $sync($empId, $selectedMonth);
            foreach ($data as $row) {
                if (!$row['workstartat']) continue;
                if ($row['hourstotal'] < 4 && $row['breakduration'] > 0) {
                    $row['hourstotal'] += $row['breakduration'];
                    $row['breakduration'] = 0;
                }
                if ($row['hourstotal'] > 10) $row['hourstotal'] = 10;
                $row['breakduration'] = ((strtotime($row['workendat']) - strtotime($row['workstartat'])) / 3600) - $row['hourstotal'];
                $save($row);
            }
        }

        $db->commit();
        $message = "<div style='background:#f0fdf4; color:#166534; padding:15px; border-radius:10px;'>✅ <b>Engine v2026.Z:</b> Nachtschicht-Pipeline erfolgreich abgeschlossen.</div>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "<div style='background:#fef2f2; color:#991b1b; padding:15px; border-radius:10px;'>❌ <b>Fehler:</b> " . $e->getMessage() . "</div>";
    }
}
?>

<div class="card" style="font-family: sans-serif; border: 1px solid #e2e8f0; border-radius:16px; background:#fff; max-width: 800px; margin: 30px auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
    <div style="background: #0f172a; padding:20px; color:white; border-radius:16px 16px 0 0;">
        <h2 style="margin:0; font-size:1.1rem;">Compliance Master Engine v2026.Z (Night Shift)</h2>
    </div>
    <div style="padding:25px;">
        <?= $message ?? '' ?>
        <form method="post" style="margin-top:20px; display:flex; gap:10px;">
            <input type="month" name="target_month" value="<?= htmlspecialchars($selectedMonth) ?>" style="flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
            <button type="submit" name="run_pipeline" style="background:#2563eb; color:white; border:none; padding:10px 25px; border-radius:8px; font-weight:bold; cursor:pointer;">🚀 Pipeline starten</button>
        </form>
    </div>
</div>