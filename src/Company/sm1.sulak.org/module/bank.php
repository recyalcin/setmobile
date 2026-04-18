<?php
$id = $_GET['edit'] ?? $_POST['id'] ?? '';
$name = ''; $info = '';

// Speichern
if (isset($_POST['save'])) {
    $pname = $_POST['name'] ?? '';
    $pinfo = $_POST['info'] ?? '';

    if (!empty($_POST['id'])) {
        $pdo->prepare("UPDATE bank SET name=?, info=? WHERE id=?")->execute([$pname, $pinfo, $_POST['id']]);
        $saveid = $_POST['id'];
    } else {
        $pdo->prepare("INSERT INTO bank (name, info) VALUES (?,?)")->execute([$pname, $pinfo]);
        $saveid = $pdo->lastInsertId();
    }
    header("location: ./bank?edit=$saveid&success=1"); exit;
}

// Löschen
if (isset($_POST['delete']) && !empty($id)) {
    $pdo->prepare("DELETE FROM bank WHERE id=?")->execute([$id]);
    header("location: ./bank?deleted=1"); exit;
}

// Laden
if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM bank WHERE id=?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) { $name = $row['name']; $info = $row['info']; }
}
$list = $pdo->query("SELECT * FROM bank ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <h2>Banken verwalten</h2>
    <form method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
        <div class="form-grid">
            <div>
                <label>Name der Bank</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required autofocus>
            </div>
            <div>
                <label>Zusatzinfo (IBAN / Konto)</label>
                <input type="text" name="info" value="<?= htmlspecialchars($info) ?>">
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" name="save" class="btn save">Speichern</button>
            <?php if($id): ?>
                <button type="submit" name="delete" class="btn delete" onclick="return confirm('Löschen?')">Löschen</button>
                <a href="bank" class="btn new">Neu</a>
            <?php endif; ?>
        </div>
    </form>

    <table>
        <thead><tr><th>Name</th><th>Info</th></tr></thead>
        <tbody>
            <?php foreach($list as $i): ?>
                <tr onclick="window.location='./bank?edit=<?= $i['id'] ?>'" style="<?= ($id==$i['id'])?'background:#f0f7ff':'' ?>">
                    <td><strong><?= htmlspecialchars($i['name']) ?></strong></td>
                    <td><small><?= htmlspecialchars($i['info']) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>