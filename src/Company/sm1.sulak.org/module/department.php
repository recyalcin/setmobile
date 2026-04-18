<?php
/**
 * module/department.php
 * Verwaltung der Abteilungen (CRUD)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM department WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/module/department?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN (INSERT & UPDATE) ---
if (isset($_POST['save_department'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE department SET name=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $id]);
        $redirect = "/module/department?msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO department (name, createddate) VALUES (?, NOW())";
        $pdo->prepare($sql)->execute([$name]);
        $redirect = "/module/department?msg=created";
    }
}

// Redirect-Ausführung für Clean URLs
if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. STATUSMELDUNGEN ---
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Abteilung wurde erfolgreich angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen an der Abteilung gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Abteilung wurde gelöscht.</div>';
}

// --- 4. EDIT-DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM department WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// --- 5. LISTE LADEN ---
$list = $pdo->query("SELECT * FROM department ORDER BY name ASC")->fetchAll();
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? 'Abteilung bearbeiten' : 'Neue Abteilung anlegen' ?></h3>
    <form method="post" action="/module/department" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Abteilungsname</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Marketing, IT, Personalwesen" required autofocus>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-left: 20px;">
            <button type="submit" name="save_department" class="btn save">Abteilung speichern</button>
            <?php if($edit): ?>
                <a href="/module/department" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Vorhandene Abteilungen</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 80px;">ID</th>
                <th>Bezeichnung</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $d): ?>
            <tr <?= (isset($edit['id']) && $edit['id'] == $d['id']) ? 'style="background: #fff9e6;"' : '' ?>>
                <td style="color:#aaa;">#<?= $d['id'] ?></td>
                <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                <td style="text-align:right;">
                    <a href="/module/department?edit=<?= $d['id'] ?>" class="action-link edit-link" title="Bearbeiten">✎</a>
                    <a href="/module/department?delete=<?= $d['id'] ?>" class="action-link delete-link" onclick="return confirm('Möchten Sie diese Abteilung wirklich löschen?')" title="Löschen">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($list)): ?>
            <tr><td colspan="3" style="text-align:center; padding:30px; color:#999;">Keine Abteilungen in der Datenbank.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; text-align: center; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
</style>