<?php
/**
 * module/driveractivity.php - Verwaltung von Fahrer-Aktivitäten (mit Speichern+ & Update+)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

// --- 0. LOOKUP: Stammdaten ---
$people    = $pdo->query("SELECT id, lastname, firstname FROM person ORDER BY lastname ASC")->fetchAll();
$actTypes  = $pdo->query("SELECT id, name FROM driveractivitytype ORDER BY name ASC")->fetchAll();
$vehicles  = $pdo->query("SELECT id, licenseplate FROM vehicle ORDER BY licenseplate ASC")->fetchAll();

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// Pagination Parameter
$limit = 100;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$showForm = isset($_GET['edit']) || isset($_POST['duplicate_activity']);

// --- 1. LOGIK: AKTIONEN ---

// DUPLIZIEREN (Vorbereitung ohne Speichern)
if (isset($_POST['duplicate_activity'])) {
    $edit = $_POST;
    $edit['id'] = '';
    $showForm = true;
}

// SPEICHERN / SPEICHERN+ / UPDATE / UPDATE+
if (isset($_POST['save_activity']) || isset($_POST['update_plus']) || isset($_POST['save_plus'])) {
    $id = $_POST['id'] ?? null;
    
    $fields = [
        'driveractivitytypeid', 'personid', 'vehicleid', 'tripid', 
        'datetime', 'lat', 'lng', 'odometer', 'speed', 'heading', 'note'
    ];
    
    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $params[] = ($val !== '') ? $val : null;
    }

    if (!empty($id)) {
        // --- FALL: UPDATE ---
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE driveractivity SET $setClause WHERE id=?";
        $pdo->prepare($sql)->execute(array_merge($params, [$id]));
        
        if (isset($_POST['update_plus'])) {
            $edit = $_POST;
            $edit['id'] = '';
            $showForm = true;
            $message = "Datensatz aktualisiert. Duplikat geladen.";
        } else {
            $redirect = "/?route=module/driveractivity&p=$page&search=".urlencode($searchTerm)."&msg=updated";
        }
    } else {
        // --- FALL: INSERT ---
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO driveractivity ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);

        if (isset($_POST['save_plus'])) {
            $edit = $_POST;
            $edit['id'] = '';
            $showForm = true;
            $message = "Eintrag gespeichert. Neues Duplikat geladen.";
        } else {
            $redirect = "/?route=module/driveractivity&msg=created";
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM driveractivity WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/driveractivity&p=$page&search=".urlencode($searchTerm)."&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATENSATZ LADEN ---
if ($edit === null) {
    $isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
    if (isset($_GET['edit']) && !$isNew) {
        $stmt = $pdo->prepare("SELECT * FROM driveractivity WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
}

// --- 3. LISTE & PAGINATION LOGIK ---
$where = " WHERE 1=1";
$queryParams = [];
if (!empty($searchTerm)) {
    $where .= " AND (p.lastname ILIKE ? OR p.firstname ILIKE ? OR da.note ILIKE ? OR da.id::text ILIKE ?)";
    $like = "%$searchTerm%";
    $queryParams = [$like, $like, $like, $like];
}

$countSql = "SELECT COUNT(*) FROM driveractivity da LEFT JOIN person p ON da.personid = p.id $where";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $limit);

$sql = "SELECT da.*, p.lastname, p.firstname, dat.name as type_name, v.licenseplate 
        FROM driveractivity da
        LEFT JOIN person p ON da.personid = p.id
        LEFT JOIN driveractivitytype dat ON da.driveractivitytypeid = dat.id
        LEFT JOIN vehicle v ON da.vehicleid = v.id
        $where
        ORDER BY da.datetime DESC LIMIT $limit OFFSET $offset";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($queryParams);
$list = $stmtList->fetchAll();
?>

<style>
    .form-container { display: flex; flex-direction: column; gap: 8px; }
    .form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
    .form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
    .form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; }
    .neu-bg { background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; }
    .dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; margin-left: 10px; }
    .badge { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
    .page-btn { padding: 5px 12px; border: 1px solid #cbd5e1; background: white; border-radius: 4px; text-decoration: none; color: #475569; font-size: 13px; }
    .page-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
</style>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">🚩 Fahrer-Aktivität</h3>
        <a href="/?route=module/driveractivity&edit=new" class="btn-action neu-bg">+ Neue Aktivität</a>
    </div>
    
    <?php if ($showForm): ?>
    <form method="post" action="/?route=module/driveractivity&p=<?= $page ?>&search=<?= urlencode($searchTerm) ?>" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Person</label>
                    <select name="personid" required>
                        <option value="">-- wählen --</option>
                        <?php foreach($people as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['personid'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['lastname'].", ".$p['firstname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Typ</label>
                    <select name="driveractivitytypeid" required>
                        <option value="">-- wählen --</option>
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
                <div class="form-row"><label>Zeitpunkt</label><input type="datetime-local" name="datetime" step="1" value="<?= !empty($edit['datetime']) ? date('Y-m-d\TH:i:s', strtotime($edit['datetime'])) : date('Y-m-d') . 'T00:00:00' ?>"></div>
                <div class="form-row"><label>Trip ID</label><input type="number" name="tripid" value="<?= htmlspecialchars($edit['tripid'] ?? '') ?>"></div>
            </div>
            <div>
                <div class="form-row"><label>Lat / Lng</label>
                    <input type="text" name="lat" value="<?= htmlspecialchars($edit['lat'] ?? '') ?>" placeholder="Lat" style="margin-right:5px;">
                    <input type="text" name="lng" value="<?= htmlspecialchars($edit['lng'] ?? '') ?>" placeholder="Lng">
                </div>
                <div class="form-row"><label>KM / Speed</label>
                    <input type="text" name="odometer" value="<?= htmlspecialchars($edit['odometer'] ?? '') ?>" placeholder="KM Stand" style="margin-right:5px;" inputmode="numeric" pattern="\d*">
                    <input type="number" step="0.01" name="speed" value="<?= htmlspecialchars($edit['speed'] ?? '') ?>" placeholder="km/h">
                </div>
                <div class="form-row"><label>Heading</label><input type="number" name="heading" value="<?= htmlspecialchars($edit['heading'] ?? '') ?>" placeholder="0-360°"></div>
                <div class="form-row" style="align-items: flex-start;"><label style="margin-top:8px;">Notiz</label><textarea name="note" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/?route=module/driveractivity&delete=<?= $edit['id'] ?>&p=<?= $page ?>&search=<?= urlencode($searchTerm) ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/?route=module/driveractivity&p=<?= $page ?>&search=<?= urlencode($searchTerm) ?>" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?>
                    <button type="submit" name="duplicate_activity" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                    <button type="submit" name="update_plus" class="btn" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px; color:white; font-weight:bold; background:#10b981;">💾 Update+</button>
                <?php else: ?>
                    <button type="submit" name="save_plus" class="btn" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px; color:white; font-weight:bold; background:#10b981;">💾 Speichern+</button>
                <?php endif; ?>
                <button type="submit" name="save_activity" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;"><?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom: 25px;">
    <form method="get" action="/" style="display: flex; gap: 10px; width: 100%;">
        <input type="hidden" name="route" value="module/driveractivity">
        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Suche..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="cursor:pointer;">🔍 Suchen</button>
        <?php if(!empty($searchTerm)): ?><a href="/?route=module/driveractivity" class="btn-action cancel-bg">✖ Filter löschen</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Zeit (s)</th>
                <th>Person</th>
                <th>Typ</th>
                <th>Fahrzeug</th>
                <th>Odometer</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $da): ?>
            <tr>
                <td><small><?= (!empty($da['datetime'])) ? date('d.m. H:i:s', strtotime($da['datetime'])) : '-' ?></small></td>
                <td><strong><?= htmlspecialchars(($da['lastname'] ?? '') . ", " . ($da['firstname'] ?? '')) ?></strong></td>
                <td><span class="badge"><?= htmlspecialchars($da['type_name'] ?? '-') ?></span></td>
                <td><small><?= htmlspecialchars($da['licenseplate'] ?? '-') ?></small></td>
                <td><small><?= $da['odometer'] ? number_format($da['odometer'],0,',','.') . ' km' : '-' ?></small></td>
                <td style="text-align:right;">
                    <a href="/?route=module/driveractivity&edit=<?= $da['id'] ?>&p=<?= $page ?>&search=<?= urlencode($searchTerm) ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="/?route=module/driveractivity&p=1&search=<?= urlencode($searchTerm) ?>" class="page-btn">« First</a>
            <a href="/?route=module/driveractivity&p=<?= $page-1 ?>&search=<?= urlencode($searchTerm) ?>" class="page-btn">‹ Prev</a>
        <?php endif; ?>
        <?php 
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="/?route=module/driveractivity&p=<?= $i ?>&search=<?= urlencode($searchTerm) ?>" class="page-btn <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="/?route=module/driveractivity&p=<?= $page+1 ?>&search=<?= urlencode($searchTerm) ?>" class="page-btn">Next ›</a>
            <a href="/?route=module/driveractivity&p=<?= $totalPages ?>&search=<?= urlencode($searchTerm) ?>" class="page-btn">Last »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>