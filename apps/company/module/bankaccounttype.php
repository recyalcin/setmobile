<?php
/**
 * module/bankaccounttype.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// Sichtbarkeits-Logik für das Formular
$showForm = isset($_GET['edit']) || isset($_POST['duplicate_bankaccounttype']);

// --- 1. LOGIK: AKTIONEN ---

// SPEICHERN / UPDATE
if (isset($_POST['save_bankaccounttype'])) {
    $id = $_POST['id'] ?? null;
    
    $fields = ['name', 'note'];

    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $params[] = ($val !== '') ? $val : null;
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE bankaccounttype SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/bankaccounttype&msg=updated";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO bankaccounttype ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/bankaccounttype&msg=created";
    }
}

// DUPLIZIEREN
if (isset($_POST['duplicate_bankaccounttype'])) {
    $edit = $_POST;
    $edit['id'] = '';
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM bankaccounttype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/bankaccounttype&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATEN LADEN ---
if ($edit === null) {
    $isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
    if (isset($_GET['edit']) && !$isNew) {
        $stmt = $pdo->prepare("SELECT * FROM bankaccounttype WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
} else { $isNew = false; }

// --- 3. LISTE ---
$sql = "SELECT * FROM bankaccounttype WHERE 1=1";
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
</style>

<?php
// BLOCK 1: FORMULAR
ob_start(); ?>
<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">🏦 Bank Account Type</h3>
        <a href="/bankaccounttype&edit=new" class="btn-action neu-bg">+ Neuer Typ</a>
    </div>
    
    <?php if ($showForm): ?>
    <form method="post" action="/bankaccounttype" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        <div style="display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Typ Name</label><input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Girokonto, Sparkonto..." required></div>
            </div>
            <div>
                <div class="form-row" style="align-items: flex-start;"><label style="margin-top:8px;">Notiz</label><textarea name="note" rows="4"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/bankaccounttype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/bankaccounttype" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_bankaccounttype" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_bankaccounttype" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;"><?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?></button>
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
        <input type="hidden" name="route" value="module/bankaccounttype">
        <input type="text" name="search" value="'.htmlspecialchars($searchTerm).'" placeholder="Suche in Name, Notiz oder ID..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="cursor:pointer;">🔍 Suchen</button>
        '.(!empty($searchTerm) ? '<a href="/bankaccounttype" class="btn-action cancel-bg">✖ Filter löschen</a>' : '').'
    </form>
</div>';

// BLOCK 3: LISTE
ob_start(); ?>
<div class="card">
    <table class="data-table">
        <thead><tr><th>ID</th><th>Name</th><th>Notiz</th><th>Erstellt am</th><th style="text-align:right;">Aktionen</th></tr></thead>
        <tbody>
            <?php foreach ($list as $item): ?>
            <tr>
                <td><small>#<?= $item['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td><small><?= htmlspecialchars($item['note'] ?? '-') ?></small></td>
                <td><small><?= $item['createdat'] ? date('d.m.Y H:i', strtotime($item['createdat'])) : '-' ?></small></td>
                <td style="text-align:right;">
                    <a href="/bankaccounttype&edit=<?= $item['id'] ?>" class="edit-link">✎</a>
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