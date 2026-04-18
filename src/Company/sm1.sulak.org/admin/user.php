<?php
$id = $_GET['edit'] ?? '';
$username = '';

if (isset($_POST['save'])) {
    $uname = $_POST['username'];
    $upass = $_POST['password'];

    if (!empty($_POST['id'])) {
        if (!empty($upass)) {
            $hash = password_hash($upass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE user SET username=?, password=? WHERE id=?")->execute([$uname, $hash, $_POST['id']]);
        } else {
            $pdo->prepare("UPDATE user SET username=? WHERE id=?")->execute([$uname, $_POST['id']]);
        }
    } else {
        $hash = password_hash($upass, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO user (username, password) VALUES (?,?)")->execute([$uname, $hash]);
    }
    header("location: ./user?success=1"); exit;
}

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT username FROM user WHERE id=?");
    $stmt->execute([$id]);
    $username = $stmt->fetchColumn();
}
$list = $pdo->query("SELECT id, username FROM user")->fetchAll();
?>

<div class="card">
    <h2>Benutzerverwaltung</h2>
    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
            <div><label>Benutzername</label><input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required></div>
            <div><label>Passwort (leer lassen für keine Änderung)</label><input type="password" name="password"></div>
        </div>
        <button type="submit" name="save" class="btn save">Speichern</button>
    </form>
    <table>
        <?php foreach($list as $u): ?>
            <tr onclick="window.location='./user?edit=<?= $u['id'] ?>'"><td><?= $u['username'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>