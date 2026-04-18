<?php
/**
 * module/csvperformance.php
 * CSV-Import für Performance-Daten (Bolt & Uber)
 * Logik: Update bei vorhandener "week + driverid", sonst Insert.
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$importLog = [];
$countInsert = 0;
$countUpdate = 0;
$errorLog = [];

// 1. HILFSFUNKTIONEN
function findOrCreate($pdo, $table, $name) {
    if (empty(trim($name))) return null;
    $name = trim($name);
    
    if ($table === 'person') {
        $sql = "SELECT id FROM person WHERE CONCAT(firstname, ' ', lastname) = ? OR lastname = ? LIMIT 1";
        $params = [$name, $name];
    } else {
        $sql = "SELECT id FROM $table WHERE name = ? LIMIT 1";
        $params = [$name];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $id = $stmt->fetchColumn();

    if ($id) return $id;

    if ($table === 'person') {
        $parts = explode(' ', $name, 2);
        $first = $parts[0];
        $last = $parts[1] ?? $parts[0];
        $ins = $pdo->prepare("INSERT INTO person (firstname, lastname, createddate) VALUES (?, ?, NOW())");
        $ins->execute([$first, $last]);
    } else {
        $ins = $pdo->prepare("INSERT INTO $table (name, createddate) VALUES (?, NOW())");
        $ins->execute([$name]);
    }
    
    return $pdo->lastInsertId();
}

function cleanNum($val) {
    if ($val === null || $val === '' || strtolower($val) === 'null') return 0.00;
    $val = str_replace('"', '', $val);
    // Behebt das Problem: 1.000,00 -> 1000.00
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val); 
        $val = str_replace(',', '.', $val); 
    }
    return (float)$val;
}

// 2. IMPORT LOGIK
if (isset($_POST['import_performance'])) {
    $csvData = $_POST['csv_text'];
    $lines = explode("\n", str_replace("\r", "", $csvData));

    foreach ($lines as $line) {
        if (empty(trim($line)) || strpos($line, '"id"') === 0 || strpos($line, 'id') === 0) continue;

        $data = str_getcsv($line, ",");
        if (count($data) < 24) {
            if (!empty(trim($line))) $errorLog[] = "❌ Zeile übersprungen (zu wenig Spalten): " . substr($line, 0, 50) . "...";
            continue;
        } 

        try {
            $week       = trim($data[2]);
            $firstName  = trim($data[22]);
            $lastName   = trim($data[23]);
            $driverFull = $firstName . ' ' . $lastName;
            
            // Driver-ID (person.id) ermitteln
            $driverId = findOrCreate($pdo, 'person', $driverFull);

            // Daten für Bolt & Uber vorbereiten
            $boltParams = [
                cleanNum($data[3]), cleanNum($data[4]), cleanNum($data[5]), cleanNum($data[6]), 
                cleanNum($data[7]), (int)$data[8], cleanNum($data[9]), cleanNum($data[10]), cleanNum($data[11])
            ];

            $uberParams = [
                cleanNum($data[12]), cleanNum($data[13]), cleanNum($data[14]), cleanNum($data[15]), 
                cleanNum($data[16]), (int)$data[17], cleanNum($data[18]), cleanNum($data[19]), cleanNum($data[20])
            ];

            // PRÜFUNG: Existiert Record mit dieser driverid und week?
            $check = $pdo->prepare("SELECT id FROM performance WHERE driverid = ? AND week = ? LIMIT 1");
            $check->execute([$driverId, $week]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                // UPDATE
                $sql = "UPDATE performance SET 
                        netearningsbolt=?, tollfeesbolt=?, ridertipsbolt=?, collectedcashbolt=?, earningsperformancebolt=?, finishedridesbolt=?, onlinetimebolt=?, totalridedistancebolt=?, totalacceptanceratebolt=?,
                        netearningsuber=?, tollfeesuber=?, ridertipsuber=?, collectedcashuber=?, earningsperformanceuber=?, finishedridesuber=?, onlinetimeuber=?, totalridedistanceuber=?, totalacceptancerateuber=?,
                        updatedat=NOW() WHERE id=?";
                $pdo->prepare($sql)->execute(array_merge($boltParams, $uberParams, [$existingId]));
                $countUpdate++;
            } else {
                // INSERT
                $sql = "INSERT INTO performance (
                        driverid, week, 
                        netearningsbolt, tollfeesbolt, ridertipsbolt, collectedcashbolt, earningsperformancebolt, finishedridesbolt, onlinetimebolt, totalridedistancebolt, totalacceptanceratebolt,
                        netearningsuber, tollfeesuber, ridertipsuber, collectedcashuber, earningsperformanceuber, finishedridesuber, onlinetimeuber, totalridedistanceuber, totalacceptancerateuber,
                        createdat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
                $pdo->prepare($sql)->execute(array_merge([$driverId, $week], $boltParams, $uberParams));
                $countInsert++;
            }
        } catch (Exception $e) {
            $errorLog[] = "⚠️ Fehler bei $driverFull ($week): " . $e->getMessage();
        }
    }
    
    // Journal-Zusammenfassung
    if ($countInsert > 0 || $countUpdate > 0) {
        $importLog[] = "📊 <strong>Journal:</strong> $countInsert neue Datensätze, $countUpdate Datensätze aktualisiert.";
    }
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h3>📊 Performance CSV-Import</h3>
    <p style="font-size: 0.9em; color: #666;">Format: CSV mit Spalten "week" (Index 2), "ad" (Index 22) und "soyad" (Index 23).</p>
    <form method="post">
        <textarea name="csv_text" rows="10" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd;" placeholder='CSV Daten hier einfügen...'></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_performance" class="btn save" style="cursor:pointer; padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 4px;">Daten verarbeiten</button>
        </div>
    </form>
</div>

<?php if (!empty($importLog) || !empty($errorLog)): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h4>Import-Protokoll</h4>
    <div style="max-height: 400px; overflow-y: auto; font-size: 13px; background: #f8fafc; padding: 10px; border: 1px solid #eee;">
        <?php foreach ($importLog as $log): ?>
            <div style="padding: 8px 0; border-bottom: 2px solid #e2e8f0; color: #065f46; font-size: 14px;"><?= $log ?></div>
        <?php endforeach; ?>
        
        <?php foreach ($errorLog as $err): ?>
            <div style="padding: 5px 0; border-bottom: 1px solid #fee2e2; color: #b91c1c;"><?= $err ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>