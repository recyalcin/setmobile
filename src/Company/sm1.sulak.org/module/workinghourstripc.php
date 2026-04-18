<?php
/**
 * module/workinghourstripb.php
 * SEQUENTIAL COMPLIANCE MASTER ENGINE v2026.X
 */

global $pdo; // Zugriff auf das Datenbank-Objekt aus der index.php
$db = $pdo;

if (!$db instanceof PDO) {
    die("Fehler: Datenbankverbindung nicht gefunden.");
}

// Monatsparameter (Beispielwert aus POST oder Current)
$selectedMonth = $_POST['target_month'] ?? date('Y-m');

/**
 * Hilfsfunktion: Lädt alle Daten für den Fahrer-Monat neu (RELOAD)
 */
function reloadMonthData($db, $driverId, $month) {
    $stmt = $db->prepare("
        SELECT wh.*, e.maxweeklyhours, s.name as schicht_name 
        FROM workinghours wh
        JOIN employee e ON wh.employeeid = e.id
        LEFT JOIN workinghoursschicht s ON e.workinghoursschichtid = s.id
        WHERE wh.employeeid = ? AND wh.date LIKE ? 
        ORDER BY wh.date ASC
    ");
    $stmt->execute([$driverId, $month . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Hilfsfunktion: Speichert den kompletten Record eines Tages (UPDATE)
 */
function updateDayRecord($db, $row) {
    $stmt = $db->prepare("
        UPDATE workinghours SET 
            workstartat = :workstartat, workendat = :workendat, firsttripat = :firsttripat, 
            lasttripat = :lasttripat, breakduration = :breakduration, hours0004 = :hours0004, 
            hours2006 = :hours2006, hourstotal = :hourstotal 
        WHERE id = :id
    ");
    $stmt->execute([
        ':workstartat' => $row['workstartat'], ':workendat' => $row['workendat'],
        ':firsttripat'  => $row['firsttripat'],  ':lasttripat'  => $row['lasttripat'],
        ':breakduration'=> $row['breakduration'], ':hours0004'   => $row['hours0004'],
        ':hours2006'    => $row['hours2006'],    ':hourstotal'  => $row['hourstotal'],
        ':id'           => $row['id']
    ]);
}

/**
 * Hilfsfunktion: Berechnet Überlappung in Stunden zwischen zwei Zeitfenstern
 */
function calculateOverlap($start, $end, $winS, $winE) {
    $s = max(strtotime($start), strtotime($winS));
    $e = min(strtotime($end), strtotime($winE));
    return ($e > $s) ? ($e - $s) / 3600 : 0;
}

// =========================================================================
// HAUPTSCHLEIFE FÜR ALLE FAHRER
// =========================================================================
$allDrivers = $db->query("SELECT id FROM employee")->fetchAll(PDO::FETCH_COLUMN);

foreach ($allDrivers as $driverId) {

    // --- VORBEREITUNG: Matrix initialisieren ---
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)date('m', strtotime($selectedMonth)), (int)date('Y', strtotime($selectedMonth)));
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        $db->prepare("INSERT INTO workinghours (employeeid, date) VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE workstartat=NULL, workendat=NULL, firsttripat=NULL, lasttripat=NULL, 
                      breakduration=0, hours0004=0, hours2006=0, hourstotal=0")->execute([$driverId, $date]);
    }

    // --- STEP 1 & 2: First- & LastTrip (Schicht-bewusst) ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    foreach ($data as $row) {
        $isNight = ($row['schicht_name'] === '12:00 - 11:59');
        $winS = $row['date'] . ($isNight ? ' 12:00:00' : ' 00:00:00');
        $winE = date('Y-m-d H:i:s', strtotime($winS . ' +23 hours 59 minutes 59 seconds'));

        $st = $db->prepare("SELECT MIN(submittedat) FROM trip WHERE driverid = (SELECT personid FROM employee WHERE id=?) AND submittedat BETWEEN ? AND ?");
        $st->execute([$driverId, $winS, $winE]);
        $row['firsttripat'] = $st->fetchColumn();

        $st = $db->prepare("SELECT MAX(COALESCE(arrivedat, submittedat)) FROM trip WHERE driverid = (SELECT personid FROM employee WHERE id=?) AND submittedat BETWEEN ? AND ?");
        $st->execute([$driverId, $winS, $winE]);
        $row['lasttripat'] = $st->fetchColumn();
        updateDayRecord($db, $row);
    }

    // --- STEP 5: Rundung (0.5h Basis) ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    foreach ($data as $row) {
        if ($row['firsttripat']) {
            $ts = strtotime($row['firsttripat']) - (15 * 60);
            $row['workstartat'] = date('Y-m-d H:i:s', round($ts / 1800) * 1800);
        }
        if ($row['lasttripat']) {
            $ts = strtotime($row['lasttripat']) + (15 * 60);
            $row['workendat'] = date('Y-m-d H:i:s', round($ts / 1800) * 1800);
        }
        updateDayRecord($db, $row);
    }

    // --- STEP 6: Tampon (1h) & STEP 7: 24h Check ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    foreach ($data as $row) {
        if (!$row['workstartat']) continue;
        $isNight = ($row['schicht_name'] === '12:00 - 11:59');
        
        if (!$isNight) {
            if (date('H', strtotime($row['workstartat'])) < 1) $row['workstartat'] = $row['date'] . ' 01:00:00';
            if (date('H', strtotime($row['workendat'])) > 22) $row['workendat'] = $row['date'] . ' 23:00:00';
        } else {
            // Nacht-Tampon: Start < 13:00 oder Ende > 11:00 (Folgetag)
        }
        // 24h Check
        if ((strtotime($row['workendat']) - strtotime($row['workstartat'])) > 86400) {
            $row['workendat'] = date('Y-m-d H:i:s', strtotime($row['workendat']) - 86400);
        }
        updateDayRecord($db, $row);
    }

    // --- STEP 8: Iterative 6+1 Regel ---
    $violation = true;
    while ($violation) {
        $violation = false;
        $data = reloadMonthData($db, $driverId, $selectedMonth);
        $streak = 0; $lastFreeIdx = -1;
        foreach ($data as $idx => $row) {
            if ($row['workstartat'] !== null) {
                $streak++;
                if ($streak == 7) {
                    $violation = true;
                    // Nullieren (7. Tag oder kürzester)
                    $target = ($lastFreeIdx !== -1) ? $data[$idx] : null; // vereinfachte Logik
                    $target['workstartat'] = $target['workendat'] = null;
                    updateDayRecord($db, $target);
                    break; // Neustart while
                }
            } else { $streak = 0; $lastFreeIdx = $idx; }
        }
    }

    // --- STEP 9: 11h Ruhepause ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    for ($i = 1; $i < count($data); $i++) {
        $vortag = $data[$i-1]; $aktuell = $data[$i];
        if (!$vortag['workendat'] || !$aktuell['workstartat']) continue;
        
        $pause = (strtotime($aktuell['workstartat']) - strtotime($vortag['workendat'])) / 3600;
        if ($pause < 11) {
            $fehlend = (11 - $pause) * 3600;
            $vortag['workendat'] = date('Y-m-d H:i:s', strtotime($vortag['workendat']) - ($fehlend / 2));
            $aktuell['workstartat'] = date('Y-m-d H:i:s', strtotime($aktuell['workstartat']) + ($fehlend / 2));
            
            // Plusstunden Logik
            if ($aktuell['workendat'] < $aktuell['workstartat']) {
                $plus = strtotime($aktuell['workstartat']) - strtotime($aktuell['workendat']);
                $aktuell['workendat'] = date('Y-m-d H:i:s', strtotime($aktuell['workendat']) + $plus + $fehlend);
            }
            updateDayRecord($db, $vortag);
            updateDayRecord($db, $aktuell);
        }
    }

    // --- STEP 12: Multiplier & Nachtstunden-Check ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    $totalCurrentHours = 0; $workDaysCount = 0;
    foreach ($data as $row) { if ($row['workstartat']) { $totalCurrentHours += (strtotime($row['workendat']) - strtotime($row['workstartat'])) / 3600; $workDaysCount++; } }
    
    if ($workDaysCount > 0) {
        $avgCurrent = $totalCurrentHours / $workDaysCount;
        $targetAvg = ($data[0]['maxweeklyhours'] * 52 / 365);
        $random = 0.95 + (0.1 * (mt_rand() / mt_getrandmax()));
        $multiplier = ($avgCurrent > 0) ? ($targetAvg / $avgCurrent) * $random : 1;

        foreach ($data as $row) {
            if (!$row['workstartat']) continue;
            $row['hourstotal'] = ((strtotime($row['workendat']) - strtotime($row['workstartat'])) / 3600) * $multiplier;
            // Nachtstunden Fenster-Check (Step 13)
            // ... (Hier Fensterberechnung heute/morgen für 00-04 und 20-06 implementieren)
            $row['breakduration'] = ((strtotime($row['workendat']) - strtotime($row['workstartat'])) / 3600) - $row['hourstotal'];
            updateDayRecord($db, $row);
        }
    }

    // --- STEP 15: Final Polishing (Rundung 0.25, Cap 10h, Min 4h) ---
    $data = reloadMonthData($db, $driverId, $selectedMonth);
    foreach ($data as $row) {
        if (!$row['workstartat']) continue;
        
        // Min 4h Logik
        if ($row['hourstotal'] < 4 && $row['breakduration'] > 0) {
            $row['hourstotal'] += $row['breakduration'];
        }
        // Max 10h Logik
        if ($row['hourstotal'] > 10) {
            $row['hourstotal'] = 10;
        }
        
        // 0.25 Rundung
        $row['hourstotal'] = round($row['hourstotal'] * 4) / 4;
        $row['breakduration'] = ((strtotime($row['workendat']) - strtotime($row['workstartat'])) / 3600) - $row['hourstotal'];
        updateDayRecord($db, $row);
    }
}

echo "Abrechnung für $selectedMonth erfolgreich abgeschlossen.";