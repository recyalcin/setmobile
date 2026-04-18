<?php
/**
 * module/csvperson.php
 * Personen-Import mit automatischer Duplikat-Bereinigung
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$importLog = [];

/**
 * Funktion zur Bereinigung der Datenbank (entfernt doppelte Namen)
 */
function cleanupDuplicates($pdo) {
    // Behält den ältesten Eintrag (kleinste ID) und löscht neuere Duplikate mit gleichem Namen
    $sql = "DELETE p1 FROM person p1
            INNER JOIN person p2 
            WHERE p1.id > p2.id 
            AND TRIM(p1.firstname) <=> TRIM(p2.firstname)
            AND TRIM(p1.lastname) <=> TRIM(p2.lastname)";
    return $pdo->exec($sql);
}

if (isset($_POST['import_person_csv'])) {
    $csvData = $_POST['csv_text'];
    $rows = str_getcsv($csvData, "\n");
    
    if (count($rows) < 2) {
        $importLog[] = "❌ Fehler: Keine Daten gefunden.";
    } else {
        $header = str_getcsv(array_shift($rows), ",");
        $header = array_map(function($h) { return trim($h, " \t\n\r\0\x0B\""); }, $header);
        $colMap = array_flip($header);

        foreach ($rows as $row) {
            if (empty(trim($row))) continue;
            $data = str_getcsv($row, ",");
            
            $get = function($key) use ($colMap, $data) {
                return (isset($colMap[$key]) && isset($data[$colMap[$key]])) ? trim($data[$colMap[$key]]) : null;
            };

            // Mapping & Bereinigung von Leerzeichen
            $fname = $get('ad');
            $mname = $get('ad1');
            $lname = $get('soyad');
            $firma = $get('firma');

            if (empty($fname) && empty($lname) && !empty($firma)) {
                $lastname = $firma; $firstname = null;
            } else {
                $firstname = $fname; $lastname = $lname;
            }

            $middlename = $mname;
            $street  = $get('str');
            $housenr = $get('hausnr');
            $city    = $get('ort');
            $note    = $get('aciklama');

            if (empty($lastname)) continue;

            // --- STRIKTE PRÜFUNG AUF DUPLIKATE (TRIM + NULL-SAFE) ---
            $check = $pdo->prepare("SELECT id FROM person WHERE TRIM(firstname) <=> TRIM(?) AND TRIM(lastname) <=> TRIM(?) LIMIT 1");
            $check->execute([$firstname, $lastname]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                // UPDATE: Wir aktualisieren nur leere Felder oder Zeitstempel
                $sql = "UPDATE person SET 
                            middlename = COALESCE(middlename, ?),
                            street = COALESCE(street, ?),
                            housenr = COALESCE(housenr, ?),
                            city = COALESCE(city, ?),
                            note = CONCAT_WS(' | ', note, ?),
                            updatedat = NOW()
                        WHERE id = ?";
                $pdo->prepare($sql)->execute([$middlename, $street, $housenr, $city, $note, $existingId]);
                $importLog[] = "🔄 Existiert: $firstname $lastname (ID $existingId aktualisiert)";
            } else {
                // INSERT
                $sql = "INSERT INTO person (firstname, middlename, lastname, street, housenr, city, note, createdat, updatedat) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $pdo->prepare($sql)->execute([$firstname, $middlename, $lastname, $street, $housenr, $city, $note]);
                $importLog[] = "✅ Neu: $firstname $lastname";
            }
        }
        
        // --- AUTOMATISCHE BEREINIGUNG NACH IMPORT ---
        $deletedCount = cleanupDuplicates($pdo);
        if ($deletedCount > 0) {
            $importLog[] = "🧹 Info: $deletedCount redundante Duplikate wurden aus der Datenbank entfernt.";
        }
    }
}

// Manuelle Bereinigung triggern
if (isset($_POST['run_cleanup'])) {
    $count = cleanupDuplicates($pdo);
    $importLog[] = "🧹 Manuelle Bereinigung abgeschlossen: $count Duplikate gelöscht.";
}
?>

<div class="card" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin:0;">👥 Personen Import & Cleanup</h3>
        <form method="post" style="margin:0;">
            <button type="submit" name="run_cleanup" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                Datenbank jetzt bereinigen
            </button>
        </form>
    </div>
    
    <p style="font-size: 11px; color: #666; margin: 10px 0 20px 0;">
        Dieses Script verhindert Duplikate durch <b>Vorname + Nachname Abgleich</b>. 
        Nach dem Import werden verbliebene Dubletten automatisch gelöscht (der älteste Eintrag bleibt erhalten).
    </p>

    <form method="post">
        <textarea name="csv_text" rows="10" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="CSV Daten hier einfügen..."></textarea>
        <div style="margin-top: 15px;">
            <button type="submit" name="import_person_csv" style="cursor:pointer; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; font-weight: bold;">
                Sicherer Import starten
            </button>
        </div>
    </form>
</div>

<?php if (!empty($importLog)): ?>
<div class="card" style="border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #fff;">
    <h4 style="margin-top:0;">Protokoll</h4>
    <div style="max-height: 300px; overflow-y: auto; font-size: 11px; background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0;">
        <?php foreach ($importLog as $log): ?>
            <div style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($log) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>