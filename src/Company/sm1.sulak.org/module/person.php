<?php
/**
 * module/person.php - Dynamisches Layout mit konditionaler Formular-Anzeige
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// --- 1. LOGIK: AKTIONEN ---

// DUPLIZIEREN
if (isset($_POST['duplicate_person'])) {
    $edit = $_POST;
    $edit['id'] = '';
    if (!empty($edit['dateofbirth'])) $edit['dateofbirth'] = date('d.m.Y', strtotime($edit['dateofbirth']));
}

// SPEICHERN / UPDATE
if (isset($_POST['save_person'])) {
    $id = $_POST['id'] ?? null;
    $fname = $_POST['firstname'] ?? '';
    $lname = $_POST['lastname'] ?? '';
    $nameParam = urlencode($fname . " " . $lname);
    
    $fields = [
        'persontypeid', 'firstname', 'middlename', 'lastname', 'email', 'phone', 
        'street', 'housenr', 'pobox', 'city', 'country', 'taxid', 
        'bankname', 'iban', 'bic', 'dateofbirth', 'birthcity', 
        'birthcountry', 'nationality', 'gender', 'note'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        if ($f === 'dateofbirth') {
            if (!empty($val)) {
                $timestamp = strtotime($val);
                $params[] = $timestamp ? date('Y-m-d', $timestamp) : null;
            } else { $params[] = null; }
        } else {
            $params[] = ($val !== '') ? $val : null;
        }
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE person SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        // Redirect OHNE edit=new (Formular schließen)
        $redirect = "/?route=module/person&msg=updated&name=$nameParam";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO person ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        // Redirect OHNE edit=new (Formular schließen)
        $redirect = "/?route=module/person&msg=created&name=$nameParam";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmtName = $pdo->prepare("SELECT firstname, lastname FROM person WHERE id = ?");
    $stmtName->execute([$_GET['delete']]);
    $pInfo = $stmtName->fetch();
    $nameParam = $pInfo ? urlencode($pInfo['firstname'] . " " . $pInfo['lastname']) : 'Unbekannt';

    $stmt = $pdo->prepare("DELETE FROM person WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    // Redirect OHNE edit=new
    $redirect = "/?route=module/person&msg=deleted&name=$nameParam";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN & STATUS ---
$personTypes = $pdo->query("SELECT id, name FROM persontype ORDER BY name ASC")->fetchAll();

if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    $pName = $_GET['name'] ?? 'Person';
    $statusText = '';
    if ($m === 'created') $statusText = 'erfolgreich angelegt!';
    if ($m === 'updated') $statusText = 'erfolgreich aktualisiert!';
    if ($m === 'deleted') $statusText = 'wurde gelöscht!';
    if ($statusText) {
        $message = "<div class='alert info-box'>✨ <strong>" . htmlspecialchars($pName) . "</strong> $statusText</div>";
    }
}

// Sichtbarkeits-Logik
$isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
$isEdit = (isset($_GET['edit']) && !$isNew);
$isDuplicate = isset($_POST['duplicate_person']);
$showForm = ($isNew || $isEdit || $isDuplicate);

if ($edit === null && $isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM person WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 3. LISTE ---
$sql = "SELECT p.*, pt.name as type_name FROM person p LEFT JOIN persontype pt ON p.persontypeid = pt.id WHERE 1=1";
$queryParams = [];
if (!empty($searchTerm)) {
    $sql .= " AND (p.lastname LIKE ? OR p.firstname LIKE ? OR p.email LIKE ? OR p.city LIKE ? OR p.id LIKE ?)";
    $like = "%$searchTerm%";
    $queryParams = [$like, $like, $like, $like, $like];
}
$sql .= " ORDER BY p.lastname ASC, p.firstname ASC LIMIT 100";
$stmtList = $pdo->prepare($sql);
$stmtList->execute($queryParams);
$list = $stmtList->fetchAll();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .info-box { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-size: 14px; }
    .form-container { display: flex; flex-direction: column; gap: 8px; }
    .form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
    .form-row label { width: 130px; font-weight: bold; font-size: 12px; color: #475569; }
    .form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; border: 1px solid transparent; cursor: pointer; }
    .neu-bg { background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; }
    .dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; }
    .search-card { margin-bottom: 25px; padding: 15px 20px; }
</style>

<div class="card search-card">
    <form method="get" action="/" style="display: flex; gap: 10px; width: 100%;">
        <input type="hidden" name="route" value="module/person">
        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Name, E-Mail, Ort suchen..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="padding: 8px 25px;">🔍 Suchen</button>
        <?php if(!empty($searchTerm)): ?>
            <a href="/?route=module/person" class="btn-action cancel-bg">✖ Filter löschen</a>
        <?php endif; ?>
    </form>
</div>

<?= $message ?>

<?php 
// --- FORMULAR BLOCK ---
ob_start(); ?>
<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">👤 Personen-Stammdaten</h3>
        <a href="/?route=module/person&edit=new" class="btn-action neu-bg">+ Neue Person</a>
    </div>

    <?php if ($showForm): ?>
    <form method="post" action="/?route=module/person" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            <div>
                <h4 style="margin-top:0; color:#1e40af; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Allgemein</h4>
                <div class="form-row"><label>Typ / Geschlecht</label>
                    <select name="persontypeid" style="width:48%; margin-right:2%;">
                        <option value="0">-- Typ --</option>
                        <?php foreach($personTypes as $pt): ?>
                            <option value="<?= $pt['id'] ?>" <?= ($edit['persontypeid'] ?? '') == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="gender" value="<?= htmlspecialchars($edit['gender'] ?? '') ?>" placeholder="Gender" style="width:48%;">
                </div>
                <div class="form-row"><label>Vorname / Mid.</label>
                    <input type="text" name="firstname" value="<?= htmlspecialchars($edit['firstname'] ?? '') ?>" placeholder="Vorname" required style="width:48%; margin-right:2%;">
                    <input type="text" name="middlename" value="<?= htmlspecialchars($edit['middlename'] ?? '') ?>" placeholder="Mittelname" style="width:48%;">
                </div>
                <div class="form-row"><label>Nachname</label>
                    <input type="text" name="lastname" value="<?= htmlspecialchars($edit['lastname'] ?? '') ?>" placeholder="Nachname" required>
                </div>
                <div class="form-row"><label>E-Mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>" placeholder="email@beispiel.de">
                </div>
                <div class="form-row"><label>Telefon</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
                </div>
                
                <h4 style="margin-top:20px; color:#1e40af; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Anschrift</h4>
                <div class="form-row"><label>Straße / Nr.</label>
                    <input type="text" name="street" value="<?= htmlspecialchars($edit['street'] ?? '') ?>" style="width:68%; margin-right:2%;">
                    <input type="text" name="housenr" value="<?= htmlspecialchars($edit['housenr'] ?? '') ?>" style="width:28%;">
                </div>
                <div class="form-row"><label>PLZ / Ort</label>
                    <input type="text" name="pobox" value="<?= htmlspecialchars($edit['pobox'] ?? '') ?>" placeholder="PLZ" style="width:28%; margin-right:2%;">
                    <input type="text" name="city" value="<?= htmlspecialchars($edit['city'] ?? '') ?>" placeholder="Stadt" style="width:68%;">
                </div>
                <div class="form-row"><label>Land</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($edit['country'] ?? 'Deutschland') ?>">
                </div>
            </div>

            <div>
                <h4 style="margin-top:0; color:#1e40af; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Zusatz & Finanzen</h4>
                <div class="form-row"><label>Steuernr.</label>
                    <input type="text" name="taxid" value="<?= htmlspecialchars($edit['taxid'] ?? '') ?>">
                </div>
                <div class="form-row"><label>Bank</label>
                    <input type="text" name="bankname" value="<?= htmlspecialchars($edit['bankname'] ?? '') ?>">
                </div>
                <div class="form-row"><label>IBAN</label>
                    <input type="text" name="iban" value="<?= htmlspecialchars($edit['iban'] ?? '') ?>">
                </div>
                <div class="form-row"><label>BIC</label>
                    <input type="text" name="bic" value="<?= htmlspecialchars($edit['bic'] ?? '') ?>">
                </div>
                
                <h4 style="margin-top:20px; color:#1e40af; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Geburt / Herkunft</h4>
                <div class="form-row"><label>Geburtsdatum</label>
                    <input type="text" class="date-picker" name="dateofbirth" value="<?= (!empty($edit['dateofbirth']) ? date('d.m.Y', strtotime($edit['dateofbirth'])) : '') ?>">
                </div>
                <div class="form-row"><label>Geburtsort</label>
                    <input type="text" name="birthcity" value="<?= htmlspecialchars($edit['birthcity'] ?? '') ?>" style="width:48%; margin-right:2%;">
                    <input type="text" name="birthcountry" value="<?= htmlspecialchars($edit['birthcountry'] ?? '') ?>" style="width:48%;">
                </div>
                <div class="form-row"><label>Nationalität</label>
                    <input type="text" name="nationality" value="<?= htmlspecialchars($edit['nationality'] ?? '') ?>">
                </div>

                <div class="form-row" style="align-items: flex-start; margin-top:10px;"><label style="margin-top:8px;">Notiz</label>
                    <textarea name="note" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/?route=module/person&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/?route=module/person" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_person" class="btn-action dupli-bg">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_person" class="btn-action" style="padding:10px 40px; color:white; font-weight:bold; background:#3b82f6;">
                    <?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?>
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php $formCard = ob_get_clean(); ?>

<?php 
// --- LISTEN BLOCK ---
ob_start(); ?>
<div class="card" style="margin-bottom: 25px;">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Name</th>
                <th>Typ</th>
                <th>Kontakt / E-Mail</th>
                <th>Ort</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $p): ?>
            <tr>
                <td><small style="color: #94a3b8;">#<?= $p['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($p['lastname']) ?></strong>, <?= htmlspecialchars($p['firstname']) ?></td>
                <td><small><?= htmlspecialchars($p['type_name'] ?? '-') ?></small></td>
                <td><small><?= htmlspecialchars($p['email'] ?? '-') ?></small><br><small style="color:#64748b;"><?= htmlspecialchars($p['phone'] ?? '') ?></small></td>
                <td><small><?= htmlspecialchars($p['city'] ?? '-') ?></small></td>
                <td style="text-align:right;"><a href="/?route=module/person&edit=<?= $p['id'] ?>" class="edit-link">✎</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px; color: #94a3b8;">Keine Datensätze gefunden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php $listCard = ob_get_clean(); ?>

<?php 
// DYNAMISCHE AUSGABE
if (!empty($searchTerm)) {
    echo $listCard;
    echo $formCard;
} else {
    echo $formCard;
    echo $listCard;
}
?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".date-picker", { dateFormat: "d.m.Y", locale: "de", allowInput: true });
});
</script>