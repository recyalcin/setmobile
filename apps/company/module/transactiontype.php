<?php
/**
 * module/transactiontype.php
 * Verwaltung der Transaktionstypen
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM transactiontype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/transactiontype?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_type'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE transactiontype SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/transactiontype?msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO transactiontype (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/transactiontype?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. STATUSMELDUNGEN ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Typ wurde erfolgreich angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Typ wurde gelöscht.</div>';
}

// --- 4. EDIT-DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM transactiontype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 5. LISTE LADEN ---
$list = $pdo->query("SELECT * FROM transactiontype ORDER BY name ASC")->fetchAll();
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '⚙️ Typ bearbeiten' : '⚙️ Neuer Transaktionstyp' ?></h3>
    <form method="post" action="/transactiontype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Tankquittung" required autofocus>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:10px;">Notiz</label>
            <textarea name="note" rows="3" placeholder="Optionale Beschreibung..."><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-left: 20px;">
            <button type="submit" name="save_type" class="btn save">Speichern</button>
            <?php if($edit): ?>
                <a href="/transactiontype" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>📋 Vorhandene Typen</h3>
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
            <tr <?= (isset($edit['id']) && $edit['id'] == $t['id']) ? 'style="background: #fff9e6;"' : '' ?>>
                <td style="color:#aaa;">#<?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                <td style="font-size: 13px; color: #666;"><?= htmlspecialchars($t['note'] ?? '') ?></td>
                <td style="text-align:right;">
                    <a href="/transactiontype?edit=<?= $t['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/transactiontype?delete=<?= $t['id'] ?>" class="action-link delete-link" onclick="return confirm('Möchten Sie diesen Typ wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($list)): ?>
            <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Keine Daten vorhanden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; text-align: center; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
</style>