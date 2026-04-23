<?php
/**
 * module/appuseradmin.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$currentPath = "/admin/appuseradmin";

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM appuser WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "$currentPath&msg=deleted";
}

if (isset($_POST['save_user'])) {
    $id            = $_POST['id'] ?? null;
    $username      = $_POST['username'] ?? '';
    $password      = $_POST['password'] ?? '';
    $personid      = !empty($_POST['personid']) ? $_POST['personid'] : null;
    $appuserroleid = !empty($_POST['appuserroleid']) ? $_POST['appuserroleid'] : null;
    $note          = $_POST['note'] ?? '';
    $active        = isset($_POST['active']) ? 1 : 0;

    if (!empty($id)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE appuser SET username=?, password=?, personid=?, appuserroleid=?, note=?, active=?, updatedat=NOW() WHERE id=?";
            $params = [$username, $hash, $personid, $appuserroleid, $note, $active, $id];
        } else {
            $sql = "UPDATE appuser SET username=?, personid=?, appuserroleid=?, note=?, active=?, updatedat=NOW() WHERE id=?";
            $params = [$username, $personid, $appuserroleid, $note, $active, $id];
        }
        $pdo->prepare($sql)->execute($params);
        $redirect = "$currentPath&msg=updated";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO appuser (username, password, personid, appuserroleid, note, active, createdat, updatedat) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $pdo->prepare($sql)->execute([$username, $hash, $personid, $appuserroleid, $note, $active]);
        $redirect = "$currentPath&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. DATEN LADEN ---
$personList = $pdo->query("SELECT id, firstname, lastname FROM person ORDER BY lastname, firstname")->fetchAll();
// Geändert: rolename -> name
$roleList   = $pdo->query("SELECT id, name FROM appuserrole ORDER BY name ASC")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM appuser WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("
    SELECT u.*, p.firstname, p.lastname, r.name AS role_display 
    FROM appuser u 
    LEFT JOIN person p ON u.personid = p.id 
    LEFT JOIN appuserrole r ON u.appuserroleid = r.id
    ORDER BY u.username ASC
")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <h3 style="margin-bottom: 15px;">👤 <?= $edit ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen' ?></h3>
    
    <form method="post" action="<?= $currentPath ?>" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row">
                    <label>Benutzername</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($edit['username'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Passwort</label>
                    <input type="password" name="password" <?= $edit ? '' : 'required' ?> placeholder="<?= $edit ? 'Unverändert lassen' : 'Passwort' ?>">
                </div>
                <div class="form-row">
                    <label>Benutzerrolle</label>
                    <select name="appuserroleid">
                        <option value="">-- Keine Rolle --</option>
                        <?php foreach($roleList as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= ($edit['appuserroleid'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row">
                    <label>Person</label>
                    <select name="personid">
                        <option value="">-- Keine Verknüpfung --</option>
                        <?php foreach($personList as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['personid'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['lastname'] . ", " . $p['firstname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Status</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="active" style="width: auto;" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>>
                        <span style="font-size: 13px;">Aktiv</span>
                    </div>
                </div>
                <div class="form-row">
                    <label>Notiz</label>
                    <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="<?= $currentPath ?>&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a>
                <a href="<?= $currentPath ?>" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_user" class="btn save">Speichern</button>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Rolle</th>
                <th>Person</th>
                <th>Status</th>
                <th style="text-align:right;">Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $u): ?>
            <tr>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($u['role_display'] ?? 'Keine') ?></span></td>
                <td><?= $u['personid'] ? "👤 ".htmlspecialchars($u['lastname']) : '-' ?></td>
                <td><?= $u['active'] ? '<span class="badge" style="background:#dcfce7; color:#166534;">Aktiv</span>' : '<span class="badge" style="background:#fee2e2; color:#991b1b;">Inaktiv</span>' ?></td>
                <td style="text-align:right;">
                    <a href="<?= $currentPath ?>&edit=<?= $u['id'] ?>" class="action-link" style="font-size:18px;">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
/* CSS bleibt unverändert */
.form-container { display: flex; flex-direction: column; gap: 5px; }
.form-row { display: flex; align-items: center; margin-bottom: 8px; }
.form-row label { width: 130px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 7px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn.save { background: #10b981; color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-action { text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; display: inline-flex; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
</style>