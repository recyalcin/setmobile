<?php
/**
 * module/driver.php
 * Vollständige Verwaltung der Fahrerdaten
 * Inkl. Fix für PHP 8.1+ (strtotime deprecation)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM driver WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/driver&msg=deleted";
}

// SPEICHERN (INSERT & UPDATE)
if (isset($_POST['save_driver'])) {
    $id                         = $_POST['id'] ?? null;
    $drivertypeid               = $_POST['drivertypeid'] ?: null;
    $personid                   = $_POST['personid'] ?: null;
    $licensecategoryid          = $_POST['licensecategoryid'] ?: null;
    $licenseissuedate           = $_POST['licenseissuedate'] ?: null;
    $licenseexpirydate          = $_POST['licenseexpirydate'] ?: null;
    $pendorsementissuedate      = $_POST['pendorsementissuedate'] ?: null;
    $pendorsementexpirydate     = $_POST['pendorsementexpirydate'] ?: null;
    $note                       = $_POST['note'] ?? '';

    $params = [
        $drivertypeid, 
        $personid, 
        $licensecategoryid, 
        $licenseissuedate, 
        $licenseexpirydate, 
        $pendorsementissuedate, 
        $pendorsementexpirydate, 
        $note
    ];

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE driver SET 
                drivertypeid=?, personid=?, licensecategoryid=?, licenseissuedate=?, 
                licenseexpirydate=?, pendorsementissuedate=?, pendorsementexpirydate=?, 
                note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/driver&msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO driver (
                drivertypeid, personid, licensecategoryid, licenseissuedate, 
                licenseexpirydate, pendorsementissuedate, pendorsementexpirydate, 
                note, createdat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/driver&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. FILTER & PAGINATION LOGIK ---
$limit  = 25; 
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$f_q = $_GET['q'] ?? '';
$where = ["1=1"];
$p_sql = [];

if ($f_q) { 
    $where[] = "(p.lastname LIKE ? OR p.firstname LIKE ? OR d.note LIKE ?)"; 
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; 
}

$whereSql = implode(" AND ", $where);

// Gesamtanzahl für Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM driver d LEFT JOIN person p ON d.personid = p.id WHERE $whereSql");
$countStmt->execute($p_sql);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. STAMMDATEN LADEN ---
$driverTypes = $pdo->query("SELECT id, name FROM drivertype ORDER BY name ASC")->fetchAll();
$licenseCats = $pdo->query("SELECT id, name FROM licensecategory ORDER BY name ASC")->fetchAll();
$persons     = $pdo->query("SELECT id, lastname, firstname FROM person ORDER BY lastname ASC")->fetchAll();

// Edit Modus prüfen
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM driver WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Journal Liste laden
$listStmt = $pdo->prepare("SELECT d.*, p.firstname, p.lastname, dt.name as typename, lc.name as catname 
                           FROM driver d
                           LEFT JOIN person p ON d.personid = p.id
                           LEFT JOIN drivertype dt ON d.drivertypeid = dt.id
                           LEFT JOIN licensecategory lc ON d.licensecategoryid = lc.id
                           WHERE $whereSql 
                           ORDER BY p.lastname ASC, p.firstname ASC 
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🪪 <?= $edit ? 'Fahrer bearbeiten' : 'Neuen Fahrer erfassen' ?></h3>
    </div>
    
    <form method="post" action="/?route=module/driver" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row">
                    <label>Person</label>
                    <select name="personid" required>
                        <option value="">-- wählen --</option>
                        <?php foreach($persons as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['personid'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['lastname'].", ".$p['firstname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Fahrertyp</label>
                    <select name="drivertypeid">
                        <option value="">-- wählen --</option>
                        <?php foreach($driverTypes as $dt): ?>
                            <option value="<?= $dt['id'] ?>" <?= ($edit['drivertypeid'] ?? '') == $dt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dt['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>FS-Klasse</label>
                    <select name="licensecategoryid">
                        <option value="">-- wählen --</option>
                        <?php foreach($licenseCats as $lc): ?>
                            <option value="<?= $lc['id'] ?>" <?= ($edit['licensecategoryid'] ?? '') == $lc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lc['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <div class="form-row"><label>FS Erteilung</label><input type="date" name="licenseissuedate" value="<?= $edit['licenseissuedate'] ?? '' ?>"></div>
                <div class="form-row"><label>FS Ablauf</label><input type="date" name="licenseexpirydate" value="<?= $edit['licenseexpirydate'] ?? '' ?>"></div>
                <div class="form-row"><label>P-Schein Erteilung</label><input type="date" name="pendorsementissuedate" value="<?= $edit['pendorsementissuedate'] ?? '' ?>"></div>
                <div class="form-row"><label>P-Schein Ablauf</label><input type="date" name="pendorsementexpirydate" value="<?= $edit['pendorsementexpirydate'] ?? '' ?>"></div>
            </div>
        </div>

        <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
            <label style="margin-top:8px;">Notiz</label>
            <textarea name="note" rows="2" placeholder="Interne Bemerkungen..."><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="/?route=module/driver&delete=<?= $edit['id'] ?>" 
                   class="btn-action delete-bg"
                   onclick="return confirm('Diesen Fahrerdatensatz wirklich löschen?')">
                     🗑 Löschen
                </a>
                <a href="/?route=module/driver" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            
            <button type="submit" name="save_driver" class="btn save" style="padding: 10px 40px; font-weight: bold; margin: 0;">
                <?= $edit ? 'Aktualisieren' : 'Speichern' ?>
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom: 20px; background: #f1f5f9; border: 1px solid #e2e8f0;">
    <form method="get" action="/" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/driver">
        <div style="flex: 2; min-width: 250px;"><label class="filter-label">🔍 Suche</label><input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Name oder Notiz..." class="filter-input"></div>
        <div style="display: flex; gap: 5px;">
            <button type="submit" class="btn save" style="height: 38px;">Suchen</button>
            <a href="/?route=module/driver" class="btn reset-btn" style="height: 38px; display:flex; align-items:center; text-decoration:none; background:#cbd5e1; color:#333; padding: 0 15px; border-radius:4px;">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fahrer / Person</th>
                <th>Typ / Klasse</th>
                <th>FS Ablauf</th>
                <th>P-Schein Ablauf</th>
                <th style="text-align:right; width: 80px;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $d): 
                // FIX: Deprecation Warnung verhindern durch Null-Check (?? '')
                $expiryWarning = '';
                $licenseDateStr = $d['licenseexpirydate'] ?? '';
                if (!empty($licenseDateStr)) {
                    if (strtotime($licenseDateStr) < strtotime('+30 days')) {
                        $expiryWarning = 'color:red; font-weight:bold;';
                    }
                }
            ?>
            <tr>
                <td>
                    <div style="font-weight: 600; color: #1e293b;">
                        <?= htmlspecialchars(($d['lastname'] ?? '').", ".($d['firstname'] ?? '')) ?>
                    </div>
                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($d['note'] ?? '') ?></div>
                </td>
                <td>
                    <span class="badge"><?= htmlspecialchars($d['typename'] ?? 'Allgemein') ?></span>
                    <span style="color:#cbd5e1; margin: 0 4px;">|</span> <?= htmlspecialchars($d['catname'] ?? '-') ?>
                </td>
                <td style="<?= $expiryWarning ?>">
                    <?= !empty($d['licenseexpirydate']) ? date('d.m.Y', strtotime($d['licenseexpirydate'])) : '<span style="color:#cbd5e1;">-</span>' ?>
                </td>
                <td>
                    <?= !empty($d['pendorsementexpirydate']) ? date('d.m.Y', strtotime($d['pendorsementexpirydate'])) : '<span style="color:#cbd5e1;">-</span>' ?>
                </td>
                <td style="text-align:right;">
                    <a href="/?route=module/driver&edit=<?= $d['id'] ?>" class="action-link edit-link" title="Bearbeiten" style="font-size: 18px; text-decoration: none; margin-right: 10px;">✎</a>
                    <a href="/?route=module/driver&delete=<?= $d['id'] ?>" 
                       class="action-link delete-link" 
                       title="Löschen" 
                       style="font-size: 18px; text-decoration: none; color: #dc2626;"
                       onclick="return confirm('Fahrer wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
        <?php $pUrl = "/?route=module/driver&q=".urlencode($f_q)."&p="; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="<?= $pUrl . $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 130px; min-width: 130px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; }
.btn.save { background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn.save:hover { background: #059669; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.filter-label { display: block; font-size: 11px; margin-bottom: 4px; color: #64748b; text-transform: uppercase; font-weight: bold; }
.filter-input { width: 100%; height: 38px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-size: 10px; font-weight: bold; }
.pagination .page-link { padding: 6px 14px; border: 1px solid #ddd; text-decoration: none; border-radius: 4px; color: #333; }
.pagination .active { background: #10b981; color: #fff; border-color: #10b981; }
</style>