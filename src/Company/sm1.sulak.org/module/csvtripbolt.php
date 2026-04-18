<?php
/**
 * module/csvtripbolt.php
 * Bolt-Import: Erweiterte Namenslogik (Vorname, Mittelname, Nachname)
 * ALLES ANDERE BLEIBT GLEICH!
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$importSummary = ["new" => 0, "update" => 0, "error" => 0];
$errorLog = [];

/**
 * Hilfsfunktion: Findet oder erstellt Referenz-IDs
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
 * Hilfsfunktion: Driver-ID auflösen mit Mittelnamen-Logik
 * Erster Teil = Vorname, Letzter Teil = Nachname, Rest = Mittelname
 */
function getDriverId($pdo, $fullName) {
    if (empty(trim($fullName))) return null;
    $fullName = trim($fullName);
    
    // Namen zerlegen (am Leerzeichen)
    $parts = preg_split('/\s+/', $fullName);
    $count = count($parts);

    if ($count === 1) {
        $first = $parts[0];
        $last  = $parts[0];
        $middles = "";
    } else {
        $first = $parts[0];
        $last  = $parts[$count - 1];
        // Alle Teile zwischen dem ersten und dem letzten sind Mittelnamen
        $middles = implode(' ', array_slice($parts, 1, $count - 2));
    }

    // Lookup: Benutze nur Vorname und Nachname
    $stmt = $pdo->prepare("SELECT id FROM person WHERE firstname = ? AND lastname = ? LIMIT 1");
    $stmt->execute([$first, $last]);
    $personId = $stmt->fetchColumn();

    if (!$personId) {
        // Falls nicht vorhanden, neu anlegen (inkl. middlename)
        $ins = $pdo->prepare("INSERT INTO person (firstname, middlename, lastname, createdat) VALUES (?, ?, ?, NOW())");
        $ins->execute([$first, $middles, $last]);
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
 * Hilfsfunktion: Fahrzeug-ID (Format: K-S-7009 -> K-S7009)
 */
function getVehicleId($pdo, $rawPlate) {
    if (empty(trim($rawPlate))) return null;
    $rawPlate = trim($rawPlate);
    $parts = explode('-', $rawPlate);
    if (count($parts) >= 3) {
        $formattedPlate = $parts[0] . "-" . $parts[1] . $parts[2];
    } else {
        $formattedPlate = $rawPlate;
    }
    $stmt = $pdo->prepare("SELECT id FROM vehicle WHERE licenseplate = ? LIMIT 1");
    $stmt->execute([$formattedPlate]);
    $id = $stmt->fetchColumn();
    if ($id) return $id;

    $ins = $pdo->prepare("INSERT INTO vehicle (licenseplate, createdat) VALUES (?, NOW())");
    $ins->execute([$formattedPlate]);
    return $pdo->lastInsertId();
}

if (isset($_POST['import_bolt_csv'])) {
    $csvData = $_POST['csv_text'];
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerChecked = false;

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $data = str_getcsv($line, ",");
        
        if (!$headerChecked && str_contains($line, 'Individueller Identifikator')) {
            $headerChecked = true;
            continue;
        }

        if (count($data) < 20) continue;

        try {
            // --- BOLT CSV MAPPING & LOGIC ---
            $datumStr   = trim($data[0]);  
            $tarifFin   = trim($data[1]);  
            $fahrerName = trim($data[3]);  
            $rawPlate   = trim($data[4]);  
            $route      = trim($data[6]);  
            $waitMin    = (float)str_replace(',', '.', $data[9]); 
            $statusStr  = trim($data[12]); 
            $dist       = (float)str_replace(',', '.', $data[14]); 
            $fareVal    = (float)str_replace(',', '.', $data[16]); 
            $payStr     = trim($data[17]); 

            // --- ZEITSTEMPEL BERECHNUNG ---
            $submittedat = $datumStr;
            $pickedupat = date("Y-m-d H:i:s", (int)(strtotime($datumStr) + ($waitMin * 60)));
            $arrivedat = ($statusStr === "Abgeschlossen" && !empty($tarifFin)) ? $tarifFin : null;

            // Kennzeichen Formatierung
            $p = explode('-', $rawPlate);
            $fmtPlate = (count($p) >= 3) ? $p[0]."-".$p[1].$p[2] : $rawPlate;

            // BOLT-REF: bolt-datum-fahrer-fahrzeug-route-tripstatus
            $note = "bolt-$datumStr-$fahrerName-$fmtPlate-$route-$statusStr";

            $driverId       = getDriverId($pdo, $fahrerName);
            $vehicleId      = getVehicleId($pdo, $rawPlate);
            $triptypeid      = findOrCreateRef($pdo, 'triptype', trim($data[11])); 
            $tripstatusid    = findOrCreateRef($pdo, 'tripstatus', $statusStr);
            $paymenttypeid   = findOrCreateRef($pdo, 'paymenttype', $payStr);
            $tripsourceid    = findOrCreateRef($pdo, 'tripsource', 'Bolt');

            // Duplikatsprüfung mit LIKE
            $check = $pdo->prepare("SELECT id FROM trip WHERE note LIKE ? LIMIT 1");
            $check->execute(["%$note%"]);
            $existingId = $check->fetchColumn();

            $sqlData = [
                $triptypeid, $tripstatusid, $tripsourceid, $vehicleId, $driverId,
                $submittedat, $pickedupat, $arrivedat,
                $dist, $fareVal, $paymenttypeid, $note
            ];

            if ($existingId) {
                $sql = "UPDATE trip SET 
                            triptypeid=?, tripstatusid=?, tripsourceid=?, vehicleid=?, driverid=?,
                            submittedat=?, pickedupat=?, arrivedat=?,
                            tripdistance=?, fare=?, paymenttypeid=?, note=?, updatedat=NOW()
                        WHERE id = ?";
                $sqlData[] = $existingId;
                $pdo->prepare($sql)->execute($sqlData);
                $importSummary["update"]++;
            } else {
                $sql = "INSERT INTO trip (
                            triptypeid, tripstatusid, tripsourceid, vehicleid, driverid,
                            submittedat, pickedupat, arrivedat,
                            tripdistance, fare, paymenttypeid, note, createdat
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute($sqlData);
                $importSummary["new"]++;
            }
        } catch (Exception $e) {
            $importSummary["error"]++;
            $errorLog[] = "Fehler bei Eintrag vom $datumStr: " . $e->getMessage();
        }
    }
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h3>⚡ Bolt CSV-Import</h3>
    <form method="post">
        <textarea name="csv_text" rows="10" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd;" placeholder="Bolt CSV Daten hier einfügen..."></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_bolt_csv" class="btn save" style="cursor:pointer; padding: 10px 20px; background: #34d399; color: white; border: none; border-radius: 4px;">In Tabelle 'trip' importieren</button>
        </div>
    </form>
</div>

<?php if ($importSummary["new"] > 0 || $importSummary["update"] > 0 || $importSummary["error"] > 0): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h4>Import-Journal</h4>
    <div style="padding: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; margin-bottom: 15px; font-weight: bold;">
        ✅ <?= $importSummary["new"] ?> neu, 🔄 <?= $importSummary["update"] ?> aktualisiert
        <?php if($importSummary["error"] > 0): ?>
            , ⚠️ <?= $importSummary["error"] ?> Fehler
        <?php endif; ?>
    </div>
    <?php if (!empty($errorLog)): ?>
        <div style="max-height: 200px; overflow-y: auto; font-size: 11px; background: #fef2f2; padding: 10px; border: 1px solid #fecaca;">
            <?php foreach ($errorLog as $err): ?>
                <div style="color: #991b1b;"><?= $err ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>