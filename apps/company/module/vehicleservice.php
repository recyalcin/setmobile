<?php
/**
 * module/vehicleservice.php
 * Fahrzeug-Serviceverwaltung: Formular + Journal mit dynamischem Layout
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM vehicleservice WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/vehicleservice&msg=deleted";
}

if (isset($_POST['save_vehicleservice'])) {
    $id                   = $_POST['id'] ?? null;
    $vehicleservicetypeid = $_POST['vehicleservicetypeid'] ?: null;
    $vehicleid            = $_POST['vehicleid'] ?: null;
    $companyid            = $_POST['companyid'] ?: null;
    $vehicleservicetaskid = $_POST['vehicleservicetaskid'] ?: null;
    $datetime             = $_POST['datetime'] ?: null;
    $description          = $_POST['description'] ?? '';
    $odometer             = $_POST['odometer'] !== '' ? (int)$_POST['odometer'] : null;
    $totalamount          = $_POST['totalamount'] !== '' ? str_replace(',', '.', $_POST['totalamount']) : null;
    $invoicenumber        = $_POST['invoicenumber'] ?? '';
    $nextserviceat        = $_POST['nextserviceat'] ?: null;
    $nextserviceodometer  = $_POST['nextserviceodometer'] !== '' ? (int)$_POST['nextserviceodometer'] : null;
    $note                 = $_POST['note'] ?? '';

    $params = [
        $vehicleservicetypeid, $vehicleid, $companyid, $vehicleservicetaskid, 
        $datetime, $description, $odometer, $totalamount, $invoicenumber, 
        $nextserviceat, $nextserviceodometer, $note
    ];

    if (!empty($id)) {
        $sql = "UPDATE vehicleservice SET 
                vehicleservicetypeid=?, vehicleid=?, companyid=?, vehicleservicetaskid=?, 
                datetime=?, description=?, odometer=?, totalamount=?, invoicenumber=?, 
                nextserviceat=?, nextserviceodometer=?, note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicleservice&msg=updated";
    } else {
        $sql = "INSERT INTO vehicleservice 
                (vehicleservicetypeid, vehicleid, companyid, vehicleservicetaskid, 
                 datetime, description, odometer, totalamount, invoicenumber, 
                 nextserviceat, nextserviceodometer, note, createdat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicleservice&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. FILTER & PAGINATION ---
$limit  = 25;
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;
$f_q    = $_GET['q'] ?? '';
$isSearching = !empty($f_q);

$where = ["1=1"];
$p_sql = [];
if ($f_q) {
    $where[] = "(vs.description LIKE ? OR vs.invoicenumber LIKE ? OR v.licenseplate LIKE ?)";
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%";
}
$whereSql = implode(" AND ", $where);

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM vehicleservice vs LEFT JOIN vehicle v ON vs.vehicleid = v.id WHERE $whereSql");
$totalCount->execute($p_sql);
$totalCount = $totalCount->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. STAMMDATEN LADEN ---
$vehicles = $pdo->query("SELECT id, licenseplate FROM vehicle ORDER BY licenseplate ASC")->fetchAll();
$types    = $pdo->query("SELECT id, name FROM vehicleservicetype ORDER BY name ASC")->fetchAll();
$companies = $pdo->query("SELECT id, name FROM company ORDER BY name ASC")->fetchAll();
$tasks    = $pdo->query("SELECT id, name FROM vehicleservicetask ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM vehicleservice WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$listStmt = $pdo->prepare("SELECT vs.*, v.licenseplate, vst.name as typename, co.name as companyname, t.name as taskname
                            FROM vehicleservice vs
                            LEFT JOIN vehicle v ON vs.vehicleid = v.id
                            LEFT JOIN vehicleservicetype vst ON vs.vehicleservicetypeid = vst.id
                            LEFT JOIN company co ON vs.companyid = co.id
                            LEFT JOIN vehicleservicetask t ON vs.vehicleservicetaskid = t.id
                            WHERE $whereSql ORDER BY vs.datetime DESC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1;">
    <form method="get" action="/" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/vehicleservice">
        <div style="flex: 1; min-width: 250px;">
            <label class="filter-label">🔍 Suche (Beschreibung, Rechnung, Kennzeichen)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" class="filter-input" placeholder="Suchen..." style="width: 100%; box-sizing: border-box; margin: 0;">
        </div>
        <button type="submit" class="btn save" style="height: 38px; padding: 0 20px; margin: 0; display: inline-flex; align-items: center; justify-content: center; border: none; font-size: 14px; font-weight: 600; cursor: pointer; box-sizing: border-box;">Suchen</button>
        <a href="/?route=module/vehicleservice" class="btn reset-btn" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; background: #cbd5e1; color: #333; text-decoration: none; padding: 0 15px; border-radius: 4px; font-size: 14px; font-weight: 600; box-sizing: border-box; margin: 0;">Reset</a>
    </form>
</div>

<?php 
$renderForm = function() use ($edit, $vehicles, $types, $companies, $tasks) { ?>
    <div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
        <h3 style="margin-bottom: 15px;">🔧 <?= $edit ? 'Service-Eintrag bearbeiten' : 'Neuen Service-Eintrag anlegen' ?></h3>
        <form method="post" action="/?route=module/vehicleservice" class="form-container">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="form-row"><label>Datum/Zeit</label>
                        <input type="datetime-local" name="datetime" value="<?= $edit['datetime'] ? date('Y-m-d\TH:i', strtotime($edit['datetime'])) : date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="form-row"><label>Fahrzeug</label>
                        <select name="vehicleid" required>
                            <option value="">-- wählen --</option>
                            <?php foreach($vehicles as $v): ?><option value="<?= $v['id'] ?>" <?= ($edit['vehicleid'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['licenseplate']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Service-Typ</label>
                        <select name="vehicleservicetypeid">
                            <option value="">-- wählen --</option>
                            <?php foreach($types as $t): ?><option value="<?= $t['id'] ?>" <?= ($edit['vehicleservicetypeid'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Werkstatt/Firma</label>
                        <select name="companyid">
                            <option value="">-- wählen --</option>
                            <?php foreach($companies as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['companyid'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Aufgabe</label>
                        <select name="vehicleservicetaskid">
                            <option value="">-- wählen --</option>
                            <?php foreach($tasks as $tk): ?><option value="<?= $tk['id'] ?>" <?= ($edit['vehicleservicetaskid'] ?? '') == $tk['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tk['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Beschreibung</label>
                        <input type="text" name="description" value="<?= htmlspecialchars($edit['description'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <div class="form-row"><label>KM-Stand</label>
                        <input type="number" name="odometer" value="<?= $edit['odometer'] ?? '' ?>">
                    </div>
                    <div class="form-row"><label>Betrag (€)</label>
                        <input type="text" name="totalamount" value="<?= ($edit && isset($edit['totalamount'])) ? number_format((float)$edit['totalamount'], 2, ',', '') : '' ?>" placeholder="0,00">
                    </div>
                    <div class="form-row"><label>Rechnungs-Nr.</label>
                        <input type="text" name="invoicenumber" value="<?= htmlspecialchars($edit['invoicenumber'] ?? '') ?>">
                    </div>
                    <div class="form-row"><label>Nächster Termin</label>
                        <input type="date" name="nextserviceat" value="<?= $edit['nextserviceat'] ?? '' ?>">
                    </div>
                    <div class="form-row"><label>Nächster Stand</label>
                        <input type="number" name="nextserviceodometer" value="<?= $edit['nextserviceodometer'] ?? '' ?>">
                    </div>
                </div>
            </div>
            <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
                <label style="margin-top:8px;">Notiz</label>
                <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if($edit): ?>
                    <a href="/?route=module/vehicleservice&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Eintrag wirklich löschen?')">🗑 Löschen</a>
                    <a href="/?route=module/vehicleservice" class="btn-action cancel-bg">Abbrechen</a>
                <?php endif; ?>
                <button type="submit" name="save_vehicleservice" class="btn save" style="padding: 10px 40px; font-weight: bold;">Speichern</button>
            </div>
        </form>
    </div>
<?php };

$renderList = function() use ($list, $totalPages, $page, $f_q) { ?>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Fahrzeug</th>
                    <th>Typ / Aufgabe</th>
                    <th>Betrag</th>
                    <th>Nächster Service</th>
                    <th style="text-align:right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $vs): ?>
                <tr>
                    <td>
                        <div style="font-weight:bold;"><?= date('d.m.Y', strtotime($vs['datetime'])) ?></div>
                        <div style="font-size:11px; color:#64748b;"><?= date('H:i', strtotime($vs['datetime'])) ?></div>
                    </td>
                    <td><strong><?= htmlspecialchars($vs['licenseplate'] ?: '-') ?></strong><br><small><?= number_format($vs['odometer'], 0, ',', '.') ?> km</small></td>
                    <td>
                        <span class="badge"><?= htmlspecialchars($vs['typename'] ?: '-') ?></span><br>
                        <small><?= htmlspecialchars($vs['taskname'] ?: '-') ?></small>
                    </td>
                    <td><?= $vs['totalamount'] ? number_format($vs['totalamount'], 2, ',', '.') . ' €' : '-' ?></td>
                    <td>
                        <div style="font-size:12px;">
                            📅 <?= $vs['nextserviceat'] ? date('d.m.Y', strtotime($vs['nextserviceat'])) : '-' ?><br>
                            🚗 <?= $vs['nextserviceodometer'] ? number_format($vs['nextserviceodometer'], 0, ',', '.') . ' km' : '-' ?>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <a href="/?route=module/vehicleservice&edit=<?= $vs['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
            <?php $pUrl = "/?route=module/vehicleservice&q=".urlencode($f_q)."&p="; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= $pUrl . $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
<?php };

if ($isSearching) { $renderList(); echo "<br>"; $renderForm(); } else { $renderForm(); echo "<br>"; $renderList(); }
?>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 140px; min-width: 140px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
.btn.save { background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; display: inline-flex; align-items: center; box-sizing: border-box; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; font-weight: 600; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.pagination .page-link { padding: 6px 14px; border: 1px solid #ddd; text-decoration: none; border-radius: 4px; color: #333; }
.pagination .active { background: #10b981; color: #fff; border-color: #10b981; }
.filter-input { height: 38px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 10px; font-size: 14px; box-sizing: border-box; }
.filter-label { display: block; font-size: 11px; font-weight: bold; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
</style>