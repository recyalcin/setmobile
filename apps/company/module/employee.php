<?php
/**
 * module/employee.php
 * Mitarbeiterverwaltung: Formular + Journal mit dynamischem Layout & fixiertem Suchdesign
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM employee WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/employee&msg=deleted";
}

if (isset($_POST['save_employee'])) {
    $id                    = $_POST['id'] ?? null;
    $employeetypeid        = $_POST['employeetypeid'] ?: null;
    $personid              = $_POST['personid'] ?: null;
    $employeenumber        = $_POST['employeenumber'] ?? '';
    $jobtitleid            = $_POST['jobtitleid'] ?: null;
    $departmentid          = $_POST['departmentid'] ?: null;
    $workinghoursschichtid = $_POST['workinghoursschichtid'] ?: null;
    $businessemail         = $_POST['businessemail'] ?? '';
    $businessphone         = $_POST['businessphone'] ?? '';
    $hiredate              = $_POST['hiredate'] ?: null;
    $terminationdate       = $_POST['terminationdate'] ?: null;
    $maxweeklyhours        = $_POST['maxweeklyhours'] !== '' ? str_replace(',', '.', $_POST['maxweeklyhours']) : null;
    $note                  = $_POST['note'] ?? '';

    $params = [
        $employeetypeid, $personid, $employeenumber, $jobtitleid, $departmentid, 
        $workinghoursschichtid, $businessemail, $businessphone, $hiredate, 
        $terminationdate, $maxweeklyhours, $note
    ];

    if (!empty($id)) {
        $sql = "UPDATE employee SET 
                employeetypeid=?, personid=?, employeenumber=?, jobtitleid=?, departmentid=?, 
                workinghoursschichtid=?, businessemail=?, businessphone=?, hiredate=?, 
                terminationdate=?, maxweeklyhours=?, note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/employee&msg=updated";
    } else {
        $sql = "INSERT INTO employee 
                (employeetypeid, personid, employeenumber, jobtitleid, departmentid, 
                 workinghoursschichtid, businessemail, businessphone, hiredate, 
                 terminationdate, maxweeklyhours, note, createdat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/employee&msg=created";
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
    $where[] = "(e.employeenumber LIKE ? OR e.businessemail LIKE ? OR p.lastname LIKE ? OR p.firstname LIKE ?)";
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%";
}
$whereSql = implode(" AND ", $where);

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM employee e LEFT JOIN person p ON e.personid = p.id WHERE $whereSql");
$totalCount->execute($p_sql);
$totalCount = $totalCount->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. STAMMDATEN LADEN ---
$people       = $pdo->query("SELECT id, lastname, firstname FROM person ORDER BY lastname ASC")->fetchAll();
$empTypes     = $pdo->query("SELECT id, name FROM employeetype ORDER BY name ASC")->fetchAll();
$jobTitles    = $pdo->query("SELECT id, name FROM jobtitle ORDER BY name ASC")->fetchAll();
$departments  = $pdo->query("SELECT id, name FROM department ORDER BY name ASC")->fetchAll();
$schichtModel = $pdo->query("SELECT id, name FROM workinghoursschicht ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$listStmt = $pdo->prepare("SELECT e.*, p.lastname, p.firstname, d.name as dept_name, jt.name as job_name 
                            FROM employee e
                            LEFT JOIN person p ON e.personid = p.id
                            LEFT JOIN department d ON e.departmentid = d.id
                            LEFT JOIN jobtitle jt ON e.jobtitleid = jt.id
                            WHERE $whereSql ORDER BY p.lastname ASC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1;">
    <form method="get" action="/" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/employee">
        
        <div style="flex: 1; min-width: 250px;">
            <label class="filter-label">🔍 Suche (Name, Pers-Nr, Email)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" class="filter-input" placeholder="Suchen..." style="width: 100%; box-sizing: border-box; margin: 0;">
        </div>
        
        <button type="submit" class="btn save" style="height: 38px; padding: 0 20px; margin: 0; display: inline-flex; align-items: center; justify-content: center; border: none; font-size: 14px; font-weight: 600; cursor: pointer; box-sizing: border-box;">Suchen</button>
        
        <a href="/employee" class="btn reset-btn" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; background: #cbd5e1; color: #333; text-decoration: none; padding: 0 15px; border-radius: 4px; font-size: 14px; font-weight: 600; box-sizing: border-box; margin: 0;">Reset</a>
    </form>
</div>

<?php 
// Formular-Renderer
$renderForm = function() use ($edit, $people, $empTypes, $departments, $jobTitles, $schichtModel) { ?>
    <div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
        <h3 style="margin-bottom: 15px;">👤 <?= $edit ? 'Mitarbeiter bearbeiten' : 'Neuen Mitarbeiter anlegen' ?></h3>
        <form method="post" action="/employee" class="form-container">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="form-row"><label>Person</label>
                        <select name="personid" required>
                            <option value="">-- wählen --</option>
                            <?php foreach($people as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($edit['personid'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['lastname'].", ".$p['firstname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Pers-Nr.</label><input type="text" name="employeenumber" value="<?= htmlspecialchars($edit['employeenumber'] ?? '') ?>"></div>
                    <div class="form-row"><label>Anstellung</label>
                        <select name="employeetypeid">
                            <option value="">-- wählen --</option>
                            <?php foreach($empTypes as $et): ?><option value="<?= $et['id'] ?>" <?= ($edit['employeetypeid'] ?? '') == $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Abteilung</label>
                        <select name="departmentid">
                            <option value="">-- wählen --</option>
                            <?php foreach($departments as $d): ?><option value="<?= $d['id'] ?>" <?= ($edit['departmentid'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Titel</label>
                        <select name="jobtitleid">
                            <option value="">-- wählen --</option>
                            <?php foreach($jobTitles as $jt): ?><option value="<?= $jt['id'] ?>" <?= ($edit['jobtitleid'] ?? '') == $jt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jt['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Schicht</label>
                        <select name="workinghoursschichtid">
                            <option value="">-- wählen --</option>
                            <?php foreach($schichtModel as $sm): ?><option value="<?= $sm['id'] ?>" <?= ($edit['workinghoursschichtid'] ?? '') == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="form-row"><label>E-Mail</label><input type="email" name="businessemail" value="<?= htmlspecialchars($edit['businessemail'] ?? '') ?>"></div>
                    <div class="form-row"><label>Telefon</label><input type="text" name="businessphone" value="<?= htmlspecialchars($edit['businessphone'] ?? '') ?>"></div>
                    <div class="form-row"><label>Eintritt</label><input type="date" name="hiredate" value="<?= $edit['hiredate'] ?? '' ?>"></div>
                    <div class="form-row"><label>Austritt</label><input type="date" name="terminationdate" value="<?= $edit['terminationdate'] ?? '' ?>"></div>
                    <div class="form-row"><label>Std/Woche</label>
                        <input type="text" name="maxweeklyhours" value="<?= ($edit && isset($edit['maxweeklyhours'])) ? number_format((float)$edit['maxweeklyhours'], 2, ',', '') : '' ?>" placeholder="0,00">
                    </div>
                </div>
            </div>
            <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
                <label style="margin-top:8px;">Notiz</label>
                <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if($edit): ?>
                    <a href="/employee&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Mitarbeiter wirklich löschen?')">🗑 Löschen</a>
                    <a href="/employee" class="btn-action cancel-bg">Abbrechen</a>
                <?php endif; ?>
                <button type="submit" name="save_employee" class="btn save" style="padding: 10px 40px; font-weight: bold;">Speichern</button>
            </div>
        </form>
    </div>
<?php };

$renderList = function() use ($list, $totalPages, $page, $f_q) { ?>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Personal-Nr.</th>
                    <th>Abteilung / Position</th>
                    <th>Kontakt</th>
                    <th style="text-align:right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $e): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['lastname'].", ".$e['firstname']) ?></strong></td>
                    <td><span class="badge"><?= htmlspecialchars($e['employeenumber'] ?: '-') ?></span></td>
                    <td>
                        <div style="font-size:13px;"><?= htmlspecialchars($e['dept_name'] ?: '-') ?></div>
                        <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($e['job_name'] ?: '-') ?></div>
                    </td>
                    <td style="font-size:12px;">
                        <div>📧 <?= htmlspecialchars($e['businessemail'] ?: '-') ?></div>
                        <div>📞 <?= htmlspecialchars($e['businessphone'] ?: '-') ?></div>
                    </td>
                    <td style="text-align:right;">
                        <a href="/employee&edit=<?= $e['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
            <?php $pUrl = "/employee&q=".urlencode($f_q)."&p="; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= $pUrl . $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
<?php }; ?>

<?php 
if ($isSearching) {
    $renderList(); echo "<br>"; $renderForm();
} else {
    $renderForm(); echo "<br>"; $renderList();
}
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