<?php
/**
 * module/csvperformanceboltuber.php
 * Zentrales Import-Tool für Bolt- und Uber-Statistiken.
 * * LOGIK FÜR NAMEN-ABGLEICH:
 * - "büyük i" (İ) -> "i" / "I" -> "i"
 * - "küçük I" (ı) -> "i"
 * - ü -> u, ö -> o, ä -> a, ß -> ss usw.
 * - Alles wird für den Vergleich normalisiert.
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$msg = ""; 
$errorLog = [];
$importWeek = isset($_POST['week']) ? $_POST['week'] : date('Y-m-d', strtotime('monday last week'));

/**
 * Hilfsfunktion: String-Normalisierung für den Namensabgleich
 * Setzt die Anforderungen: İ/I/ı -> i, Umlaute -> Vokal, Kleinschreibung
 */
function normalizeName($str) {
    if ($str === null) return "";
    
    // Spezielle türkische Logik & Umlaute
    $search  = ['İ', 'ı', 'ö', 'ü', 'ä', 'Ö', 'Ü', 'Ä', 'ß', 'ş', 'Ş', 'ğ', 'Ğ', 'ç', 'Ç'];
    $replace = ['i', 'i', 'o', 'u', 'a', 'o', 'u', 'a', 'ss', 's', 's', 'g', 'g', 'c', 'c'];
    
    $str = str_replace($search, $replace, $str);
    
    // In Kleinschreibung umwandeln und trimmen
    return mb_strtolower(trim($str), 'UTF-8');
}

/**
 * Hilfsfunktion: Bereinigung von Zahlen (1.000,00 -> 1000.00)
 */
function cleanNum($val) {
    if ($val === null || $val === '' || strtolower($val) === 'null') return 0.00;
    $val = str_replace(['"', ' ', '€'], '', $val);
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val); 
        $val = str_replace(',', '.', $val); 
    }
    return is_numeric($val) ? (float)$val : 0.00;
}

/**
 * Hilfsfunktion: Zeitformat (HH:MM:SS) in Minuten
 */
function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    if (count($parts) < 2) return 0;
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    $s = isset($parts[2]) ? (int)$parts[2] : 0;
    return ($h * 60) + $m + ($s / 60);
}

/**
 * BOLT Namens-Logik:
 * 1. Wort = firstname, Letztes Wort = lastname, alles dazwischen = middlename
 */
function getDriverIdFromBoltName($pdo, $fullName) {
    $fullName = trim($fullName);
    if (empty($fullName)) return null;

    $parts = array_values(array_filter(explode(' ', $fullName)));
    $count = count($parts);

    if ($count === 1) {
        return getDriverIdByParts($pdo, $parts[0], "", "");
    }

    $fName = $parts[0];
    $lName = $parts[$count - 1];
    $mName = implode(' ', array_slice($parts, 1, $count - 2));

    return getDriverIdByParts($pdo, $fName, $mName, $lName);
}

/**
 * UBER Namens-Logik:
 * Vorname-Feld: 1. Wort = firstname, Rest = middlename
 * Nachname-Feld: Kompletter Inhalt = lastname
 */
function getDriverIdFromUberNames($pdo, $uberVorname, $uberNachname) {
    $vParts = array_values(array_filter(explode(' ', trim($uberVorname))));
    
    $fName = $vParts[0] ?? "";
    $mName = implode(' ', array_slice($vParts, 1));
    $lName = trim($uberNachname);

    return getDriverIdByParts($pdo, $fName, $mName, $lName);
}

/**
 * Kernfunktion: Findet oder erstellt Person & Driver
 * Nutzt Normalisierung für den Namensabgleich
 */
function getDriverIdByParts($pdo, $fName, $mName, $lName) {
    $normFirst = normalizeName($fName);
    $normLast  = normalizeName($lName);

    // 1. Alle Personen laden für den normalisierten Abgleich
    // (Bei sehr vielen Fahrern könnte man dies cachen, für den Import ist es so aber am sichersten)
    $stmt = $pdo->query("SELECT id, firstname, lastname FROM person");
    $personId = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (normalizeName($row['firstname']) === $normFirst && normalizeName($row['lastname']) === $normLast) {
            $personId = $row['id'];
            break;
        }
    }

    if (!$personId) {
        // Neu anlegen, falls kein normalisierter Treffer
        $insP = $pdo->prepare("INSERT INTO person (firstname, middlename, lastname, createdat) VALUES (?, ?, ?, NOW())");
        $insP->execute([$fName, $mName, $lName]);
        $personId = $pdo->lastInsertId();
    } else {
        // Middlename aktualisieren, falls er in der DB leer ist
        $updP = $pdo->prepare("UPDATE person SET middlename = ? WHERE id = ? AND (middlename IS NULL OR middlename = '')");
        $updP->execute([$mName, $personId]);
    }

    // 2. Driver suchen oder anlegen
    $stmtD = $pdo->prepare("SELECT id FROM driver WHERE personid = ? LIMIT 1");
    $stmtD->execute([$personId]);
    $driverId = $stmtD->fetchColumn();

    if (!$driverId) {
        $insD = $pdo->prepare("INSERT INTO driver (personid, createdat) VALUES (?, NOW())");
        $insD->execute([$personId]);
        $driverId = $pdo->lastInsertId();
    }

    return $driverId;
}

// Import-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $count = 0;
    $action = $_POST['action'];
    $file = $_FILES['csvfile']['tmp_name'] ?? null;

    try {
        if (!$file || !is_uploaded_file($file)) {
            throw new Exception("Keine Datei ausgewählt.");
        }

        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle, 4000, ","); // Header überspringen

            while (($data = fgetcsv($handle, 4000, ",")) !== FALSE) {
                $driverId = null;

                if ($action === 'bolt') {
                    if (count($data) < 30) continue;
                    $driverId = getDriverIdFromBoltName($pdo, $data[0]);
                } else {
                    if (count($data) < 3) continue;
                    $driverId = getDriverIdFromUberNames($pdo, $data[1], $data[2]);
                }

                if (!$driverId) continue;

                $check = $pdo->prepare("SELECT id FROM performance WHERE driverid = ? AND week = ? LIMIT 1");
                $check->execute([$driverId, $importWeek]);
                $exists = $check->fetchColumn();

                // Mapping-Logik
                switch ($action) {
                    case 'bolt':
                        $net = cleanNum($data[21]); $toll = cleanNum($data[14]); $tips = cleanNum($data[9]);
                        $vals = [$net, $toll, $tips, ($net-$toll-$tips), cleanNum($data[8]), (int)$data[33], cleanNum($data[36]), cleanNum($data[41]), cleanNum($data[34])];
                        
                        if ($exists) {
                            $sql = "UPDATE performance SET netearningsbolt=?, tollfeesbolt=?, ridertipsbolt=?, earningsperformancebolt=?, collectedcashbolt=?, finishedridesbolt=?, onlinetimebolt=?, totalridedistancebolt=?, totalacceptanceratebolt=?, updatedat=NOW() WHERE id=?";
                            $pdo->prepare($sql)->execute(array_merge($vals, [$exists]));
                        } else {
                            $sql = "INSERT INTO performance (driverid, week, netearningsbolt, tollfeesbolt, ridertipsbolt, earningsperformancebolt, collectedcashbolt, finishedridesbolt, onlinetimebolt, totalridedistancebolt, totalacceptanceratebolt, createdat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            $pdo->prepare($sql)->execute(array_merge([$driverId, $importWeek], $vals));
                        }
                        break;

                    case 'uber_pay':
                        $net = cleanNum($data[3]); $toll = cleanNum($data[6]); $tips = cleanNum($data[10]); $cash = abs(cleanNum($data[9]));
                        if ($exists) {
                            $pdo->prepare("UPDATE performance SET netearningsuber=?, tollfeesuber=?, ridertipsuber=?, earningsperformanceuber=?, collectedcashuber=?, updatedat=NOW() WHERE id=?")->execute([$net, $toll, $tips, ($net-$toll-$tips), $cash, $exists]);
                        } else {
                            $pdo->prepare("INSERT INTO performance (driverid, week, netearningsuber, tollfeesuber, ridertipsuber, earningsperformanceuber, collectedcashuber, createdat) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")->execute([$driverId, $importWeek, $net, $toll, $tips, ($net-$toll-$tips), $cash]);
                        }
                        break;

                    case 'uber_metrics':
                        $rides = (int)$data[3]; $rate = cleanNum($data[4]) * 100;
                        if ($exists) {
                            $pdo->prepare("UPDATE performance SET finishedridesuber=?, totalacceptancerateuber=?, updatedat=NOW() WHERE id=?")->execute([$rides, $rate, $exists]);
                        } else {
                            $pdo->prepare("INSERT INTO performance (driverid, week, finishedridesuber, totalacceptancerateuber, createdat) VALUES (?, ?, ?, ?, NOW())")->execute([$driverId, $importWeek, $rides, $rate]);
                        }
                        break;

                    case 'uber_activity':
                        $min = max(timeToMinutes($data[3]), timeToMinutes($data[4]), timeToMinutes($data[5]), timeToMinutes($data[6]));
                        $km = max(cleanNum($data[7]), cleanNum($data[8]), cleanNum($data[9]), cleanNum($data[10]));
                        if ($exists) {
                            $pdo->prepare("UPDATE performance SET onlinetimeuber=?, totalridedistanceuber=?, updatedat=NOW() WHERE id=?")->execute([$min, $km, $exists]);
                        } else {
                            $pdo->prepare("INSERT INTO performance (driverid, week, onlinetimeuber, totalridedistanceuber, createdat) VALUES (?, ?, ?, ?, NOW())")->execute([$driverId, $importWeek, $min, $km]);
                        }
                        break;
                }
                $count++;
            }
            fclose($handle);
            $msg = "Erfolg: $count Datensätze für Woche $importWeek verarbeitet.";
        }
    } catch (Exception $e) {
        $errorLog[] = $e->getMessage();
    }
}
?>

<style>
    .import-wrapper { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 20px auto; padding: 25px; background: #fff; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .import-header { border-bottom: 2px solid #f4f4f4; margin-bottom: 20px; padding-bottom: 10px; }
    .import-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .card { padding: 20px; border-radius: 8px; border: 1px solid #eee; transition: transform 0.2s; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .bolt { border-top: 5px solid #32bb78; background: #fafffb; }
    .uber { border-top: 5px solid #333; background: #fcfcfc; }
    .card-title { font-weight: bold; font-size: 14px; margin-bottom: 15px; display: block; color: #444; }
    .btn-submit { width: 100%; padding: 10px; border: none; border-radius: 6px; color: white; font-weight: bold; cursor: pointer; margin-top: 10px; }
    .btn-bolt { background: #32bb78; } .btn-uber { background: #333; }
    .date-picker { background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #cfe2f3; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
</style>

<div class="import-wrapper">
    <div class="import-header">
        <h2>📊 Performance CSV Import Center</h2>
    </div>

    <?php if($msg): ?><div class="alert" style="background:#d4edda; color:#155724; border:1px solid #c3e6cb;"><?= $msg ?></div><?php endif; ?>
    <?php if(!empty($errorLog)): ?><div class="alert" style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;"><?= implode('<br>', $errorLog) ?></div><?php endif; ?>

    <div class="date-picker">
        <label style="font-weight: bold; display: block; margin-bottom: 5px;">Zielwoche festlegen (Montag):</label>
        <input type="date" name="week_global" id="week_global" value="<?= $importWeek ?>" 
               style="padding: 10px; border: 1px solid #ccc; border-radius: 6px; width: 200px;" 
               onchange="document.querySelectorAll('.hidden-week').forEach(el => el.value = this.value)">
    </div>

    <div class="import-grid">
        <div class="card bolt">
            <span class="card-title">🟢 Bolt: Alle Statistiken</span>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="week" class="hidden-week" value="<?= $importWeek ?>">
                <input type="file" name="csvfile" accept=".csv" required>
                <button type="submit" name="action" value="bolt" class="btn-submit btn-bolt">Bolt Import</button>
            </form>
        </div>

        <div class="card uber">
            <span class="card-title">⚫ Uber: Zahlungen (Earnings)</span>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="week" class="hidden-week" value="<?= $importWeek ?>">
                <input type="file" name="csvfile" accept=".csv" required>
                <button type="submit" name="action" value="uber_pay" class="btn-submit btn-uber">Uber Pay Import</button>
            </form>
        </div>

        <div class="card uber">
            <span class="card-title">⚫ Uber: Fahrten & Raten</span>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="week" class="hidden-week" value="<?= $importWeek ?>">
                <input type="file" name="csvfile" accept=".csv" required>
                <button type="submit" name="action" value="uber_metrics" class="btn-submit btn-uber">Uber Metrics Import</button>
            </form>
        </div>

        <div class="card uber">
            <span class="card-title">⚫ Uber: Zeit & Kilometer</span>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="week" class="hidden-week" value="<?= $importWeek ?>">
                <input type="file" name="csvfile" accept=".csv" required>
                <button type="submit" name="action" value="uber_activity" class="btn-submit btn-uber">Uber Activity Import</button>
            </form>
        </div>
    </div>

    <div style="margin-top: 40px; text-align: center;">
        <a href="/?s=performance" style="color: #2563eb; text-decoration: none; font-weight: bold;">← Zum Dashboard</a>
    </div>
</div>