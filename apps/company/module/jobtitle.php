<?php
/**
 * module/jobtitle.php
 * Verwaltung der Job-Bezeichnungen
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    // Optional: Hier könnte man prüfen, ob noch Mitarbeiter diesen Job-Titel nutzen
    $stmt = $pdo->prepare("DELETE FROM jobtitle WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/jobtitle&msg=deleted";
}

if (isset($_POST['save_jobtitle'])) {
    $id    = $_POST['id'] ?? null;
    $name  = $_POST['name'] ?? '';
    $note  = $_POST['note'] ?? '';

    if (!empty($id)) {
        $sql = "UPDATE jobtitle SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/?route=module/jobtitle&msg=updated";
    } else {
        $sql = "INSERT INTO jobtitle (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/?route=module/jobtitle&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. DATEN LADEN ---
$f_q = $_GET['q'] ?? '';
$where = "";
$params = [];
if ($f_q) {
    $where = " WHERE name LIKE ? OR note LIKE ?";
    $params = ["%$f_q%", "%$f_q%"];
}

$list = $pdo->prepare("SELECT * FROM jobtitle $where ORDER BY name ASC");
$list->execute($params);
$rows = $list->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    foreach($rows as $row) { if($row['id'] == $_GET['edit']) $edit = $row; }
}
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <h3 style="margin-top:0;">💼 <?= $edit ? 'Job-Titel bearbeiten' : 'Neuer Job-Titel' ?></h3>
    <form method="post" action="/?route=module/jobtitle" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div class="form-row">
                <label>Bezeichnung</label>
                <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Projektleiter, Entwickler..." required>
            </div>
            <div class="form-row">
                <label>Notiz / Beschreibung</label>
                <input type="text" name="note" value="<?= htmlspecialchars($edit['note'] ?? '') ?>" placeholder="Optionale Details zur Stelle...">
            </div>
        </div>
        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <?php if($edit): ?>
                <a href="/?route=module/jobtitle" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_jobtitle" class="btn save" style="background: #3b82f6; padding: 8px 25px;">
                <?= $edit ? 'Aktualisieren' : 'Hinzufügen' ?>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 style="margin:0;">Definierte Job-Titel</h4>
        <form method="get" action="/">
            <input type="hidden" name="route" value="module/jobtitle">
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Suchen..." style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Bezeichnung</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td style="color: #64748b; font-size: 13px;"><?= htmlspecialchars($r['note'] ?? '-') ?></td>
                <td style="text-align:right;">
                    <a href="/?route=module/jobtitle&edit=<?= $r['id'] ?>" class="action-link" style="text-decoration:none; margin-right:10px;">✎</a>
                    <a href="/?route=module/jobtitle&delete=<?= $r['id'] ?>" 
                       onclick="return confirm('Diesen Job-Titel wirklich löschen?')" 
                       style="text-decoration:none; color: #dc2626;">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($rows)): ?>
                <tr><td colspan="3" style="text-align:center; padding: 20px; color: #94a3b8;">Keine Job-Titel vorhanden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; }
.form-row { display: flex; flex-direction: column; gap: 5px; }
.form-row label { font-size: 12px; font-weight: bold; color: #475569; }
.form-row input { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.data-table th, .data-table td { padding: 10px; text-align: left; border-bottom: 1px solid #f1f5f9; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; text-decoration: none; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.btn.save { color: white; border: none; border-radius: 4px; cursor: pointer; }
</style>