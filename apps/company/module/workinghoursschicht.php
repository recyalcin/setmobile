<?php
/**
 * module/workinghoursschicht.php - Verwaltung von Arbeitszeitschichten
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// --- 1. LOGIK: AKTIONEN ---

// DUPLIZIEREN
if (isset($_POST['duplicate_schicht'])) {
    $edit = $_POST;
    $edit['id'] = '';
}

// SPEICHERN / UPDATE
if (isset($_POST['save_schicht'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $nameParam = urlencode($name);
    
    $fields = ['name', 'note'];
    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $params[] = ($val !== '') ? $val : null;
    }

    if (!empty($id)) {
        // UPDATE
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE workinghoursschicht SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/workinghoursschicht&edit=new&msg=updated&name=$nameParam";
    } else {
        // INSERT
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO workinghoursschicht ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/workinghoursschicht&edit=new&msg=created&name=$nameParam";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    // Name für Info-Zeile holen
    $stmtName = $pdo->prepare("SELECT name FROM workinghoursschicht WHERE id = ?");
    $stmtName->execute([$_GET['delete']]);
    $name = $stmtName->fetchColumn() ?: 'Schicht';
    $nameParam = urlencode($name);

    $stmt = $pdo->prepare("DELETE FROM workinghoursschicht WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/workinghoursschicht&edit=new&msg=deleted&name=$nameParam";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STATUSMELDUNGEN (INFO-ZEILE) ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    $sName = $_GET['name'] ?? 'Schicht';
    $statusText = '';
    
    if ($m === 'created') $statusText = 'erfolgreich angelegt!';
    if ($m === 'updated') $statusText = 'erfolgreich aktualisiert!';
    if ($m === 'deleted') $statusText = 'wurde gelöscht!';
    
    if ($statusText) {
        $message = "<div class='alert info-box'>✨ <strong>" . htmlspecialchars($sName) . "</strong> $statusText</div>";
    }
}

// --- 3. DATEN LADEN (EDIT) ---
if ($edit === null) {
    $isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
    if (isset($_GET['edit']) && !$isNew) {
        $stmt = $pdo->prepare("SELECT * FROM workinghoursschicht WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
} else { $isNew = false; }

// --- 4. LISTE ---
$sql = "SELECT * FROM workinghoursschicht WHERE 1=1";
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
    .form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
    .form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; }
    .neu-bg { background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; }
    .dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; }
</style>

<?= $message ?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🕒 Schicht-Verwaltung</h3>
        <a href="/?route=module/workinghoursschicht&edit=new" class="btn-action neu-bg">+ Neue Schicht</a>
    </div>

    <form method="post" action="/?route=module/workinghoursschicht" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="Schichtname..." required>
                </div>
            </div>
            <div>
                <div class="form-row" style="align-items: flex-start;"><label style="margin-top:8px;">Notiz</label>
                    <textarea name="note" rows="4"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/?route=module/workinghoursschicht&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/?route=module/workinghoursschicht&edit=new" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_schicht" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_schicht" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;"><?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?></button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div style="margin-bottom: 15px;">
        <form method="get" action="/" style="display: flex; gap: 10px;">
            <input type="hidden" name="route" value="module/workinghoursschicht">
            <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Suche..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            <button type="submit" class="btn-action neu-bg">🔍 Suchen</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Schichtname</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $s): ?>
            <tr>
                <td><small>#<?= $s['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><small><?= htmlspecialchars($s['note'] ?? '-') ?></small></td>
                <td style="text-align:right;"><a href="/?route=module/workinghoursschicht&edit=<?= $s['id'] ?>" class="edit-link">✎</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>