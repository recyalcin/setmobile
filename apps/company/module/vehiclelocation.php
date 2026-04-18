<?php
/**
 * module/vehiclelocation.php
 * Fahrzeug-Standorte (Sekundengenau)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$redirect = false;

// --- 1. LOGIK: SPEICHERN ---
if (isset($_POST['save_location'])) {
    $id = $_POST['id'] ?? null;
    $params = [
        $_POST['vehicleid'] ?: null,
        $_POST['driverid'] ?: null,
        $_POST['tripid'] ?: null,
        $_POST['datetime'] ?: date('Y-m-d H:i:s'), // Default mit Sekunden
        $_POST['lat'] !== '' ? $_POST['lat'] : null,
        $_POST['lng'] !== '' ? $_POST['lng'] : null,
        $_POST['speed'] !== '' ? $_POST['speed'] : null,
        $_POST['heading'] !== '' ? $_POST['heading'] : null,
        $_POST['accuracy'] !== '' ? $_POST['accuracy'] : null,
        $_POST['note'] ?? ''
    ];

    if (!empty($id)) {
        $sql = "UPDATE vehiclelocation SET 
                vehicleid=?, driverid=?, tripid=?, datetime=?, 
                lat=?, lng=?, speed=?, heading=?, accuracy=?, note=?, 
                updateddate=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/vehiclelocation&msg=updated";
    } else {
        $sql = "INSERT INTO vehiclelocation 
                (vehicleid, driverid, tripid, datetime, lat, lng, speed, heading, accuracy, note, createddate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/vehiclelocation&msg=created";
    }
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$driverList = $pdo->query("SELECT d.id as driver_id, p.lastname, p.firstname 
                           FROM driver d 
                           JOIN person p ON d.personid = p.id 
                           ORDER BY p.lastname ASC")->fetchAll();

$vehicles = $pdo->query("SELECT id, licenseplate FROM vehicle ORDER BY licenseplate ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM vehiclelocation WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 3. LISTE ---
$list = $pdo->query("SELECT vl.*, v.licenseplate, p.lastname, p.firstname 
                     FROM vehiclelocation vl
                     LEFT JOIN vehicle v ON vl.vehicleid = v.id
                     LEFT JOIN driver d ON vl.driverid = d.id
                     LEFT JOIN person p ON d.personid = p.id
                     ORDER BY vl.datetime DESC LIMIT 50")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <h3>📍 Standort-Erfassung (Sekundengenau)</h3>
    <form method="post" action="/vehiclelocation" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row"><label>Fahrzeug</label>
                    <select name="vehicleid" required>
                        <option value="">-- wählen --</option>
                        <?php foreach($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($edit['vehicleid'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['licenseplate']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Zeitpunkt</label>
                    <input type="datetime-local" name="datetime" step="1" 
                           value="<?= isset($edit['datetime']) ? date('Y-m-d\TH:i:s', strtotime($edit['datetime'])) : date('Y-m-d\TH:i:s') ?>">
                </div>
                <div class="form-row"><label>Fahrer</label>
                    <select name="driverid">
                        <option value="">-- wählen --</option>
                        <?php foreach($driverList as $d): ?>
                            <option value="<?= $d['driver_id'] ?>" <?= ($edit['driverid'] ?? '') == $d['driver_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['lastname'].", ".$d['firstname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row"><label>Koordinaten</label>
                    <input type="text" name="lat" placeholder="Lat" style="width:45%;" value="<?= htmlspecialchars($edit['lat'] ?? '') ?>">
                    <input type="text" name="lng" placeholder="Lng" style="width:45%; margin-left:5px;" value="<?= htmlspecialchars($edit['lng'] ?? '') ?>">
                </div>
                <div class="form-row"><label>Speed (km/h)</label>
                    <input type="number" step="0.01" name="speed" value="<?= htmlspecialchars($edit['speed'] ?? '') ?>">
                </div>
                <div class="form-row"><label>Trip ID</label>
                    <input type="number" name="tripid" value="<?= htmlspecialchars($edit['tripid'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div style="margin-top: 15px; text-align: right;">
            <button type="submit" name="save_location" class="btn save" style="padding: 10px 30px;">Speichern</button>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Zeitpunkt</th>
                <th>Fahrzeug</th>
                <th>Fahrer</th>
                <th>Position</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $vl): ?>
            <tr>
                <td style="white-space: nowrap;"><strong><?= date('d.m.Y H:i:s', strtotime($vl['datetime'])) ?></strong></td>
                <td><span class="badge" style="background:#f0fdf4; color:#166534;">🚗 <?= htmlspecialchars($vl['licenseplate'] ?: '-') ?></span></td>
                <td><?= htmlspecialchars($vl['lastname'] ? $vl['lastname'].", ".$vl['firstname'] : '-') ?></td>
                <td style="font-size:11px; color:#64748b;">
                    <?= $vl['lat'] ? $vl['lat'].", ".$vl['lng'] : 'Kein GPS' ?>
                    <?= $vl['speed'] ? " | 💨 ".$vl['speed']." km/h" : "" ?>
                </td>
                <td style="text-align:right;">
                    <a href="/vehiclelocation&edit=<?= $vl['id'] ?>" class="action-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 130px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select { flex-grow: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn.save { background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight:bold; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.action-link { text-decoration: none; font-size: 18px; color: #3b82f6; }
</style>