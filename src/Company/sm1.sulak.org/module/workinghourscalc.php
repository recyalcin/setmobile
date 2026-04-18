<?php
/**
 * module/workinghourscalc.php - High-End Multi-Pass Pipeline (10 Stufen)
 * Vorgehensweise: Reset -> Schritt berechnen -> Speichern -> Neu laden -> Nächster Schritt
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$selectedMonth = $_POST['target_month'] ?? date('Y-m');

if (isset($_POST['calculate_workinghours'])) {
    try {
        $queryDate = $selectedMonth . '-%';

        // --- SCHRITT 0: RESET (Vermeidung von 1970-Leichen & Geisterdaten) ---
        $pdo->prepare("UPDATE workinghours SET workstartat=NULL, workendat=NULL, hourstotal=0, hours0004=0, hours2006=0, breakduration=0 WHERE date LIKE ?")->execute([$queryDate]);

        // --- 1. RUNDEN AUF HALBE STUNDEN (BASIS) ---
        $stmt = $pdo->prepare("SELECT id, firsttripat, lasttripat FROM workinghours WHERE date LIKE ? AND firsttripat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $s = strtotime($r['firsttripat'] ?? '');
            $e = strtotime($r['lasttripat'] ?? '');
            if ($s > 0 && $e > 0) {
                $tsS = (float)(round(($s - 900) / 1800) * 1800);
                $tsE = (float)(round(($e + 900) / 1800) * 1800);
                if ($tsE <= $tsS) $tsE += 86400;
                $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=?, hourstotal=? WHERE id=?")->execute([
                    date('Y-m-d H:i:s', (int)$tsS), date('Y-m-d H:i:s', (int)$tsE), ($tsE - $tsS) / 3600, $r['id']
                ]);
            }
        }

        // --- 2. 6 TAGE ARBEIT, 1 TAG FREI ---
        $stmt = $pdo->prepare("SELECT id, employeeid, date, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL ORDER BY employeeid, date ASC");
        $stmt->execute([$queryDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $streak = 0;
        for ($i = 0; $i < count($data); $i++) {
            if ($i > 0 && $data[$i]['employeeid'] == $data[$i-1]['employeeid']) {
                if (date('Y-m-d', strtotime($data[$i-1]['date'] . ' +1 day')) == $data[$i]['date']) $streak++; else $streak = 0;
            } else $streak = 0;
            if ($streak == 6) {
                if (isset($data[$i+1]) && $data[$i+1]['employeeid'] == $data[$i]['employeeid']) {
                    $newS = min(strtotime($data[$i]['workstartat']), strtotime($data[$i+1]['workstartat']));
                    $newE = max(strtotime($data[$i]['workendat']), strtotime($data[$i+1]['workendat']));
                    $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=? WHERE id=?")->execute([date('Y-m-d H:i:s', (int)$newS), date('Y-m-d H:i:s', (int)$newE), $data[$i+1]['id']]);
                    $pdo->prepare("DELETE FROM workinghours WHERE id=?")->execute([$data[$i]['id']]);
                }
                $streak = 0;
            }
        }

        // --- 3. 11H RUHEPAUSE ---
        $stmt = $pdo->prepare("SELECT id, employeeid, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL ORDER BY employeeid, workstartat ASC");
        $stmt->execute([$queryDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        for ($i = 1; $i < count($data); $i++) {
            if ($data[$i]['employeeid'] == $data[$i-1]['employeeid']) {
                $gap = strtotime($data[$i]['workstartat']) - strtotime($data[$i-1]['workendat']);
                if ($gap < 39600) {
                    $diff = (39600 - $gap) / 2;
                    $pdo->prepare("UPDATE workinghours SET workendat=? WHERE id=?")->execute([date('Y-m-d H:i:s', (int)(strtotime($data[$i-1]['workendat']) - $diff)), $data[$i-1]['id']]);
                    $pdo->prepare("UPDATE workinghours SET workstartat=? WHERE id=?")->execute([date('Y-m-d H:i:s', (int)(strtotime($data[$i]['workstartat']) + $diff)), $data[$i]['id']]);
                    $data[$i]['workstartat'] = date('Y-m-d H:i:s', (int)(strtotime($data[$i]['workstartat']) + $diff));
                }
            }
        }

        // --- 4. 00-04 STUNDEN ---
        $stmt = $pdo->prepare("SELECT id, date, workstartat, workendat FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tsS = strtotime($r['workstartat']); $tsE = strtotime($r['workendat']);
            $m0 = strtotime($r['date'] . ' +1 day 00:00:00'); $m4 = strtotime($r['date'] . ' +1 day 04:00:00');
            $h04 = ($tsS < $m0) ? max(0, min($tsE, $m4) - max($tsS, $m0)) / 3600 : 0;
            $pdo->prepare("UPDATE workinghours SET hours0004=?, hourstotal=? WHERE id=?")->execute([$h04, ($tsE - $tsS) / 3600, $r['id']]);
        }

        // --- 5. MULT ---
        $stmt = $pdo->prepare("SELECT w.*, e.maxweeklyhours FROM workinghours w JOIN employee e ON w.employeeid = e.id WHERE w.date LIKE ? AND w.workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats = [];
        foreach ($all as $x) {
            if(!isset($stats[$x['employeeid']])) $stats[$x['employeeid']] = ['sum' => 0, 'count' => 0];
            $stats[$x['employeeid']]['sum'] += $x['hourstotal'];
            $stats[$x['employeeid']]['count']++;
        }
        foreach ($all as $x) {
            $istTag = $stats[$x['employeeid']]['sum'] / max(1, $stats[$x['employeeid']]['count']);
            $sollTag = ($x['maxweeklyhours'] * 52) / 365;
            $mult = ($istTag > 0) ? ($sollTag / $istTag) * (0.95 + 0.1 * (mt_rand() / mt_getrandmax())) : 1;
            
            if (($x['hourstotal'] * $mult) >= 4) {
                // Hier werden auch 00-04 und 20-06 (noch nicht explizit in DB als 2006) mit mult geupdated
                $pdo->prepare("UPDATE workinghours SET hourstotal = hourstotal * ?, hours0004 = hours0004 * ? WHERE id = ?")->execute([$mult, $mult, $x['id']]);
            }
        }

        // --- 6.1. TOTAL < 4 & PAUSE ---
        $stmt = $pdo->prepare("SELECT id, workstartat, workendat, hourstotal FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $p = $pres - (float)$r['hourstotal'];
            if ((float)$r['hourstotal'] < 4 && $p > 0) {
                $pdo->prepare("UPDATE workinghours SET hourstotal = hourstotal + ? WHERE id = ?")->execute([$p / 2, $r['id']]);
            }
        }

        // --- 6.2. TOTALSTUNDEN = MAX 10 STUNDEN ---
        $stmt = $pdo->prepare("SELECT id, workstartat, workendat, hourstotal FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ((float)$r['hourstotal'] > 10) {
                $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
                $newT = 10.0;
                $pdo->prepare("UPDATE workinghours SET hourstotal=?, breakduration=? WHERE id=?")->execute([$newT, $pres - $newT, $r['id']]);
            }
        }

        // --- 7. RUNDEN (0,25) & PAUSE ---
        $stmt = $pdo->prepare("SELECT id, workstartat, workendat, hourstotal, hours0004 FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $newT = round((float)$r['hourstotal'] / 0.25) * 0.25;
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            // 20-06 Berechnung für die finale Speicherung
            $tsS = strtotime($r['workstartat']); $tsE = strtotime($r['workendat']);
            $t20 = strtotime(date('Y-m-d', $tsS) . ' 20:00:00'); $m06 = strtotime(date('Y-m-d', $tsS) . ' +1 day 06:00:00');
            $ov206 = max(0, min($tsE, $m06) - max($tsS, $t20)) / 3600;
            $h206 = round(max(0, $ov206 - (float)$r['hours0004']) / 0.25) * 0.25;

            $pdo->prepare("UPDATE workinghours SET hourstotal=?, hours0004=round(hours0004/0.25)*0.25, hours2006=?, breakduration=? WHERE id=?")
                ->execute([$newT, $h206, round($pres - $newT, 2), $r['id']]);
        }

        // --- 8 & 9. KONTROLLE NEGATIV & SUMME (FALLBACK) ---
        // Diese Schritte fangen Fehler ab und setzen auf die Basis von firsttripat/lasttripat zurück
        $stmt = $pdo->prepare("SELECT * FROM workinghours WHERE date LIKE ? AND workstartat IS NOT NULL");
        $stmt->execute([$queryDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pres = (strtotime($r['workendat']) - strtotime($r['workstartat'])) / 3600;
            $sum = round((float)$r['hourstotal'] + (float)$r['breakduration'], 2);
            
            if ((float)$r['hourstotal'] < 0 || (float)$r['hours0004'] < 0 || (float)$r['breakduration'] < 0 || $sum != round($pres, 2)) {
                
                // Trip-Basis wiederherstellen
                $tsS = (float)(round((strtotime($r['firsttripat']) - 900) / 1800) * 1800);
                $tsE = (float)(round((strtotime($r['lasttripat']) + 900) / 1800) * 1800);
                if ($tsE <= $tsS) $tsE += 86400;
                $p_fb = ($tsE - $tsS) / 3600;
                
                $total_fb = min($p_fb / 2, 5.5);
                
                $m0 = strtotime($r['date'] . ' +1 day 00:00:00'); $m4 = strtotime($r['date'] . ' +1 day 04:00:00');
                $t20 = strtotime($r['date'] . ' 20:00:00'); $m6 = strtotime($r['date'] . ' +1 day 06:00:00');
                
                $ov04 = max(0, min($tsE, $m4) - max($tsS, $m0)) / 3600;
                $ov206 = max(0, min($tsE, $m6) - max($tsS, $t20)) / 3600;

                $h04_fb = min($ov04 / 2, 2.0);
                $h206_fb = min($ov206 / 2, 4.0) - $h04_fb;
                
                $pause_fb = $p_fb - $total_fb;
                if ($total_fb < 4 && $pause_fb > 0) {
                    $total_fb += ($pause_fb / 2);
                    $pause_fb = $p_fb - $total_fb;
                }

                $pdo->prepare("UPDATE workinghours SET workstartat=?, workendat=?, hourstotal=?, hours0004=?, hours2006=?, breakduration=? WHERE id=?")
                    ->execute([date('Y-m-d H:i:s', (int)$tsS), date('Y-m-d H:i:s', (int)$tsE), $total_fb, $h04_fb, max(0, $h206_fb), round($pause_fb, 2), $r['id']]);
            }
        }

        $message = "<div style='padding:15px; background:#dcfce7; color:#166534; border-radius:4px; border: 1px solid #bbf7d0;'>✅ 10-Stufen-Pipeline erfolgreich abgeschlossen.</div>";
    } catch (Exception $e) {
        $message = "<div style='padding:15px; background:#fee2e2; color:#991b1b; border-radius:4px; border: 1px solid #fecaca;'>❌ Fehler: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981; font-family: sans-serif;">
    <h3 style="margin-top:0;">🕒 Arbeitszeit-Kalkulator (Advanced 10-Step Sync)</h3>
    <?= $message ?>
    <div style="background: #f8fafc; border-radius: 8px; border: 1px solid #cbd5e1; padding: 25px; margin-top:10px;">
        <form method="post">
            <div style="display: flex; align-items: center; gap: 15px;">
                <select name="target_month" style="padding: 10px; border-radius: 4px; border: 1px solid #cbd5e1; width: 200px;">
                    <?php for ($i = 0; $i < 12; $i++) {
                        $m = date('Y-m', strtotime("-$i months"));
                        echo "<option value='$m' ".($selectedMonth == $m ? 'selected' : '').">".date('F Y', strtotime("-$i months"))."</option>";
                    } ?>
                </select>
                <button type="submit" name="calculate_workinghours" style="cursor:pointer; border:none; padding:12px 30px; border-radius:6px; color:white; font-weight:bold; background:#10b981; transition: 0.2s;">
                    🚀 Pipeline starten
                </button>
            </div>
        </form>
    </div>
</div>