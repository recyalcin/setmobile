<?php
/**
 * module/tripsource.php
 * Management von Trip-Quellen (z.B. Uber, Bolt, Telefon, etc.)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$route = "module/tripsource";

// --- 1. LOGIK: AKTIONEN ---

if (isset($_POST['save_tripsource']) || isset($_POST['duplicate_tripsource'])) {
    $id = (isset($_POST['duplicate_tripsource'])) ? null : ($_POST['id'] ?? null);
    
    $fields = ['name', 'note'];
    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $params[] = ($val !== '') ? $val : null;
    }

    if (!empty($id)) {
        // UPDATE
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE tripsource SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=$route&msg=updated";
    } else {
        // INSERT
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO tripsource ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=$route&msg=created";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM tripsource WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=$route&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATEN LADEN ---

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM tripsource WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM tripsource ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🌐 Trip-Quellen (Source)</h3>
        <a href="/?route=<?= $route ?>&edit=new" class="btn-action neu-bg" style="text-decoration:none;">+ Neue Quelle</a>
    </div>

    <form method="post" action="/?route=<?= $route ?>" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Uber, Bolt, Website..." required>
                </div>
            </div>
            <div>
                <div class="form-row">
                    <label>Notiz</label>
                    <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div>
                <?php if($edit && isset($edit['id'])): ?>
                    <a href="/?route=<?= $route ?>&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Quelle wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if($edit): ?>
                    <a href="/?route=<?= $route ?>" class="btn-action cancel-bg" style="text-decoration:none;">Abbrechen</a>
                    <button type="submit" name="duplicate_tripsource" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                <?php endif; ?>
                <button type="submit" name="save_tripsource" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;">
                    <?= ($edit && isset($edit['id'])) ? '💾 Update' : '💾 Speichern' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>Name</th>
                <th>Notiz</th>
                <th>Erstellt</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $s): ?>
            <tr>
                <td><small>#<?= $s['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><small><?= htmlspecialchars($s['note'] ?? '-') ?></small></td>
                <td style="font-size:11px;"><?= $s['createdat'] ? date('d.m.Y H:i', strtotime($s['createdat'])) : '-' ?></td>
                <td style="text-align:right;">
                    <a href="/?route=<?= $route ?>&edit=<?= $s['id'] ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
                <tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">Keine Quellen definiert.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
.form-row label { width: 100px; font-weight: bold; font-size: 12px; color: #475569; }
.form-row input, .form-row textarea { flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; }
.neu-bg { background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; }
.dupli-bg { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; text-decoration:none; }
.cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; }
</style>