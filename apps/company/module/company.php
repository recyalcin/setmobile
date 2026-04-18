<?php
/**
 * module/company.php
 * Unternehmensverwaltung: Formular + Journal mit dynamischem Layout
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM company WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/company&msg=deleted";
}

if (isset($_POST['save_company'])) {
    $id                   = $_POST['id'] ?? null;
    $companytypeid        = $_POST['companytypeid'] ?: null;
    $companylegalformid   = $_POST['companylegalformid'] ?: null;
    $countryid            = $_POST['countryid'] ?: null;
    $name                 = $_POST['name'] ?? '';
    $street               = $_POST['street'] ?? '';
    $housenr              = $_POST['housenr'] ?? '';
    $pobox                = $_POST['pobox'] ?? '';
    $city                 = $_POST['city'] ?? '';
    $phone                = $_POST['phone'] ?? '';
    $email                = $_POST['email'] ?? '';
    $website              = $_POST['website'] ?? '';
    $vatid                = $_POST['vatid'] ?? '';
    $taxid                = $_POST['taxid'] ?? '';
    $bankname             = $_POST['bankname'] ?? '';
    $iban                 = $_POST['iban'] ?? '';
    $bic                  = $_POST['bic'] ?? '';
    $note                 = $_POST['note'] ?? '';

    $params = [
        $companytypeid, $companylegalformid, $name, $street, $housenr, 
        $pobox, $city, $countryid, $phone, $email, 
        $website, $vatid, $taxid, $bankname, $iban, $bic, $note
    ];

    if (!empty($id)) {
        $sql = "UPDATE company SET 
                companytypeid=?, companylegalformid=?, name=?, street=?, housenr=?, 
                pobox=?, city=?, countryid=?, phone=?, email=?, 
                website=?, vatid=?, taxid=?, bankname=?, iban=?, bic=?, note=?, 
                updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/company&msg=updated";
    } else {
        $sql = "INSERT INTO company 
                (companytypeid, companylegalformid, name, street, housenr, 
                 pobox, city, countryid, phone, email, 
                 website, vatid, taxid, bankname, iban, bic, note, createdat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/company&msg=created";
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
    $where[] = "(c.name LIKE ? OR c.city LIKE ? OR c.email LIKE ?)";
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%";
}
$whereSql = implode(" AND ", $where);

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM company c WHERE $whereSql");
$totalCount->execute($p_sql);
$totalCount = $totalCount->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. STAMMDATEN LADEN ---
$types      = $pdo->query("SELECT id, name FROM companytype ORDER BY name ASC")->fetchAll();
$legalforms = $pdo->query("SELECT id, name FROM companylegalform ORDER BY name ASC")->fetchAll();
$countries  = $pdo->query("SELECT id, name FROM country ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM company WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$listStmt = $pdo->prepare("SELECT c.*, ct.name as typename, clf.name as legalformname
                            FROM company c
                            LEFT JOIN companytype ct ON c.companytypeid = ct.id
                            LEFT JOIN companylegalform clf ON c.companylegalformid = clf.id
                            WHERE $whereSql ORDER BY c.name ASC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1;">
    <form method="get" action="/" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/company">
        <div style="flex: 1; min-width: 250px;">
            <label class="filter-label">🔍 Suche (Name, Stadt, Email)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" class="filter-input" placeholder="Suchen..." style="width: 100%; box-sizing: border-box; margin: 0;">
        </div>
        <button type="submit" class="btn save" style="height: 38px; padding: 0 20px;">Suchen</button>
        <a href="/company" class="btn reset-btn" style="height: 38px; display: inline-flex; align-items: center; background: #cbd5e1; color: #333; text-decoration: none; padding: 0 15px; border-radius: 4px; font-size: 14px; font-weight: 600;">Reset</a>
    </form>
</div>

<?php 
$renderForm = function() use ($edit, $types, $legalforms, $countries) { ?>
    <div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
        <h3 style="margin-bottom: 15px;">🏢 <?= $edit ? 'Unternehmen bearbeiten' : 'Neues Unternehmen anlegen' ?></h3>
        <form method="post" action="/company" class="form-container">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="form-row"><label>Firmenname</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-row"><label>Rechtsform</label>
                        <select name="companylegalformid">
                            <option value="">-- wählen --</option>
                            <?php foreach($legalforms as $lf): ?><option value="<?= $lf['id'] ?>" <?= ($edit['companylegalformid'] ?? '') == $lf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lf['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Typ</label>
                        <select name="companytypeid">
                            <option value="">-- wählen --</option>
                            <?php foreach($types as $t): ?><option value="<?= $t['id'] ?>" <?= ($edit['companytypeid'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row"><label>Straße / Nr.</label>
                        <div style="display:flex; gap:5px; flex-grow:1;">
                            <input type="text" name="street" value="<?= htmlspecialchars($edit['street'] ?? '') ?>" placeholder="Straße">
                            <input type="text" name="housenr" value="<?= htmlspecialchars($edit['housenr'] ?? '') ?>" placeholder="Nr." style="width:60px; flex-grow:0;">
                        </div>
                    </div>
                    <div class="form-row"><label>PLZ Ort / Postfach</label>
                        <div style="display:flex; gap:5px; flex-grow:1;">
                            <input type="text" name="city" value="<?= htmlspecialchars($edit['city'] ?? '') ?>" placeholder="Stadt">
                            <input type="text" name="pobox" value="<?= htmlspecialchars($edit['pobox'] ?? '') ?>" placeholder="Postfach" style="width:80px; flex-grow:0;">
                        </div>
                    </div>
                    <div class="form-row"><label>Land</label>
                        <select name="countryid">
                            <option value="">-- wählen --</option>
                            <?php foreach($countries as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['countryid'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="form-row"><label>Telefon</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
                    </div>
                    <div class="form-row"><label>E-Mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
                    </div>
                    <div class="form-row"><label>Website</label>
                        <input type="text" name="website" value="<?= htmlspecialchars($edit['website'] ?? '') ?>">
                    </div>
                    <div class="form-row"><label>USt-ID / Steuernr.</label>
                        <div style="display:flex; gap:5px; flex-grow:1;">
                            <input type="text" name="vatid" value="<?= htmlspecialchars($edit['vatid'] ?? '') ?>" placeholder="VAT ID">
                            <input type="text" name="taxid" value="<?= htmlspecialchars($edit['taxid'] ?? '') ?>" placeholder="Tax ID">
                        </div>
                    </div>
                    <div class="form-row"><label>Bankname</label>
                        <input type="text" name="bankname" value="<?= htmlspecialchars($edit['bankname'] ?? '') ?>">
                    </div>
                    <div class="form-row"><label>IBAN / BIC</label>
                        <div style="display:flex; gap:5px; flex-grow:1;">
                            <input type="text" name="iban" value="<?= htmlspecialchars($edit['iban'] ?? '') ?>" placeholder="IBAN">
                            <input type="text" name="bic" value="<?= htmlspecialchars($edit['bic'] ?? '') ?>" placeholder="BIC" style="width:100px; flex-grow:0;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
                <label style="margin-top:8px;">Notiz</label>
                <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if($edit): ?>
                    <a href="/company&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Unternehmen wirklich löschen?')">🗑 Löschen</a>
                    <a href="/company" class="btn-action cancel-bg">Abbrechen</a>
                <?php endif; ?>
                <button type="submit" name="save_company" class="btn save" style="padding: 10px 40px; font-weight: bold;">Speichern</button>
            </div>
        </form>
    </div>
<?php };

$renderList = function() use ($list, $totalPages, $page, $f_q) { ?>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Unternehmen</th>
                    <th>Typ / Form</th>
                    <th>Standort</th>
                    <th>Kontakt</th>
                    <th style="text-align:right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td>
                        <span class="badge"><?= htmlspecialchars($c['typename'] ?: '-') ?></span>
                        <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($c['legalformname'] ?: '-') ?></div>
                    </td>
                    <td><?= htmlspecialchars($c['city'] ?: '-') ?></td>
                    <td style="font-size:12px;">
                        <div><?= htmlspecialchars($c['phone'] ?: '-') ?></div>
                        <div style="color:#3b82f6;"><?= htmlspecialchars($c['email'] ?: '-') ?></div>
                    </td>
                    <td style="text-align:right;">
                        <a href="/company&edit=<?= $c['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
            <?php $pUrl = "/company&q=".urlencode($f_q)."&p="; ?>
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