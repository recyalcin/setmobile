<?php
/**
 * module/transactionentitytype.php
 * Verwaltung der Entitäts-Typen (Mapping auf Datenbank-Tabellen)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM transactionentitytype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/transactionentitytype?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_entitytype'])) {
    $id        = $_POST['id'] ?? null;
    $name      = $_POST['name'] ?? '';
    $tablename = $_POST['tablename'] ?? '';
    $note      = $_POST['note'] ?? '';

    if (!empty($id)) {
        $sql = "UPDATE transactionentitytype SET name=?, tablename=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $tablename, $note, $id]);
        $redirect = "/transactionentitytype?msg=updated";
    } else {
        $sql = "INSERT INTO transactionentitytype (name, tablename, note, createddate) VALUES (?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $tablename, $note]);
        $redirect = "/transactionentitytype?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM transactionentitytype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM transactionentitytype ORDER BY id ASC")->fetchAll();

if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Neuer Entitäts-Typ angelegt.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Änderungen gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Typ wurde gelöscht.</div>';
}
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '🔗 Entitäts-Typ bearbeiten' : '🔗 Neuen Entitäts-Typ definieren' ?></h3>
    <p style="font-size: 13px; color: #666; margin-bottom: 20px;">
        Hier definierst du, welche Tabellen als Quelle oder Ziel für Buchungen dienen können.
    </p>

    <form method="post" action="/transactionentitytype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Anzeigename</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Fahrzeug oder Mitarbeiter" required>
        </div>

        <div class="form-row">
            <label>DB Tabellenname</label>
            <input type="text" name="tablename" value="<?= htmlspecialchars($edit['tablename'] ?? '') ?>" placeholder="z.B. vehicle (exakter Name der Tabelle)" required>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:10px;">Notiz</label>
            <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; padding-left: 20px;">
            <button type="submit" name="save_entitytype" class="btn save">Typ speichern</button>
            <?php if($edit): ?>
                <a href="/transactionentitytype" class="btn" style="background:#eee; color:#333; text-decoration:none; margin-left:10px;">Abbrechen</a>
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
                <th>Tabelle</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $t): ?>
            <tr>
                <td style="color:#aaa;">#<?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                <td><code style="background:#f0f2f5; padding:2px 5px; border-radius:3px; color:#2980b9;"><?= htmlspecialchars($t['tablename']) ?></code></td>
                <td style="font-size: 12px; color: #777;"><?= htmlspecialchars($t['note'] ?? '-') ?></td>
                <td style="text-align:right;">
                    <a href="/transactionentitytype?edit=<?= $t['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/transactionentitytype?delete=<?= $t['id'] ?>" class="action-link delete-link" onclick="return confirm('Wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
</style>