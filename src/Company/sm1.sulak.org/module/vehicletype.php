<?php
/**
 * module/vehicletype.php - Dynamisches Layout für Fahrzeugtypen
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// --- 1. LOGIK: AKTIONEN ---

// DUPLIZIEREN
if (isset($_POST['duplicate_vehicletype'])) {
    $edit = $_POST;
    $edit['id'] = '';
}

// SPEICHERN / UPDATE
if (isset($_POST['save_vehicletype'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $nameParam = urlencode($name);
    
    $fields = ['name', 'note'];
    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $params[] = ($val !== '') ? $val : null;
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE vehicletype SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicletype&msg=updated&name=$nameParam";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO vehicletype ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicletype&msg=created&name=$nameParam";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmtName = $pdo->prepare("SELECT name FROM vehicletype WHERE id = ?");
    $stmtName->execute([$_GET['delete']]);
    $info = $stmtName->fetch();
    $nameParam = $info ? urlencode($info['name']) : 'Unbekannt';

    $stmt = $pdo->prepare("DELETE FROM vehicletype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/vehicletype&msg=deleted&name=$nameParam";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STATUS ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    $pName = $_GET['name'] ?? 'Typ';
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
$isDuplicate = isset($_POST['duplicate_vehicletype']);
$showForm = ($isNew || $isEdit || $isDuplicate);

if ($edit === null && $isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM vehicletype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 3. LISTE ---
$sql = "SELECT * FROM vehicletype WHERE 1=1";
$queryParams = [];
if (!empty($searchTerm)) {
    $sql .= " AND (name LIKE ? OR note LIKE ? OR id LIKE ?)";
    $like = "%$searchTerm%";
    $queryParams = [$like, $like, $like];
}
$sql .= " ORDER BY name ASC LIMIT 100";
$stmtList = $pdo->prepare($sql);
$stmtList->execute($queryParams);
$list = $stmtList->fetchAll();
?>

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
        <input type="hidden" name="route" value="module/vehicletype">
        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Fahrzeugtyp suchen..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="padding: 8px 25px;">🔍 Suchen</button>
        <?php if(!empty($searchTerm)): ?>
            <a href="/?route=module/vehicletype" class="btn-action cancel-bg">✖ Filter löschen</a>
        <?php endif; ?>
    </form>
</div>

<?= $message ?>

<?php 
// --- FORMULAR BLOCK ---
ob_start(); ?>
<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">🚗 Fahrzeugtypen</h3>
        <a href="/?route=module/vehicletype&edit=new" class="btn-action neu-bg">+ Neuer Typ</a>
    </div>

    <?php if ($showForm): ?>
    <form method="post" action="/?route=module/vehicletype" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        
        <div style="display: grid; grid-template-columns: 1fr; gap: 40px;">
            <div>
                <h4 style="margin-top:0; color:#1e40af; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Allgemein</h4>
                <div class="form-row"><label>Bezeichnung</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. PKW, LKW, Anhänger" required>
                </div>
                <div class="form-row" style="align-items: flex-start; margin-top:10px;"><label style="margin-top:8px;">Notiz</label>
                    <textarea name="note" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/?route=module/vehicletype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/?route=module/vehicletype" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_vehicletype" class="btn-action dupli-bg">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_vehicletype" class="btn-action" style="padding:10px 40px; color:white; font-weight:bold; background:#3b82f6;">
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
                <th>Typ-Bezeichnung</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $v): ?>
            <tr>
                <td><small style="color: #94a3b8;">#<?= $v['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                <td><small><?= htmlspecialchars($v['note'] ?? '-') ?></small></td>
                <td style="text-align:right;"><a href="/?route=module/vehicletype&edit=<?= $v['id'] ?>" class="edit-link">✎</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 20px; color: #94a3b8;">Keine Datensätze gefunden.</td></tr>
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