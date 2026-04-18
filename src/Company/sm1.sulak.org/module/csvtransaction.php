<?php
/**
 * module/csvtransaction.php
 * CSV-Import mit korrigierter Zahlen-Konvertierung (Tausender-Punkte)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$importLog = [];

// 1. HILFSFUNKTION: findOrCreate
function findOrCreate($pdo, $table, $name) {
    if (empty(trim($name))) return null;
    $name = trim($name);
    
    if ($table === 'vehicle') {
        $sql = "SELECT id FROM vehicle WHERE licenseplate = ? LIMIT 1";
        $params = [$name];
    } elseif ($table === 'person') {
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

    // Neuanlage
    if ($table === 'vehicle') {
        $ins = $pdo->prepare("INSERT INTO vehicle (licenseplate, createddate) VALUES (?, NOW())");
        $ins->execute([$name]);
    } elseif ($table === 'person') {
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

// 2. ENTITY TYPES & HAUPTKASSE
$entityTypes = $pdo->query("SELECT id, tablename FROM transactionentitytype")->fetchAll(PDO::FETCH_KEY_PAIR);
$typeCompany = array_search('company', $entityTypes);
$typeCashbox = array_search('cashbox', $entityTypes);

$hauptkasseId = $pdo->query("SELECT id FROM cashbox WHERE name LIKE '%Hauptkasse%' LIMIT 1")->fetchColumn();

// 3. IMPORT LOGIK
if (isset($_POST['import_csv'])) {
    $csvData = $_POST['csv_text'];
    $lines = explode("\n", str_replace("\r", "", $csvData));

    foreach ($lines as $line) {
        if (empty(trim($line)) || str_starts_with($line, 'officialtypeid')) continue;

        $data = str_getcsv($line, ",");
        if (count($data) < 7) continue;

        $officialtypeid    = (int)$data[0];
        $dateRaw           = trim($data[1]);

        // --- ZAHLEN-KONVERTIERUNG FIX ---
        // 1. Entferne eventuelle Anführungszeichen
        $rawIn  = str_replace('"', '', $data[2]);
        $rawOut = str_replace('"', '', $data[3]);

        // 2. Tausender-Punkt entfernen, dann Komma zu Punkt wandeln
        $valIn  = (float)str_replace(',', '.', str_replace('.', '', $rawIn));
        $valOut = (float)str_replace(',', '.', str_replace('.', '', $rawOut));
        // --------------------------------

        $fromToName        = trim($data[4]);
        $transTypeName     = trim($data[5]);
        $description       = trim($data[6]);
        $licensePlate      = isset($data[7]) ? trim($data[7]) : '';
        $fahrerName        = isset($data[8]) ? trim($data[8]) : '';

        // Datum konvertieren
        $d = DateTime::createFromFormat('d.m.Y', $dateRaw);
        $formattedDate = $d ? $d->format('Y-m-d') : date('Y-m-d');

        // Stammdaten auflösen
        $companyId   = findOrCreate($pdo, 'company', $fromToName);
        $transTypeId = findOrCreate($pdo, 'transactiontype', $transTypeName);
        $vehicleId   = findOrCreate($pdo, 'vehicle', $licensePlate);
        $personId    = findOrCreate($pdo, 'person', $fahrerName);

        $amount = ($valIn > 0) ? $valIn : $valOut;
        if ($valIn > 0) {
            $fromid = $companyId; $fromtypeid = $typeCompany;
            $toid = $hauptkasseId; $totypeid = $typeCashbox;
        } else {
            $fromid = $hauptkasseId; $fromtypeid = $typeCashbox;
            $toid = $companyId; $totypeid = $typeCompany;
        }

        // 4. LOOKUP & SAVE
        $check = $pdo->prepare("SELECT id FROM transaction WHERE date = ? AND amount = ? AND description = ? LIMIT 1");
        $check->execute([$formattedDate, $amount, $description]);
        $existingId = $check->fetchColumn();

        if ($existingId) {
            $sql = "UPDATE transaction SET transactiontypeid=?, officialtypeid=?, date=?, fromtypeid=?, fromid=?, totypeid=?, toid=?, amount=?, description=?, vehicleid=?, personid=?, updateddate=NOW() WHERE id=?";
            $pdo->prepare($sql)->execute([$transTypeId, $officialtypeid, $formattedDate, $fromtypeid, $fromid, $totypeid, $toid, $amount, $description, $vehicleId, $personId, $existingId]);
            $importLog[] = "🔄 ID $existingId: Update für '$description' (" . number_format($amount, 2, ',', '.') . " €)";
        } else {
            $sql = "INSERT INTO transaction (transactiontypeid, officialtypeid, date, fromtypeid, fromid, totypeid, toid, amount, description, vehicleid, personid, createddate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([$transTypeId, $officialtypeid, $formattedDate, $fromtypeid, $fromid, $totypeid, $toid, $amount, $description, $vehicleId, $personId]);
            $importLog[] = "✅ Neu: '$description' am $dateRaw (" . number_format($amount, 2, ',', '.') . " €)";
        }
    }
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h3>📥 CSV Transaktions-Import</h3>
    <p style="font-size: 0.9em; color: #666;">Format: officialtypeid, Datum (DD.MM.YYYY), Einnahme, Ausgabe, Partner, Typ, Beschreibung, Kennzeichen, Fahrer</p>
    <form method="post">
        <textarea name="csv_text" rows="8" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd;" placeholder="1,01.01.2024,1.250,50,0.00,Firma ABC,Verkauf,Rechnung 123,B-MW-123,Max Mustermann"></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_csv" class="btn save" style="cursor:pointer; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px;">Daten importieren</button>
        </div>
    </form>
</div>

<?php if (!empty($importLog)): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
    <h4>Import-Protokoll</h4>
    <div style="max-height: 400px; overflow-y: auto; font-size: 13px; background: #f8fafc; padding: 10px; border: 1px solid #eee;">
        <?php foreach ($importLog as $log): ?>
            <div style="padding: 5px 0; border-bottom: 1px solid #e2e8f0;"><?= $log ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>