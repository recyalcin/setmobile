<?php
/**
 * module/cashbox.php
 * Verwaltung der Kassen
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM cashbox WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/cashbox?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_cashbox'])) {
    $id            = $_POST['id'] ?? null;
    $cashboxtypeid = $_POST['cashboxtypeid'] ?: null;
    $name          = $_POST['name'] ?? '';
    $note          = $_POST['note'] ?? '';

    if (!empty($id)) {
        $sql = "UPDATE cashbox SET cashboxtypeid=?, name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$cashboxtypeid, $name, $note, $id]);
        $redirect = "/cashbox?msg=updated";
    } else {
        $sql = "INSERT INTO cashbox (cashboxtypeid, name, note, createddate) VALUES (?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$cashboxtypeid, $name, $note]);
        $redirect = "/cashbox?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. DATEN LADEN ---
// Kassen-Typen für das Dropdown (Tabelle muss existieren!)
$types = $pdo->query("SELECT id, name FROM cashboxtype ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cashbox WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Liste mit Typ-Namen laden
$list = $pdo->query("SELECT c.*, ct.name as typename 
                     FROM cashbox c 
                     LEFT JOIN cashboxtype ct ON c.cashboxtypeid = ct.id 
                     ORDER BY c.name ASC")->fetchAll();

if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Kasse wurde angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Kasse wurde gelöscht.</div>';
}
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '🪙 Kasse bearbeiten' : '🪙 Neue Kasse anlegen' ?></h3>
    
    <form method="post" action="/cashbox" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Hauptkasse" required autofocus>
        </div>

        <div class="form-row">
            <label>Kassen-Typ</label>
            <select name="cashboxtypeid">
                <option value="">-- Typ wählen --</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($edit['cashboxtypeid'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:10px;">Notiz</label>
            <textarea name="note" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-top: 20px;">
            <button type="submit" name="save_cashbox" class="btn save">Speichern</button>
            <?php if($edit): ?>
                <a href="/cashbox" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Typ</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $c): ?>
            <tr>
                <td style="color:#aaa;">#<?= $c['id'] ?></td>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><span class="badge"><?= htmlspecialchars($c['typename'] ?? '-') ?></span></td>
                <td style="text-align:right;">
                    <a href="/cashbox?edit=<?= $c['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/cashbox?delete=<?= $c['id'] ?>" class="action-link delete-link" onclick="return confirm('Wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; align-items: center; min-height: 40px; }
.form-row label { width: 180px; min-width: 180px; font-weight: 500; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.badge { background: #f0f2f5; padding: 2px 8px; border-radius: 4px; font-size: 11px; color: #666; }
</style>