<?php
/**
 * module/csvarbeitszeit.php
 * Version: 01-260407
 * Import von Eskiden-Arbeitszeiten
 * Felder: date=tarih, firsttripat=ilkfahrt, lasttripat=sonfahrt (keine Rundung)
 * Sonst wird nichts importiert.
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$report = [
    'updates' => 0,
    'inserts' => 0,
    'errors'  => []
];

/**
 * Hilfsfunktion: Findet die employee.id basierend auf Vor- und Nachnamen
 */
function getEmployeeIdByNames($pdo, $firstname, $lastname) {
    if (empty($firstname) && empty($lastname)) return false;

    $stmt = $pdo->prepare("SELECT id FROM person WHERE firstname = ? AND lastname = ? LIMIT 1");
    $stmt->execute([trim($firstname), trim($lastname)]);
    $personId = $stmt->fetchColumn();

    if (!$personId) return false;

    $stmt = $pdo->prepare("SELECT id FROM employee WHERE personid = ? LIMIT 1");
    $stmt->execute([$personId]);
    $employeeId = $stmt->fetchColumn();

    if ($employeeId) return $employeeId;

    $insEmp = $pdo->prepare("INSERT INTO employee (personid) VALUES (?)");
    $insEmp->execute([$personId]);
    return $pdo->lastInsertId();
}

if (isset($_POST['import_arbeitszeit_csv'])) {
    $csvData = $_POST['csv_text'];
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerChecked = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine)) continue;

        $data = str_getcsv($trimmedLine, ",", '"');

        if (!$headerChecked && (strpos($trimmedLine, 'ad') !== false || strpos($trimmedLine, 'kisiid') !== false)) {
            $headerChecked = true;
            continue;
        }

        if (count($data) < 21) {
            $report['errors'][] = "Ungültiges Format: " . $trimmedLine;
            continue;
        }

        // --- MAPPING ---
        $rawDate    = trim($data[2]);      // tarih
        $ilkfahrt   = trim($data[3]);      // ilkfahrt
        $sonfahrt   = trim($data[4]);      // sonfahrt
        $firstName  = trim($data[19]);     // ad
        $lastName   = trim($data[20]);     // soyad

        // --- VALIDIERUNG ---
        $dateObj = date_create($rawDate);
        $cleanDate = $dateObj ? date_format($dateObj, 'Y-m-d') : null;

        if (!$cleanDate) {
            $report['errors'][] = "Datum ungültig: " . $trimmedLine;
            continue;
        }

        // Zeitstempel für DB vorbereiten (NULL Handling)
        $firstTrip = ($ilkfahrt === 'NULL' || empty($ilkfahrt)) ? null : $ilkfahrt;
        $lastTrip  = ($sonfahrt === 'NULL' || empty($sonfahrt)) ? null : $sonfahrt;

        $employeeId = getEmployeeIdByNames($pdo, $firstName, $lastName);

        if ($employeeId === false) {
            $report['errors'][] = "Person nicht gefunden ('$firstName $lastName'). Zeile: " . $trimmedLine;
            continue;
        }

        // --- DB OPERATION ---
        $check = $pdo->prepare("SELECT id FROM workinghours WHERE employeeid = ? AND date = ? LIMIT 1");
        $check->execute([$employeeId, $cleanDate]);
        $existingId = $check->fetchColumn();

        $sqlData = [
            $employeeId, 
            $cleanDate, 
            $firstTrip,
            $lastTrip,
            "CSV Import (v01-260407 / Originalzeiten)"
        ];

        try {
            if ($existingId) {
                // UPDATE
                $sql = "UPDATE workinghours SET 
                            employeeid=?, date=?, firsttripat=?, lasttripat=?, 
                            note=?, updatedat=NOW() 
                        WHERE id = ?";
                $sqlData[] = $existingId;
                $pdo->prepare($sql)->execute($sqlData);
                $report['updates']++;
            } else {
                // INSERT
                $sql = "INSERT INTO workinghours (
                            employeeid, date, firsttripat, lasttripat, 
                            note, createdat
                        ) VALUES (?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute($sqlData);
                $report['inserts']++;
            }
        } catch (PDOException $e) {
            $report['errors'][] = "DB Fehler: " . $e->getMessage() . " | Zeile: " . $trimmedLine;
        }
    }
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff; position: relative;">
    <span style="position: absolute; top: 10px; right: 15px; font-size: 10px; color: #aaa;">v01-260407</span>
    <h3>🕒 Arbeitszeit CSV Import</h3>
    <p style="font-size: 0.85em; color: #555;">
        Mapping: <strong>date</strong>=tarih, <strong>firsttripat</strong>=ilkfahrt, <strong>lasttripat</strong>=sonfahrt. <br>
        Die Zeiten werden 1:1 ohne Rundung übernommen.
    </p>
    
    <form method="post">
        <textarea name="csv_text" rows="10" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd; font-size: 11px;" placeholder='"id","kisiid",...,"ad","soyad"'></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_arbeitszeit_csv" class="btn save" style="cursor:pointer; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; font-weight: bold;">
                Import starten
            </button>
        </div>
    </form>
</div>

<?php if (isset($_POST['import_arbeitszeit_csv'])): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff;">
    <h4>Import-Ergebnis</h4>
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div style="padding: 10px 20px; background: #dcfce7; color: #166534; border-radius: 4px; border: 1px solid #bbf7d0;">
            <strong>Updates:</strong> <?= $report['updates'] ?>
        </div>
        <div style="padding: 10px 20px; background: #dbeafe; color: #1e40af; border-radius: 4px; border: 1px solid #bfdbfe;">
            <strong>Inserts:</strong> <?= $report['inserts'] ?>
        </div>
        <div style="padding: 10px 20px; background: <?= empty($report['errors']) ? '#f3f4f6' : '#fee2e2' ?>; color: #991b1b; border-radius: 4px; border: 1px solid #fecaca;">
            <strong>Fehler:</strong> <?= count($report['errors']) ?>
        </div>
    </div>

    <?php if (!empty($report['errors'])): ?>
        <h5 style="color: #991b1b; margin-bottom: 5px;">Fehlerliste (komplette Zeilen):</h5>
        <div style="max-height: 300px; overflow-y: auto; font-size: 11px; background: #fff5f5; padding: 10px; border-radius: 4px; border: 1px solid #fecaca; white-space: pre-wrap; font-family: monospace;">
            <?php foreach ($report['errors'] as $error): ?>
                <div style="padding: 5px 0; border-bottom: 1px solid #fee2e2; color: #991b1b;">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>