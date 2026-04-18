<?php
/**
 * module/trip.php
 * Fokus: Fahrten-Management mit angepassten Zeitstempeln (createdat/updatedat) und Suchbereich
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: AKTIONEN ---

if (isset($_POST['save_trip']) || isset($_POST['duplicate_trip'])) {
    $id = (isset($_POST['duplicate_trip'])) ? null : ($_POST['id'] ?? null);
    
    $fields = [
        'triptypeid', 'tripstatusid', 'tripsourceid', 'vehicleid', 'driverid',
        'submittedat', 'transmittedat', 'respondedat', 'pickedupat', 'arrivedat',
        'latvehiclelocationatorder', 'lngvehiclelocationatorder', 
        'latpickuplocation', 'lngpickuplocation', 'latdropofflocation', 'lngdropofflocation',
        'pickupdistance', 'tripdistance', 'fare', 'paymenttypeid', 'note'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        // Datumsfelder checken (wenn leer, dann NULL)
        if (strpos($f, 'at') !== false && empty($val)) {
            $params[] = null;
        } else {
            $params[] = ($val !== '') ? $val : null;
        }
    }

    if (!empty($id)) {
        // UPDATE: Nutzt jetzt updatedat
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE trip SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/trip&msg=updated";
    } else {
        // INSERT: Nutzt jetzt createdat
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO trip ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/trip&msg=created";
    }
}

// LÖSCHEN LOGIK
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM trip WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/trip&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$drivers      = $pdo->query("SELECT d.id, p.lastname, p.firstname FROM driver d JOIN person p ON d.personid = p.id ORDER BY p.lastname ASC")->fetchAll();
$vehicles     = $pdo->query("SELECT id, licenseplate FROM vehicle ORDER BY licenseplate ASC")->fetchAll();
$tripTypes    = $pdo->query("SELECT id, name FROM triptype ORDER BY name ASC")->fetchAll();
$tripStatuses = $pdo->query("SELECT id, name FROM tripstatus ORDER BY name ASC")->fetchAll();
$paymentTypes = $pdo->query("SELECT id, name FROM paymenttype ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM trip WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 3. SUCHLOGIK ---
$s_date_start = $_GET['s_date_start'] ?? '';
$s_date_end   = $_GET['s_date_end'] ?? '';
$s_driver     = $_GET['s_driver'] ?? '';
$s_vehicle    = $_GET['s_vehicle'] ?? '';
$s_text       = $_GET['s_text'] ?? '';

$where = ["1=1"];
$queryParams = [];

if ($s_date_start) { 
    $where[] = "DATE(t.submittedat) >= ?"; 
    $queryParams[] = $s_date_start; 
}
if ($s_date_end) { 
    $where[] = "DATE(t.submittedat) <= ?"; 
    $queryParams[] = $s_date_end; 
}
if ($s_driver) { 
    $where[] = "t.driverid = ?"; 
    $queryParams[] = $s_driver; 
}
if ($s_vehicle) { 
    $where[] = "t.vehicleid = ?"; 
    $queryParams[] = $s_vehicle; 
}
if ($s_text) {
    $where[] = "(t.note LIKE ? OR p.lastname LIKE ? OR p.firstname LIKE ? OR v.licenseplate LIKE ? OR t.id LIKE ?)";
    $textParam = "%$s_text%";
    $queryParams = array_merge($queryParams, [$textParam, $textParam, $textParam, $textParam, $textParam]);
}

$sql = "SELECT t.*, p.lastname, p.firstname, v.licenseplate, ts.name as status_name, tsrc.name as source_name 
        FROM trip t
        LEFT JOIN driver d ON t.driverid = d.id
        LEFT JOIN person p ON d.personid = p.id
        LEFT JOIN vehicle v ON t.vehicleid = v.id
        LEFT JOIN tripstatus ts ON t.tripstatusid = ts.id
        LEFT JOIN tripsource tsrc ON t.tripsourceid = tsrc.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY t.submittedat ASC, t.pickedupat ASC, t.arrivedat ASC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($queryParams);
$list = $stmtList->fetchAll();

// --- 4. SUMMENBERECHNUNG ---
$sum_source = [];
$sum_status = [];
foreach ($list as $t) {
    $srcName = $t['source_name'] ?: 'Unbekannt';
    $staName = $t['status_name'] ?: 'Unbekannt';
    $sum_source[$srcName] = ($sum_source[$srcName] ?? 0) + 1;
    $sum_status[$staName] = ($sum_status[$staName] ?? 0) + 1;
}
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🚕 Fahrten-Management (Trip)</h3>
        <a href="/trip&edit=new" class="btn-action neu-bg" style="text-decoration:none;">+ Neue Fahrt</a>
    </div>

    <form method="post" action="/trip" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row"><label>Fahrer</label>
                    <select name="driverid">
                        <option value="">-- wählen --</option>
                        <?php foreach($drivers as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit['driverid'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['lastname'].", ".$d['firstname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Fahrzeug</label>
                    <select name="vehicleid">
                        <option value="">-- wählen --</option>
                        <?php foreach($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($edit['vehicleid'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['licenseplate']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Typ / Status</label>
                    <select name="triptypeid" style="width:48%; margin-right:2%;">
                        <option value="">Typ...</option>
                        <?php foreach($tripTypes as $tt): ?>
                            <option value="<?= $tt['id'] ?>" <?= ($edit['triptypeid'] ?? '') == $tt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tripstatusid" style="width:48%;">
                        <option value="">Status...</option>
                        <?php foreach($tripStatuses as $ts): ?>
                            <option value="<?= $ts['id'] ?>" <?= ($edit['tripstatusid'] ?? '') == $ts['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ts['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Bezahlung</label>
                    <select name="paymenttypeid" style="width:50%;">
                        <option value="">-- wählen --</option>
                        <?php foreach($paymentTypes as $pt): ?>
                            <option value="<?= $pt['id'] ?>" <?= ($edit['paymenttypeid'] ?? '') == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="fare" step="0.01" placeholder="Preis" value="<?= $edit['fare'] ?? '' ?>" style="width:45%; margin-left:5%;">
                </div>
            </div>

            <div>
                <div class="form-row"><label>Submitted</label><input type="datetime-local" name="submittedat" step="1" value="<?= isset($edit['submittedat']) ? date('Y-m-d\TH:i:s', strtotime($edit['submittedat'])) : '' ?>"></div>
                <div class="form-row"><label>Picked Up</label><input type="datetime-local" name="pickedupat" step="1" value="<?= isset($edit['pickedupat']) ? date('Y-m-d\TH:i:s', strtotime($edit['pickedupat'])) : '' ?>"></div>
                <div class="form-row"><label>Arrived</label><input type="datetime-local" name="arrivedat" step="1" value="<?= isset($edit['arrivedat']) ? date('Y-m-d\TH:i:s', strtotime($edit['arrivedat'])) : '' ?>"></div>
                <div class="form-row"><label>Distanzen</label>
                    <input type="number" name="pickupdistance" step="0.1" placeholder="Anfahrt" value="<?= $edit['pickupdistance'] ?? '' ?>" style="width:45%; margin-right:5%;">
                    <input type="number" name="tripdistance" step="0.1" placeholder="Fahrt" value="<?= $edit['tripdistance'] ?? '' ?>" style="width:45%;">
                </div>
            </div>

            <div>
                <div class="form-row"><label>Pickup L/L</label>
                    <input type="text" name="latpickuplocation" placeholder="Lat" value="<?= $edit['latpickuplocation'] ?? '' ?>" style="width:45%; margin-right:5%;">
                    <input type="text" name="lngpickuplocation" placeholder="Lng" value="<?= $edit['lngpickuplocation'] ?? '' ?>" style="width:45%;">
                </div>
                <div class="form-row"><label>Dropoff L/L</label>
                    <input type="text" name="latdropofflocation" placeholder="Lat" value="<?= $edit['latdropofflocation'] ?? '' ?>" style="width:45%; margin-right:5%;">
                    <input type="text" name="lngdropofflocation" placeholder="Lng" value="<?= $edit['lngdropofflocation'] ?? '' ?>" style="width:45%;">
                </div>
                <div class="form-row"><label>Notiz</label>
                    <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <input type="hidden" name="tripsourceid" value="<?= $edit['tripsourceid'] ?? '1' ?>">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div>
                <?php if($edit): ?>
                    <a href="/trip&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Fahrt wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if($edit): ?>
                    <a href="/trip" class="btn-action cancel-bg" style="text-decoration:none;">Abbrechen</a>
                    <button type="submit" name="duplicate_trip" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                <?php endif; ?>
                <button type="submit" name="save_trip" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#10b981;">
                    <?= $edit ? '💾 Update' : '💾 Speichern' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom: 25px; background: #f8fafc;">
    <form method="get" action="/" class="form-container">
        <input type="hidden" name="route" value="module/trip">
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; align-items: flex-start;">
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <div style="display: flex; gap: 5px;">
                    <div style="flex: 1;">
                        <label style="font-size: 11px; font-weight: bold; color: #64748b;">Datum von</label>
                        <input type="date" name="s_date_start" value="<?= htmlspecialchars($s_date_start) ?>" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 11px; font-weight: bold; color: #64748b;">bis</label>
                        <input type="date" name="s_date_end" value="<?= htmlspecialchars($s_date_end) ?>" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                    </div>
                </div>
                <div style="display: flex; gap: 5px;">
                    <button type="button" onclick="setSearchDateRange('last_week')" style="font-size: 10px; padding: 2px 5px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; cursor: pointer;">letzte Woche</button>
                    <button type="button" onclick="setSearchDateRange('last_month')" style="font-size: 10px; padding: 2px 5px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; cursor: pointer;">letzter Monat</button>
                </div>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: bold; color: #64748b;">Fahrer</label>
                <select name="s_driver" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                    <option value="">-- alle --</option>
                    <?php foreach($drivers as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $s_driver == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['lastname'].", ".$d['firstname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: bold; color: #64748b;">Fahrzeug</label>
                <select name="s_vehicle" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                    <option value="">-- alle --</option>
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $s_vehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['licenseplate']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: bold; color: #64748b;">Textsuche</label>
                <input type="text" name="s_text" value="<?= htmlspecialchars($s_text) ?>" placeholder="ID, Notiz, Kennzeichen..." style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <div style="display: flex; gap: 5px; align-self: flex-end; height: 31px;">
                <button type="submit" class="btn save-bg" style="flex:1; padding:7px; border:none; border-radius:4px; color:white; font-weight:bold; cursor:pointer; background:#64748b;">🔍 Filtern</button>
                <a href="/trip" class="btn-action cancel-bg" style="padding:7px; text-decoration:none;">✖</a>
            </div>
        </div>
    </form>
</div>

<div class="card" style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Datum</th>
                <th>Fahrer</th>
                <th>Fahrzeug</th>
                <th>Source</th>
                <th>Subm.</th>
                <th>Pick.</th>
                <th>Arr.</th>
                <th>Preis</th>
                <th>KM</th>
                <th>Status</th>
                <th style="text-align:right;">Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $t): ?>
            <tr style="font-size: 12px;">
                <td><small>#<?= $t['id'] ?></small></td>
                <td><?= $t['submittedat'] ? date('d.m.y', strtotime($t['submittedat'])) : '-' ?></td>
                <td><strong><?= htmlspecialchars(($t['lastname'] ?? '').", ".($t['firstname'] ?? '')) ?></strong></td>
                <td><?= htmlspecialchars($t['licenseplate'] ?: '-') ?></td>
                <td><small><?= htmlspecialchars($t['source_name'] ?? '-') ?></small></td>
                <td><?= $t['submittedat'] ? date('H:i', strtotime($t['submittedat'])) : '-' ?></td>
                <td><?= $t['pickedupat'] ? date('H:i', strtotime($t['pickedupat'])) : '-' ?></td>
                <td><?= $t['arrivedat'] ? date('H:i', strtotime($t['arrivedat'])) : '-' ?></td>
                <td><strong><?= number_format($t['fare'] ?? 0, 2, ',', '.') ?> €</strong></td>
                <td><?= $t['tripdistance'] ?: '-' ?></td>
                <td><span class="badge" style="background:#ecfdf5; color:#065f46;"><?= htmlspecialchars($t['status_name'] ?? 'Unbekannt') ?></span></td>
                <td style="text-align:right;">
                    <a href="/trip&edit=<?= $t['id'] ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($list)): ?>
                <tr><td colspan="12" style="text-align:center; padding:20px; color:#94a3b8;">Keine Fahrten gefunden.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-size: 11px; font-weight: bold; border-top: 2px solid #cbd5e1;">
                <td colspan="4" style="text-align: right; color: #64748b;">SUMMEN (gefiltert):</td>
                <td>
                    <?php foreach($sum_source as $name => $count) echo htmlspecialchars($name).": $count<br>"; ?>
                </td>
                <td colspan="5"></td>
                <td>
                    <?php foreach($sum_status as $name => $count) echo htmlspecialchars($name).": $count<br>"; ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
function setSearchDateRange(range) {
    const startInput = document.querySelector('input[name="s_date_start"]');
    const endInput = document.querySelector('input[name="s_date_end"]');
    const today = new Date();
    let start, end;

    if (range === 'last_week') {
        // Letzte Woche: Montag bis Sonntag
        let day = today.getDay(); // 0 (So) bis 6 (Sa)
        let diffToLastSun = (day === 0) ? 7 : day; 
        end = new Date(today);
        end.setDate(today.getDate() - diffToLastSun); // Letzter Sonntag
        start = new Date(end);
        start.setDate(end.getDate() - 6); // Montag davor
    } else if (range === 'last_month') {
        // Letzter Monat: 1. bis Letzter des Vormonats
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    }

    if (start && end) {
        const formatDate = (date) => {
            let d = new Date(date),
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        };
        startInput.value = formatDate(start);
        endInput.value = formatDate(end);
    }
}
</script>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
.form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; }
.neu-bg { background: #f0fdf4; color: #10b981; border: 1px solid #10b981; }
.dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; text-decoration:none; }
.cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 8px 6px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.edit-link { font-size: 18px; text-decoration: none; color: #10b981; }
</style>