<?php
/**
 * module/cashboxtype.php
 * Verwaltung der Kassentypen (z.B. Stationär, Mobil, Fahrer-Account)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM cashboxtype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/cashboxtype?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_type'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE cashboxtype SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/cashboxtype?msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO cashboxtype (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/cashboxtype?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. STATUSMELDUNGEN ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Neuer Kassentyp wurde angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Typ wurde gelöscht.</div>';
}

// --- 4. DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cashboxtype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM cashboxtype ORDER BY name ASC")->fetchAll();
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '⚙️ Kassentyp bearbeiten' : '⚙️ Neuen Kassentyp definieren' ?></h3>
    
    <form method="post" action="/cashboxtype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Fahrerkasse" required autofocus>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:10px;">Interne Notiz</label>
            <textarea name="note" rows="3" placeholder="Wofür wird dieser Typ verwendet?"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-top: 20px;">
            <button type="submit" name="save_type" class="btn save">Typ speichern</button>
            <?php if($edit): ?>
                <a href="/cashboxtype" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Name</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $t): ?>
            <tr>
                <td style="color:#aaa;">#<?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                <td style="font-size: 13px; color: #666;"><?= htmlspecialchars($t['note'] ?? '-') ?></td>
                <td style="text-align:right;">
                    <a href="/cashboxtype?edit=<?= $t['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/cashboxtype?delete=<?= $t['id'] ?>" class="action-link delete-link" onclick="return confirm('Wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
                <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Noch keine Typen vorhanden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
/* CSS für das 2-Spalten-Layout (einheitlich mit deinen anderen Modulen) */
.form-container { display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; align-items: center; min-height: 40px; }
.form-row label { width: 180px; min-width: 180px; font-weight: 500; color: #444; }
.form-row input, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
</style>