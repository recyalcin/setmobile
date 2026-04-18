<?php
/**
 * module/workinghourstripa.php
 * FINAL COMPLIANCE ENGINE v2026.H
 * * 11-Regel-System inkl. 6+1 Nullierung & präzisem Nachtstunden-Splitting.
 * Manipuliert Beginn, Ende und alle Stundenfelder zur Gesetzeskonformität.
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$selectedMonth = $_POST['target_month'] ?? date('Y-m', strtotime('first day of last month'));

if (isset($_POST['run_pipeline'])) {
    try {
        $pdo->beginTransaction();

        $queryDate = $selectedMonth . '-%';
        $year = (int)date('Y', strtotime($selectedMonth));
        $month = (int)date('m', strtotime($selectedMonth));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // --- SCHRITT 0: CLEANUP ---
        // Löscht alte Berechnungen für diesen Monat
        $pdo->prepare("DELETE FROM workinghours WHERE date LIKE ?")->execute([$queryDate]);

        // Mitarbeiter-Daten laden
        $employees = $pdo->query("SELECT e.id, e.maxweeklyhours, d.id as driver_id 
                                 FROM employee e 
                                 JOIN driver d ON e.personid = d.personid")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($employees as $emp) {
            $consecutiveDays = 0;
            $lastEndTimestamp = null;

            // --- PHASE 1: ZEITLICHE BASIS (REGEL 1, 2, 3) ---
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $currentDate = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                
                // Trips des Fahrers an diesem Tag finden
                $tStmt = $pdo->prepare("SELECT MIN(submittedat) as start, MAX(arrivedat) as end 
                                       FROM trip WHERE driverid = ? AND submittedat LIKE ?");
                $tStmt->execute([$emp['driver_id'], $currentDate . '%']);
                $trips = $tStmt->fetch(PDO::FETCH_ASSOC);

                $rawStart = ($trips['start'] !== null) ? (string)$trips['start'] : '';
                $rawEnd = ($trips['end'] !== null) ? (string)$trips['end'] : '';

                if ($rawStart === '') {
                    $consecutiveDays = 0;
                    continue; 
                }

                // REGEL 2: 6 Tage Arbeit, 1 Tag frei (Nullierung des 7. Tages)
                if ($consecutiveDays >= 6) {
                    $consecutiveDays = 0;
                    continue; 
                }
                $consecutiveDays++;

                // REGEL 1: Runden auf 15 Minuten (Vor/Nach)
                $startTS = floor(strtotime($rawStart) / 900) * 900;
                $endTS = ceil(strtotime($rawEnd) / 900) * 900;

                // REGEL 3: 11 Stunden Ruhepause (Start verschieben)
                if ($lastEndTimestamp !== null) {
                    $gap = $startTS - $lastEndTimestamp;
                    if ($gap < 39600) { 
                        $startTS = $lastEndTimestamp + 39600;
                        $endTS = max($endTS, $startTS + 14400); // Sicherstellen, dass Ende > Start
                    }
                }
                $lastEndTimestamp = $endTS;

                $ins = $pdo->prepare("INSERT INTO workinghours (employeeid, date, workstartat, workendat) VALUES (?, ?, ?, ?)");
                $ins->execute([$emp['id'], $currentDate, date('Y-m-d H:i:s', $startTS), date('Y-m-d H:i:s', $endTS)]);
            }

            // --- PHASE 2: MATHEMATIK & COMPLIANCE (REGEL 4 - 11) ---
            $stmt = $pdo->prepare("SELECT id, date, workstartat, workendat, 
                                   (EXTRACT(EPOCH FROM(workendat) - EXTRACT(EPOCH FROM(workstartat))/3600 as raw_hours 
                                   FROM workinghours WHERE employeeid = ? AND date LIKE ?");
            $stmt->execute([$emp['id'], $queryDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($rows)) continue;

            // Durchschnittsberechnung für Multiplier
            $totalRawMonth = array_sum(array_column($rows, 'raw_hours'));
            $avgRaw = $totalRawMonth / count($rows);
            
            // REGEL 6: Multiplier Formel
            $randomZahl = 0.95 + (mt_rand() / mt_getrandmax() * 0.1);
            $dailyTarget = ((float)$emp['maxweeklyhours'] * 52 / 365);
            $multiplier = ($dailyTarget / max(0.1, $avgRaw)) * $randomZahl;

            foreach ($rows as $r) {
                // Multiplikation & Caps (REGEL 7 & 8)
                $totalH = $r['raw_hours'] * $multiplier;
                if ($totalH < 4) $totalH = 4;
                if ($totalH > 10) $totalH = 10;

                // REGEL 10: Runden auf 0.25
                $totalH = round($totalH / 0.25) * 0.25;

                // REGEL 9: Pause
                $pause = ($totalH >= 9) ? 1.0 : 0.5;

                // NACHTSTUNDEN SPEZIAL-LOGIK (ERGÄNZUNG)
                $sTS = strtotime($r['workstartat']);
                $eTS_virt = $sTS + ($totalH * 3600); // Virtueller Zeitraum für Anteilsberechnung
                $dStr = $r['date'];

                // Fenster-Definitionen
                $w2024 = ['s' => strtotime($dStr." 20:00:00"), 'e' => strtotime($dStr." 23:59:59") + 1];
                $w0004 = ['s' => strtotime($dStr." +1 day 00:00:00"), 'e' => strtotime($dStr." +1 day 04:00:00")];
                $w0406 = ['s' => strtotime($dStr." +1 day 04:00:00"), 'e' => strtotime($dStr." +1 day 06:00:00")];

                // Schnittmengen-Berechnung
                $h2024 = max(0, min($eTS_virt, $w2024['e']) - max($sTS, $w2024['s'])) / 3600;
                $h0004 = max(0, min($eTS_virt, $w0004['e']) - max($sTS, $w0004['s'])) / 3600;
                $h0406 = max(0, min($eTS_virt, $w0406['e']) - max($sTS, $w0406['s'])) / 3600;

                // REGEL 4 & 5 Zuordnung
                $final_0004 = round($h0004 / 0.25) * 0.25;
                $final_2006 = round(($h2024 + $h0406) / 0.25) * 0.25;

                // REGEL 11: Finales Ende anpassen
                // Damit pause = (ende-begin) - total stimmt
                $finalEndTS = $sTS + (($totalH + $pause) * 3600);

                $upd = $pdo->prepare("UPDATE workinghours SET 
                    hourstotal = ?, 
                    startkm = ?, 
                    endkm = ?, 
                    breakduration = ?, 
                    workendat = ? 
                    WHERE id = ?");
                $upd->execute([
                    $totalH, 
                    $final_0004, 
                    $final_2006, 
                    $pause, 
                    date('Y-m-d H:i:s', (int)$finalEndTS), 
                    $r['id']
                ]);
            }
        }

        $pdo->commit();
        $message = "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:10px; border:1px solid #bbf7d0;'>✅ <b>Abrechnungs-Master v2026.H erfolgreich:</b> Alle 11 Regeln inkl. Nachtschicht-Vorgaben wurden präzise angewendet.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:10px; border:1px solid #fecaca;'>❌ <b>Kritischer Fehler:</b> " . $e->getMessage() . "</div>";
    }
}
?>

<div class="card" style="font-family: 'Segoe UI', Roboto, sans-serif; border: 1px solid #e2e8f0; border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); background:#fff; overflow:hidden; max-width: 850px; margin: 20px auto;">
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:25px; color:white;">
        <h2 style="margin:0; font-size:1.4rem; letter-spacing: -0.01em;">🕒 Workinghours Pipeline 2026</h2>
        <p style="margin:6px 0 0 0; font-size:0.85rem; opacity:0.8; font-weight: 300;">Automatisierte Manipulation & Gesetzeskonformität</p>
    </div>
    <div style="padding:30px;">
        <?= $message ?>
        
        <div style="margin-top:20px; padding:15px; background:#f1f5f9; border-radius:8px; border-left:4px solid #3b82f6;">
            <h4 style="margin:0 0 5px 0; font-size:0.8rem; color:#1e293b; text-transform:uppercase;">Konfiguration der Nachtstunden</h4>
            <p style="margin:0; font-size:0.75rem; color:#475569; line-height:1.4;">
                <b>00-04h:</b> Kernnachtzeit | <b>20-06h:</b> Erweiterte Nachtzeit (20-24h + 04-06h).<br>
                Alle Werte werden gemäß Regel 10 auf 0.25h gerundet.
            </p>
        </div>

        <form method="post" style="margin-top:25px; display:flex; gap:15px; align-items:flex-end;">
            <div style="flex-grow:1;">
                <label style="display:block; font-size:0.7rem; font-weight:bold; color:#64748b; margin-bottom:8px; text-transform:uppercase;">Zeitraum für Abrechnung</label>
                <input type="month" name="target_month" value="<?= $selectedMonth ?>" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; outline:none; font-weight:600; color:#1e293b; background:#fff;">
            </div>
            <button type="submit" name="run_pipeline" style="background:#2563eb; color:white; border:none; padding:13px 30px; border-radius:8px; font-weight:bold; cursor:pointer; transition:all 0.2s; box-shadow: 0 2px 4px rgba(37,99,235,0.2);" onmouseover="this.style.background='#1d4ed8'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#2563eb'; this.style.transform='translateY(0)';">
                🚀 Pipeline starten
            </button>
        </form>
    </div>
</div>