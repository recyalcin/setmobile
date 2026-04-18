<?php
/**
 * module/usermanager.php
 * Benutzerverwaltung mit Personen-Verknüpfung
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;
$currentRoute = "module/usermanager";

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=$currentRoute&msg=deleted";
}

if (isset($_POST['save_user'])) {
    $id       = $_POST['id'] ?? null;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $personid = !empty($_POST['personid']) ? $_POST['personid'] : null;
    $note     = $_POST['note'] ?? '';
    $active   = isset($_POST['active']) ? 1 : 0;

    if (!empty($id)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE user SET username=?, password=?, personid=?, note=?, active=?, updatedat=NOW() WHERE id=?";
            $params = [$username, $hash, $personid, $note, $active, $id];
        } else {
            $sql = "UPDATE user SET username=?, personid=?, note=?, active=?, updatedat=NOW() WHERE id=?";
            $params = [$username, $personid, $note, $active, $id];
        }
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=$currentRoute&msg=updated";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO user (username, password, personid, note, active, createdat, updatedat) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $pdo->prepare($sql)->execute([$username, $hash, $personid, $note, $active]);
        $redirect = "/?route=$currentRoute&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. DATEN LADEN ---

// Personen für das Dropdown laden (Lookup)
// Ich nehme an, die Tabelle 'person' hat id, firstname, lastname
$personList = $pdo->query("SELECT id, firstname, lastname FROM person ORDER BY lastname, firstname")->fetchAll();

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Liste der Benutzer inkl. Personennamen laden (LEFT JOIN)
$list = $pdo->query("
    SELECT u.*, p.firstname, p.lastname 
    FROM user u 
    LEFT JOIN person p ON u.personid = p.id 
    ORDER BY u.username ASC
")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <h3 style="margin-bottom: 15px;">👤 <?= $edit ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen' ?></h3>
    
    <form method="post" action="/?route=<?= $currentRoute ?>" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row">
                    <label>Benutzername</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($edit['username'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Passwort</label>
                    <input type="password" name="password" <?= $edit ? '' : 'required' ?> placeholder="<?= $edit ? 'Leer lassen für keine Änderung' : 'Passwort vergeben' ?>">
                </div>
                <div class="form-row">
                    <label>Verknüpfte Person</label>
                    <select name="personid" style="flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <option value="">-- Keine Person verknüpft --</option>
                        <?php foreach($personList as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['personid'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['lastname'] . ", " . $p['firstname']) ?> (#<?= $p['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row">
                    <label>Status</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="active" style="width: auto;" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>>
                        <span style="font-size: 13px; color: #475569;">Account aktiv</span>
                    </div>
                </div>
                <div class="form-row" style="align-items: flex-start;">
                    <label>Notiz</label>
                    <textarea name="note" style="flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="/?route=<?= $currentRoute ?>&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Benutzer unwiderruflich löschen?')">🗑 Löschen</a>
                <a href="/?route=<?= $currentRoute ?>" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_user" class="btn save" style="padding: 10px 40px; font-weight: bold; background:#10b981;">Speichern</button>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Benutzername</th>
                <th>Verknüpfte Person</th>
                <th>Status</th>
                <th>Info / Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $u): ?>
            <tr>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong> <small style="color:#cbd5e1;">#<?= $u['id'] ?></small></td>
                <td>
                    <?php if($u['personid']): ?>
                        <span style="font-size: 13px; color: #334155;">
                            👤 <?= htmlspecialchars($u['lastname'] . ", " . $u['firstname']) ?>
                        </span>
                    <?php else: ?>
                        <small style="color:#94a3b8;">-</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $u['active'] ? '<span class="badge" style="background:#dcfce7; color:#166534;">Aktiv</span>' : '<span class="badge" style="background:#fee2e2; color:#991b1b;">Inaktiv</span>' ?>
                </td>
                <td style="font-size:12px; color:#64748b; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($u['note'] ?? '') ?>
                </td>
                <td style="text-align:right;">
                    <a href="/?route=<?= $currentRoute ?>&edit=<?= $u['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
/* CSS bleibt wie gehabt */
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
.form-row label { width: 140px; min-width: 140px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select { flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn.save { background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; display: inline-flex; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
</style>