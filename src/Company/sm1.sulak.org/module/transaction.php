<?php
/**
 * module/transaction.php
 * Update: Integration der bankaccount-Tabelle in die Lookup-Logik.
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN (Unverändert) ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM transaction WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/transaction&msg=deleted";
}

if (isset($_POST['save_transaction'])) {
    $id                = $_POST['id'] ?? null;
    $transactiontypeid = $_POST['transactiontypeid'] ?: null;
    $officialtypeid    = $_POST['officialtypeid'] ?: null;
    $date              = $_POST['date'] ?: date('Y-m-d');
    $fromtypeid        = $_POST['fromtypeid'] ?: null;
    $fromid            = $_POST['fromid'] ?: null;
    $totypeid          = $_POST['totypeid'] ?: null;
    $toid              = $_POST['toid'] ?: null;
    $amount            = str_replace(',', '.', $_POST['amount'] ?? '0'); 
    $description       = $_POST['description'] ?? '';
    $vehicleid         = $_POST['vehicleid'] ?: null;
    $personid          = $_POST['personid'] ?: null;
    $note              = $_POST['note'] ?? '';

    $params = [$transactiontypeid, $officialtypeid, $date, $fromtypeid, $fromid, $totypeid, $toid, $amount, $description, $vehicleid, $personid, $note];

    if (!empty($id)) {
        $sql = "UPDATE transaction SET transactiontypeid=?, officialtypeid=?, date=?, fromtypeid=?, fromid=?, totypeid=?, toid=?, amount=?, description=?, vehicleid=?, personid=?, note=?, updateddate=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/transaction&msg=updated";
    } else {
        $sql = "INSERT INTO transaction (transactiontypeid, officialtypeid, date, fromtypeid, fromid, totypeid, toid, amount, description, vehicleid, personid, note, createddate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/transaction&msg=created";
    }
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATEN LADEN (Erweitert um bankaccount) ---
$limit  = 25; 
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;
$f_q = $_GET['q'] ?? '';
$f_start = $_GET['start'] ?? '';
$f_end = $_GET['end'] ?? '';
$where = ["1=1"]; $p_sql = [];
if ($f_q) { $where[] = "(t.description LIKE ? OR t.note LIKE ?)"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; }
if ($f_start) { $where[] = "t.date >= ?"; $p_sql[] = $f_start; }
if ($f_end) { $where[] = "t.date <= ?"; $p_sql[] = $f_end; }
$whereSql = implode(" AND ", $where);
$totalRows = $pdo->prepare("SELECT COUNT(*) FROM transaction t WHERE $whereSql");
$totalRows->execute($p_sql);
$totalPages = ceil($totalRows->fetchColumn() / $limit);

$entityTypes   = $pdo->query("SELECT * FROM transactionentitytype")->fetchAll();
$transTypes    = $pdo->query("SELECT id, name FROM transactiontype ORDER BY name ASC")->fetchAll();
$officialTypes = $pdo->query("SELECT id, name FROM officialtype ORDER BY id ASC")->fetchAll();

$raw_p = $pdo->query("SELECT id, CONCAT(lastname, ', ', firstname) as n FROM person")->fetchAll();
$raw_v = $pdo->query("SELECT id, licenseplate as n FROM vehicle")->fetchAll();
$raw_c = $pdo->query("SELECT id, name as n FROM company")->fetchAll();
$raw_x = $pdo->query("SELECT id, name as n FROM cashbox")->fetchAll();
$raw_b = $pdo->query("SELECT id, name as n FROM bankaccount")->fetchAll(); // Neu hinzugefügt

$nameIndex = [
    'person'      => array_column($raw_p, 'n', 'id'), 
    'vehicle'     => array_column($raw_v, 'n', 'id'), 
    'company'     => array_column($raw_c, 'n', 'id'), 
    'cashbox'     => array_column($raw_x, 'n', 'id'),
    'bankaccount' => array_column($raw_b, 'n', 'id') // Neu hinzugefügt
];
$typeToTable = array_column($entityTypes, 'tablename', 'id');

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM transaction WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
    if (isset($_GET['duplicate'])) { unset($edit['id']); }
}

$listStmt = $pdo->prepare("SELECT t.*, tt.name as typename, ot.name as officialname FROM transaction t LEFT JOIN transactiontype tt ON t.transactiontypeid = tt.id LEFT JOIN officialtype ot ON t.officialtypeid = ot.id WHERE $whereSql ORDER BY t.date DESC, t.id DESC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<?php if (isset($_GET['edit'])): ?>
    <div class="card main-card-style" style="border-left: 5px solid #3b82f6;">
        <h3 style="margin-top:0; margin-bottom: 20px;">💸 <?= (isset($edit['id']) ? 'Buchung bearbeiten' : (isset($_GET['duplicate']) ? 'Buchung duplizieren' : 'Neue Buchung erfassen')) ?></h3>
        <form method="post" action="/?route=module/transaction" class="form-container">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div>
                    <div class="form-row"><label>Datum</label><input type="date" name="date" value="<?= $edit['date'] ?? date('Y-m-d') ?>" required></div>
                    <div class="form-row"><label>Betrag (€)</label><input type="text" name="amount" value="<?= $edit ? number_format((float)$edit['amount'], 2, ',', '') : '' ?>" placeholder="0,00" required></div>
                    <div class="form-row"><label>Kategorie</label><select name="transactiontypeid"><option value="">-- wählen --</option><?php foreach($transTypes as $tt): ?><option value="<?= $tt['id'] ?>" <?= ($edit['transactiontypeid'] ?? '') == $tt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tt['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                    <div class="form-row"><label>Art</label><select name="officialtypeid"><?php foreach($officialTypes as $ot): ?><option value="<?= $ot['id'] ?>" <?= ($edit['officialtypeid'] ?? '') == $ot['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ot['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                </div>
                <div>
                    <div class="form-row"><label>Quelle (F)</label>
                        <div style="display:flex; flex-grow:1; gap:5px;">
                            <select name="fromtypeid" id="fromtypeid" onchange="updateLookup('from')" style="width:100px;">
                                <option value="">-- Typ --</option>
                                <?php foreach($entityTypes as $et): ?><option value="<?= $et['id'] ?>" data-table="<?= $et['tablename'] ?>" <?= ($edit['fromtypeid'] ?? '') == $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['name'] ?? '') ?></option><?php endforeach; ?>
                            </select>
                            <select name="fromid" id="fromid" style="flex-grow:1;"><option value="">-- wählen --</option></select>
                        </div>
                    </div>
                    <div class="form-row"><label>Ziel (T)</label>
                        <div style="display:flex; flex-grow:1; gap:5px;">
                            <select name="totypeid" id="totypeid" onchange="updateLookup('to')" style="width:100px;">
                                <option value="">-- Typ --</option>
                                <?php foreach($entityTypes as $et): ?><option value="<?= $et['id'] ?>" data-table="<?= $et['tablename'] ?>" <?= ($edit['totypeid'] ?? '') == $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['name'] ?? '') ?></option><?php endforeach; ?>
                            </select>
                            <select name="toid" id="toid" style="flex-grow:1;"><option value="">-- wählen --</option></select>
                        </div>
                    </div>
                    <div class="form-row"><label>Fahrzeug</label><select name="vehicleid"><option value="">-- kein Bezug --</option><?php foreach($raw_v as $v): ?><option value="<?= $v['id'] ?>" <?= ($edit['vehicleid'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['n'] ?? '') ?></option><?php endforeach; ?></select></div>
                    <div class="form-row"><label>Zweck</label><input type="text" name="description" value="<?= htmlspecialchars($edit['description'] ?? '') ?>" placeholder="Zweck der Buchung"></div>
                </div>
            </div>
            <div class="form-row" style="margin-top: 10px;"><label>Notiz</label><textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if(isset($edit['id'])): ?>
                    <a href="/?route=module/transaction&edit=<?= $edit['id'] ?>&duplicate=1" class="btn-action duplicate-bg">👯 Duplizieren</a>
                    <a href="/?route=module/transaction&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
                <a href="/?route=module/transaction" class="btn-action cancel-bg">Abbrechen</a>
                <button type="submit" name="save_transaction" class="btn save" style="padding: 10px 40px; font-weight: bold;"> <?= isset($edit['id']) ? 'Aktualisieren' : 'Speichern' ?> </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card main-card-style" style="display:flex; justify-content: space-between; align-items: center; border-left: 5px solid #3b82f6;">
        <h2 style="margin:0; font-size: 20px; color: #1e293b;">💸 Transaktion</h2>
        <a href="/?route=module/transaction&edit=new" class="btn save" style="text-decoration:none; padding: 10px 25px; display:flex; align-items:center; gap:8px;"><span>+</span> Neue Buchung</a>
    </div>
<?php endif; ?>

<div class="card main-card-style" style="background: #f8fafc; border: 1px solid #e2e8f0;">
    <form method="get" action="/" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="route" value="module/transaction">
        <div style="flex: 3; min-width: 250px;"><label class="filter-label">🔍 Globale Suche</label><input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Suche..." class="filter-input"></div>
        <div style="flex: 1; min-width: 140px;"><label class="filter-label">Von</label><input type="date" name="start" value="<?= htmlspecialchars($f_start) ?>" class="filter-input"></div>
        <div style="flex: 1; min-width: 140px;"><label class="filter-label">Bis</label><input type="date" name="end" value="<?= htmlspecialchars($f_end) ?>" class="filter-input"></div>
        <div style="display: flex; gap: 8px;"><button type="submit" class="btn-filter filter-save">Suchen</button><a href="/?route=module/transaction" class="btn-filter filter-reset">Reset</a></div>
    </form>
</div>

<div class="card main-card-style">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 90px;">Datum</th>
                <th>Details (Von ➔ Zu | Art | Zweck)</th>
                <th style="text-align:right;">Betrag</th>
                <th style="text-align:right; width: 60px;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $t): 
                $fTab = $typeToTable[$t['fromtypeid']] ?? ''; $tTab = $typeToTable[$t['totypeid']] ?? '';
                $fName = $nameIndex[$fTab][$t['fromid']] ?? "ID:".$t['fromid']; $tName = $nameIndex[$tTab][$t['toid']] ?? "ID:".$t['toid'];
            ?>
            <tr <?= ($t['officialtypeid'] == 2) ? 'style="background: #fafafa; opacity: 0.8;"' : '' ?>>
                <td style="font-size: 13px; color: #475569;"><?= date('d.m.Y', strtotime($t['date'])) ?></td>
                <td>
                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($fName ?? '') ?> <span style="color:#cbd5e1;">➔</span> <?= htmlspecialchars($tName ?? '') ?></div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 3px;"><span class="badge"><?= htmlspecialchars($t['officialname'] ?? '') ?></span> | <?= htmlspecialchars($t['typename'] ?? 'Allgemein') ?> | <?= htmlspecialchars($t['description'] ?? '-') ?></div>
                </td>
                <td style="text-align:right; font-weight: bold; font-size: 15px; color: <?= ($t['officialtypeid'] == 2) ? '#94a3b8' : '#1e293b' ?>;"><?= number_format($t['amount'], 2, ',', '.') ?> €</td>
                <td style="text-align:right;">
                    <a href="/?route=module/transaction&edit=<?= $t['id'] ?>" class="action-link edit-link" title="Bearbeiten">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
const lookupData = { 
    "person": [<?php foreach($raw_p as $p) echo "{id:{$p['id']}, n:'👤 ".addslashes($p['n'] ?? '')."'},"; ?>], 
    "vehicle": [<?php foreach($raw_v as $v) echo "{id:{$v['id']}, n:'🚗 ".addslashes($v['n'] ?? '')."'},"; ?>], 
    "company": [<?php foreach($raw_c as $c) echo "{id:{$c['id']}, n:'🏢 ".addslashes($c['n'] ?? '')."'},"; ?>], 
    "cashbox": [<?php foreach($raw_x as $x) echo "{id:{$x['id']}, n:'🪙 ".addslashes($x['n'] ?? '')."'},"; ?>],
    "bankaccount": [<?php foreach($raw_b as $b) echo "{id:{$b['id']}, n:'🏦 ".addslashes($b['n'] ?? '')."'},"; ?>] // Neu hinzugefügt
};
function updateLookup(prefix, sId = null) {
    const tS = document.getElementById(prefix + 'typeid'); const target = document.getElementById(prefix + 'id'); if(!tS || !target) return;
    const table = tS.options[tS.selectedIndex]?.getAttribute('data-table'); target.innerHTML = '<option value="">-- wählen --</option>';
    if (table && lookupData[table]) { lookupData[table].forEach(i => { const o = document.createElement('option'); o.value = i.id; o.textContent = i.n; if (i.id == sId) o.selected = true; target.appendChild(o); }); }
}
window.onload = function() { updateLookup('from', '<?= $edit['fromid'] ?? 0 ?>'); updateLookup('to', '<?= $edit['toid'] ?? 0 ?>'); };
</script>

<style>
.main-card-style { width: 100%; box-sizing: border-box; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
.form-row label { width: 130px; min-width: 130px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
.btn.save { background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
.filter-label { display: block; font-size: 11px; margin-bottom: 6px; color: #64748b; text-transform: uppercase; font-weight: bold; }
.filter-input { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #fff; vertical-align: bottom; }
.btn-filter { height: 40px; padding: 0 25px; border-radius: 4px; font-size: 14px; font-weight: bold; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid transparent; text-decoration: none; box-sizing: border-box; min-width: 100px; vertical-align: bottom; }
.filter-save { background: #3b82f6; color: white; }
.filter-reset { background: #cbd5e1; color: #333; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.duplicate-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-size: 10px; font-weight: bold; }
.action-link { font-size: 18px; text-decoration: none; margin-right: 8px; }
</style>