<?php
/**
 * module/driveractivity.php
 * Fokus: Sekundengenaue Zeitstempel (Odometer & GPS inklusive)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: AKTIONEN ---

// SPEICHERN / UPDATEN / DUPLIZIEREN
if (isset($_POST['save_activity']) || isset($_POST['duplicate_activity'])) {
    $id                   = (isset($_POST['duplicate_activity'])) ? null : ($_POST['id'] ?? null);
    $driveractivitytypeid = $_POST['driveractivitytypeid'] ?: null;
    $driverid             = $_POST['driverid'] ?: null;
    $vehicleid            = $_POST['vehicleid'] ?: null;
    $tripid               = $_POST['tripid'] ?: null;
    
    // Falls das Feld leer ist, aktuelles Datum mit Sekunden nehmen
    $datetime             = !empty($_POST['datetime']) ? $_POST['datetime'] : date('Y-m-d H:i:s');
    
    $lat                  = $_POST['lat'] !== '' ? $_POST['lat'] : null;
    $lng                  = $_POST['lng'] !== '' ? $_POST['lng'] : null;
    $odometer             = $_POST['odometer'] !== '' ? (int)$_POST['odometer'] : null;
    $note                 = $_POST['note'] ?? '';

    $params = [$driveractivitytypeid, $driverid, $vehicleid, $tripid, $datetime, $lat, $lng, $odometer, $note];

    if (!empty($id)) {
        $sql = "UPDATE driveractivity SET 
                driveractivitytypeid=?, driverid=?, vehicleid=?, tripid=?, 
                datetime=?, lat=?, lng=?, odometer=?, note=?, 
                updateddate=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/driveractivity&msg=updated";
    } else {
        $sql = "INSERT INTO driveractivity 
                (driveractivitytypeid, driverid, vehicleid, tripid, 
                 datetime, lat, lng, odometer, note, createddate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/driveractivity&msg=created";
    }
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$drivers   = $pdo->query("SELECT d.id, p.lastname, p.firstname FROM driver d JOIN person p ON d.personid = p.id ORDER BY p.lastname ASC")->fetchAll();
$actTypes  = $pdo->query("SELECT id, name FROM driveractivitytype ORDER BY name ASC")->fetchAll();
$vehicles  = $pdo->query("SELECT id, licenseplate FROM vehicle ORDER BY licenseplate ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM driveractivity WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT da.*, p.lastname, p.firstname, dat.name as type_name, v.licenseplate 
                     FROM driveractivity da
                     LEFT JOIN driver d ON da.driverid = d.id
                     LEFT JOIN person p ON d.personid = p.id
                     LEFT JOIN driveractivitytype dat ON da.driveractivitytypeid = dat.id
                     LEFT JOIN vehicle v ON da.vehicleid = v.id
                     ORDER BY da.datetime DESC LIMIT 50")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🚩 Fahrer-Aktivität (Sekundengenau)</h3>
        <a href="/?route=module/driveractivity&edit=new" class="btn-action neu-bg" style="text-decoration:none;">+ Neu</a>
    </div>

    <form method="post" action="/?route=module/driveractivity" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row"><label>Fahrer</label>
                    <select name="driverid" required>
                        <option value="">-- wählen --</option>
                        <?php foreach($drivers as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit['driverid'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['lastname'].", ".$d['firstname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Aktivität</label>
                    <select name="driveractivitytypeid" required>
                        <?php foreach($actTypes as $at): ?>
                            <option value="<?= $at['id'] ?>" <?= ($edit['driveractivitytypeid'] ?? '') == $at['id'] ? 'selected' : '' ?>><?= htmlspecialchars($at['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Fahrzeug</label>
                    <select name="vehicleid">
                        <option value="">-- kein Fahrzeug --</option>
                        <?php foreach($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($edit['vehicleid'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['licenseplate']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row">
                    <label>Zeitpunkt</label>
                    <input type="datetime-local" name="datetime" step="1" 
                           value="<?= $edit['datetime'] ? date('Y-m-d\TH:i:s', strtotime($edit['edit'] ?? $edit['datetime'])) : date('Y-m-d\TH:i:s') ?>">
                </div>
                <div class="form-row"><label>Position (L/L)</label>
                    <input type="text" name="lat" placeholder="Lat" value="<?= $edit['lat'] ?? '' ?>" style="width:45%; margin-right:5px;">
                    <input type="text" name="lng" placeholder="Lng" value="<?= $edit['lng'] ?? '' ?>" style="width:45%;">
                </div>
                <div class="form-row"><label>KM-Stand</label>
                    <input type="number" name="odometer" value="<?= $edit['odometer'] ?? '' ?>">
                </div>
            </div>
        </div>
        <div class="form-row" style="margin-top:10px;"><label>Notiz</label><textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div>
                <?php if($edit): ?>
                    <a href="/?route=module/driveractivity&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Eintrag wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if($edit): ?>
                    <a href="/?route=module/driveractivity" class="btn-action cancel-bg" style="text-decoration:none;">Abbrechen</a>
                    <button type="submit" name="duplicate_activity" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                <?php endif; ?>
                <button type="submit" name="save_activity" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold;">
                    <?= $edit ? '💾 Update' : '💾 Speichern' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Zeitpunkt (s)</th>
                <th>Fahrer</th>
                <th>Aktivität</th>
                <th>Fahrzeug</th>
                <th>KM-Stand</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $da): ?>
            <tr>
                <td style="font-size:12px; font-family:monospace;">
                    <?= date('d.m.Y', strtotime($da['datetime'])) ?><br>
                    <strong><?= date('H:i:s', strtotime($da['datetime'])) ?></strong>
                </td>
                <td><strong><?= htmlspecialchars($da['lastname'].", ".$da['firstname']) ?></strong></td>
                <td><span class="badge"><?= htmlspecialchars($da['type_name']) ?></span></td>
                <td style="font-size:12px;"><?= htmlspecialchars($da['licenseplate'] ?: '-') ?></td>
                <td><?= $da['odometer'] ? number_format($da['odometer'], 0, ',', '.') . ' km' : '-' ?></td>
                <td style="text-align:right;">
                    <a href="/?route=module/driveractivity&edit=<?= $da['id'] ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
/* CSS Styles identisch zu vorher */
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 30px; }
.form-row label { width: 130px; font-weight: bold; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; }
.neu-bg { background: #f1f5f9; color: #3b82f6; border: 1px solid #3b82f6; }
.save-bg { background: #3b82f6; }
.dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; text-decoration:none; }
.cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; }
</style>