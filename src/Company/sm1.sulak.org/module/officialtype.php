<?php
/**
 * module/officialtype.php
 * Verwaltung der Buchungs-Arten (z.B. Offiziell, Intern)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM officialtype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/module/officialtype?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_officialtype'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!empty($id)) {
        $sql = "UPDATE officialtype SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/module/officialtype?msg=updated";
    } else {
        $sql = "INSERT INTO officialtype (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/module/officialtype?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. STATUSMELDUNGEN ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Buchungs-Art wurde angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Typ wurde gelöscht.</div>';
}

// --- 4. EDIT-DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM officialtype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 5. LISTE LADEN ---
$list = $pdo->query("SELECT * FROM officialtype ORDER BY id ASC")->fetchAll();
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '📊 Buchungs-Art bearbeiten' : '📊 Neue Buchungs-Art definieren' ?></h3>
    
    <form method="post" action="/module/officialtype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Offiziell" required autofocus>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:10px;">Notiz</label>
            <textarea name="note" rows="3" placeholder="Zusatzinformationen..."><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-top: 20px;">
            <button type="submit" name="save_officialtype" class="btn save">Speichern</button>
            <?php if($edit): ?>
                <a href="/module/officialtype" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Bezeichnung</th>
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
                    <a href="/module/officialtype?edit=<?= $t['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/module/officialtype?delete=<?= $t['id'] ?>" class="action-link delete-link" onclick="return confirm('Möchten Sie diesen Typ wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; align-items: center; min-height: 40px; }
.form-row label { width: 180px; min-width: 180px; font-weight: 500; color: #444; }
.form-row input, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
</style>