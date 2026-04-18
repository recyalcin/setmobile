<?php
/**
 * module/bankaccount.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// Sichtbarkeits-Logik für das Formular
$showForm = isset($_GET['edit']) || isset($_POST['duplicate_bankaccount']);

// --- 1. LOGIK: AKTIONEN ---

// SPEICHERN / UPDATE
if (isset($_POST['save_bankaccount'])) {
    $id = $_POST['id'] ?? null;
    
    $fields = [
        'bankaccounttypeid', 'name', 'bankname', 'iban', 'bic', 
        'personid', 'currencyid', 'isactive', 'note'
    ];

    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        if ($f === 'isactive') {
            $params[] = ($val === '1') ? 1 : 0;
        } else {
            $params[] = ($val !== '') ? $val : null;
        }
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE bankaccount SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/bankaccount&msg=updated";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO bankaccount ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/bankaccount&msg=created";
    }
}

// DUPLIZIEREN
if (isset($_POST['duplicate_bankaccount'])) {
    $edit = $_POST;
    $edit['id'] = '';
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM bankaccount WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/bankaccount&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$accountTypes = $pdo->query("SELECT id, name FROM bankaccounttype ORDER BY name ASC")->fetchAll();
$currencies   = $pdo->query("SELECT id, name FROM currency ORDER BY name ASC")->fetchAll();
$persons      = $pdo->query("SELECT id, lastname, firstname FROM person ORDER BY lastname ASC, firstname ASC")->fetchAll();

if ($edit === null) {
    $isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
    if (isset($_GET['edit']) && !$isNew) {
        $stmt = $pdo->prepare("SELECT * FROM bankaccount WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
} else { $isNew = false; }

// --- 3. LISTE ---
$sql = "SELECT b.*, bt.name as type_name, c.name as currency_name, p.lastname, p.firstname 
        FROM bankaccount b
        LEFT JOIN bankaccounttype bt ON b.bankaccounttypeid = bt.id
        LEFT JOIN currency c ON b.currencyid = c.id
        LEFT JOIN person p ON b.personid = p.id
        WHERE 1=1";

$queryParams = [];
if (!empty($searchTerm)) {
    $sql .= " AND (b.name LIKE ? OR b.bankname LIKE ? OR b.iban LIKE ? OR p.lastname LIKE ? OR b.id LIKE ?)";
    $like = "%$searchTerm%";
    $queryParams = [$like, $like, $like, $like, $like];
}

$sql .= " ORDER BY b.isactive DESC, b.name ASC LIMIT 100";
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
    .badge { padding: 3px 8px; background:#f1f5f9; color:#475569; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .badge-active { background:#dcfce7; color:#166534; }
    .edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; margin-left: 10px; }
</style>

<?php
// BLOCK 1: FORMULAR
ob_start(); ?>
<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">🏦 Bank Account</h3>
        <a href="/?route=module/bankaccount&edit=new" class="btn-action neu-bg">+ Neues Konto</a>
    </div>
    
    <?php if ($showForm): ?>
    <form method="post" action="/?route=module/bankaccount" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        <div style="display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Bezeichnung</label><input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Hauptkonto" required></div>
                <div class="form-row"><label>Bankname</label><input type="text" name="bankname" value="<?= htmlspecialchars($edit['bankname'] ?? '') ?>"></div>
                <div class="form-row"><label>IBAN</label><input type="text" name="iban" value="<?= htmlspecialchars($edit['iban'] ?? '') ?>"></div>
                <div class="form-row"><label>BIC</label><input type="text" name="bic" value="<?= htmlspecialchars($edit['bic'] ?? '') ?>"></div>
                <div class="form-row"><label>Inhaber (Pers.)</label>
                    <select name="personid">
                        <option value="">-- wählen --</option>
                        <?php foreach($persons as $p): 
                            $sel = (($edit['personid'] ?? '') == $p['id'] || ($isNew && $p['lastname'] == 'Sulak' && $p['firstname'] == 'Süleyman')) ? 'selected' : ''; 
                        ?><option value="<?= $p['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p['lastname'].", ".$p['firstname']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Typ / Währung</label>
                    <select name="bankaccounttypeid" style="width:48%; margin-right:2%;"><option value="">-- Typ --</option>
                        <?php foreach($accountTypes as $at): $sel = ($edit['bankaccounttypeid'] ?? '') == $at['id'] ? 'selected' : ''; ?><option value="<?= $at['id'] ?>" <?= $sel ?>><?= htmlspecialchars($at['name']) ?></option><?php endforeach; ?>
                    </select>
                    <select name="currencyid" style="width:48%;"><option value="">-- Währung --</option>
                        <?php foreach($currencies as $c): $sel = ($edit['currencyid'] ?? '') == $c['id'] ? 'selected' : ''; ?><option value="<?= $c['id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Status</label>
                    <select name="isactive">
                        <option value="1" <?= ($edit['isactive'] ?? '1') == '1' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= ($edit['isactive'] ?? '') == '0' ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row" style="align-items: flex-start;"><label style="margin-top:8px;">Notiz</label><textarea name="note" rows="8"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/?route=module/bankaccount&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/?route=module/bankaccount" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_bankaccount" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_bankaccount" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;"><?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php $htmlForm = ob_get_clean();

// BLOCK 2: SUCHBEREICH
$htmlSearch = '
<div class="card" style="margin-bottom: 25px;">
    <form method="get" action="/" style="display: flex; gap: 10px; width: 100%;">
        <input type="hidden" name="route" value="module/bankaccount">
        <input type="text" name="search" value="'.htmlspecialchars($searchTerm).'" placeholder="Suche in Name, Bank, IBAN, Person oder ID..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="cursor:pointer;">🔍 Suchen</button>
        '.(!empty($searchTerm) ? '<a href="/?route=module/bankaccount" class="btn-action cancel-bg">✖ Filter löschen</a>' : '').'
    </form>
</div>';

// BLOCK 3: LISTE
ob_start(); ?>
<div class="card">
    <table class="data-table">
        <thead><tr><th>ID</th><th>Konto / Bank</th><th>IBAN / BIC</th><th>Inhaber (Person)</th><th>Typ</th><th>Status</th><th style="text-align:right;">Aktionen</th></tr></thead>
        <tbody>
            <?php foreach ($list as $b): ?>
            <tr>
                <td><small>#<?= $b['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($b['name']) ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($b['bankname'] ?? '-') ?></small></td>
                <td><small><?= htmlspecialchars($b['iban'] ?? '-') ?></small><br><small style="color:#64748b;"><?= htmlspecialchars($b['bic'] ?? '-') ?></small></td>
                <td><small><?= $b['personid'] ? htmlspecialchars($b['lastname'].", ".$b['firstname']) : '-' ?></small></td>
                <td><span class="badge"><?= htmlspecialchars($b['type_name'] ?? '-') ?></span><br><small><?= htmlspecialchars($b['currency_name'] ?? '') ?></small></td>
                <td><span class="badge <?= $b['isactive'] ? 'badge-active' : '' ?>"><?= $b['isactive'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
                <td style="text-align:right;">
                    <a href="/?route=module/bankaccount&edit=<?= $b['id'] ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $htmlList = ob_get_clean();

echo $htmlForm;
echo $htmlSearch;
echo $htmlList;
?>