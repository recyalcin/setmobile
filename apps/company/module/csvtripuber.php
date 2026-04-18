<?php
/**
 * module/csvtripuber.php
 * Uber-Import: Fix für leere Datumsfelder (Empty String to NULL)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$importSummary = ["new" => 0, "update" => 0, "error" => 0];
$errorLog = [];

/**
 * Hilfsfunktion: Findet oder erstellt Referenz-IDs (Status, Typen, etc.)
 */
function findOrCreateRef($pdo, $table, $name) {
    if (empty(trim($name))) return null;
    $name = trim($name);
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) return $id;

    $ins = $pdo->prepare("INSERT INTO $table (name, createdat) VALUES (?, NOW())");
    $ins->execute([$name]);
    return $pdo->lastInsertId();
}

/**
 * Hilfsfunktion: Driver-ID über Person-Tabelle auflösen
 */
function getDriverId($pdo, $fullName) {
    if (empty(trim($fullName))) return null;
    $stmt = $pdo->prepare("SELECT id FROM person WHERE CONCAT(firstname, ' ', lastname) = ? OR lastname = ? LIMIT 1");
    $stmt->execute([$fullName, $fullName]);
    $personId = $stmt->fetchColumn();

    if (!$personId) {
        $parts = explode(' ', $fullName, 2);
        $first = $parts[0];
        $last = $parts[1] ?? $parts[0];
        $ins = $pdo->prepare("INSERT INTO person (firstname, lastname, createdat) VALUES (?, ?, NOW())");
        $ins->execute([$first, $last]);
        $personId = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("SELECT id FROM driver WHERE personid = ? LIMIT 1");
    $stmt->execute([$personId]);
    $driverId = $stmt->fetchColumn();

    if (!$driverId) {
        $ins = $pdo->prepare("INSERT INTO driver (personid, createdat) VALUES (?, NOW())");
        $ins->execute([$personId]);
        $driverId = $pdo->lastInsertId();
    }
    return $driverId;
}

/**
 * Hilfsfunktion: Fahrzeug-ID auflösen
 */
function getVehicleId($pdo, $plate) {
    if (empty(trim($plate))) return null;
    $stmt = $pdo->prepare("SELECT id FROM vehicle WHERE licenseplate = ? LIMIT 1");
    $stmt->execute([$plate]);
    $id = $stmt->fetchColumn();
    if ($id) return $id;

    $ins = $pdo->prepare("INSERT INTO vehicle (licenseplate, createdat) VALUES (?, NOW())");
    $ins->execute([$plate]);
    return $pdo->lastInsertId();
}

if (isset($_POST['import_uber_csv'])) {
    $csvData = $_POST['csv_text'];
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerChecked = false;

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $data = str_getcsv($line, ",");
        
        if (!$headerChecked && strpos($line, 'Fahrt-UUID') !== false) {
            $headerChecked = true;
            continue;
        }

        if (count($data) < 15) continue;

        try {
            // --- CSV MAPPING ---
            $fahrtUuid   = trim($data[0]);
            $fahrerUuid  = trim($data[1]);
            $fahrerName  = trim($data[2] . " " . $data[3]);
            $plate       = trim($data[5]);
            
            // FIX: Leere Datumsfelder zu NULL wandeln
            $reqTime     = !empty(trim($data[7]))  ? trim($data[7])  : null;  // submittedat
            $arrTime     = !empty(trim($data[8]))  ? trim($data[8])  : null;  // arrivedat
            $transTime   = !empty(trim($data[13])) ? trim($data[13]) : null;  // transmittedat
            $startTime   = !empty(trim($data[14])) ? trim($data[14]) : null;  // pickedupat
            
            $dist        = (float)str_replace(',', '.', $data[11]); 
            $statusStr   = trim($data[12]); 
            
            $coordsRaw = trim($data[15]);
            $lat = null; $lng = null;
            if (!empty($coordsRaw)) {
                $cParts = explode(' ', $coordsRaw);
                $lat = $cParts[0] ?? null;
                $lng = $cParts[1] ?? null;
            }

            $fareVal     = (float)str_replace(',', '.', $data[16]);
            $typeStr     = trim($data[17]); 
            $payStr      = trim($data[19]); 

            $driverId       = getDriverId($pdo, $fahrerName);
            $vehicleId      = getVehicleId($pdo, $plate);
            $triptypeid     = findOrCreateRef($pdo, 'triptype', $typeStr);
            $tripstatusid   = findOrCreateRef($pdo, 'tripstatus', $statusStr);
            $paymenttypeid  = findOrCreateRef($pdo, 'paymenttype', $payStr);
            $tripsourceid   = findOrCreateRef($pdo, 'tripsource', 'Uber');

            $note = "Fahrt-UUID=$fahrtUuid - Fahrer-UUID=$fahrerUuid";

            $check = $pdo->prepare("SELECT id FROM trip WHERE note LIKE ? LIMIT 1");
            $check->execute(["%Fahrt-UUID=$fahrtUuid%"]);
            $existingId = $check->fetchColumn();

            $sqlData = [
                $triptypeid, $tripstatusid, $tripsourceid, $vehicleId, $driverId,
                $reqTime, $transTime, $startTime, $arrTime,
                $lat, $lng, $dist, $fareVal, $paymenttypeid, $note
            ];

            if ($existingId) {
                $sql = "UPDATE trip SET 
                            triptypeid=?, tripstatusid=?, tripsourceid=?, vehicleid=?, driverid=?,
                            submittedat=?, transmittedat=?, pickedupat=?, arrivedat=?,
                            latvehiclelocationatorder=?, lngvehiclelocationatorder=?,
                            tripdistance=?, fare=?, paymenttypeid=?, note=?, updatedat=NOW()
                        WHERE id = ?";
                $sqlData[] = $existingId;
                $pdo->prepare($sql)->execute($sqlData);
                $importSummary["update"]++;
            } else {
                $sql = "INSERT INTO trip (
                            triptypeid, tripstatusid, tripsourceid, vehicleid, driverid,
                            submittedat, transmittedat, pickedupat, arrivedat,
                            latvehiclelocationatorder, lngvehiclelocationatorder,
                            tripdistance, fare, paymenttypeid, note, createdat
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute($sqlData);
                $importSummary["new"]++;
            }
        } catch (Exception $e) {
            $importSummary["error"]++;
            $errorLog[] = "Fehler bei UUID " . ($fahrtUuid ?? 'Unbekannt') . ": " . $e->getMessage();
        }
    }
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h3>🚗 Uber CSV-Import</h3>
    <form method="post">
        <textarea name="csv_text" rows="10" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd;" placeholder="Uber CSV Daten hier einfügen..."></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_uber_csv" class="btn save" style="cursor:pointer; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px;">In Tabelle 'trip' importieren</button>
        </div>
    </form>
</div>

<?php if ($importSummary["new"] > 0 || $importSummary["update"] > 0 || $importSummary["error"] > 0): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h4>Import-Journal</h4>
    <div style="padding: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; margin-bottom: 15px; font-weight: bold;">
        ✅ <?= $importSummary["new"] ?> neue Fahrten angelegt, 
        🔄 <?= $importSummary["update"] ?> Fahrten aktualisiert
        <?php if($importSummary["error"] > 0): ?>
            , ⚠️ <?= $importSummary["error"] ?> fehlgeschlagen
        <?php endif; ?>
    </div>
    <?php if (!empty($errorLog)): ?>
        <div style="margin-top: 10px;">
            <strong style="color: #dc2626;">Fehlgeschlagene Importe:</strong>
            <div style="max-height: 200px; overflow-y: auto; font-size: 11px; background: #fef2f2; padding: 10px; border: 1px solid #fecaca; margin-top: 5px;">
                <?php foreach ($errorLog as $err): ?>
                    <div style="padding: 2px 0; border-bottom: 1px solid #fee2e2; color: #991b1b;"><?= $err ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>