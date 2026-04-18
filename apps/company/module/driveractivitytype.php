<?php
/**
 * module/driveractivitytype.php
 * Verwaltung der Aktivitätstypen
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    // Vorher prüfen, ob der Typ bereits in driveractivity genutzt wird (Fremdschlüssel-Schutz)
    $check = $pdo->prepare("SELECT COUNT(*) FROM driveractivity WHERE driveractivitytypeid = ?");
    $check->execute([$_GET['delete']]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('Löschen nicht möglich: Dieser Typ wird bereits in Aktivitäten verwendet.'); window.location.href='/?route=module/driveractivitytype';</script>";
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM driveractivitytype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/driveractivitytype&msg=deleted";
}

if (isset($_POST['save_type'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!empty($id)) {
        $sql = "UPDATE driveractivitytype SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/?route=module/driveractivitytype&msg=updated";
    } else {
        $sql = "INSERT INTO driveractivitytype (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/?route=module/driveractivitytype&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. DATEN LADEN ---
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM driveractivitytype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM driveractivitytype ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #64748b;">
    <h3 style="margin-bottom: 15px;">⚙️ <?= $edit ? 'Aktivitätstyp bearbeiten' : 'Neuen Aktivitätstyp anlegen' ?></h3>
    
    <form method="post" action="/?route=module/driveractivitytype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required placeholder="z.B. Pause">
        </div>

        <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
            <label style="margin-top:8px;">Beschreibung/Notiz</label>
            <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="/?route=module/driveractivitytype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Typ wirklich löschen?')">🗑 Löschen</a>
                <a href="/?route=module/driveractivitytype" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_type" class="btn save" style="background:#64748b; padding: 10px 40px;">Speichern</button>
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
            <?php foreach ($list as $item): ?>
            <tr>
                <td><span class="badge"><?= $item['id'] ?></span></td>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($item['note'] ?: '-') ?></td>
                <td style="text-align:right;">
                    <a href="/?route=module/driveractivitytype&edit=<?= $item['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 150px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn.save { color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
</style>