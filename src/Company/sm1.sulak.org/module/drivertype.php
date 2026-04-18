<?php
/**
 * module/drivertype.php
 * Verwaltung der Fahrertypen-Stammdaten
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM drivertype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/drivertype&msg=deleted";
}

if (isset($_POST['save_drivertype'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE drivertype SET name=?, note=?, updateddate=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $note, $id]);
        $redirect = "/?route=module/drivertype&msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO drivertype (name, note, createddate) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute([$name, $note]);
        $redirect = "/?route=module/drivertype&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. FILTER & LISTE ---
$f_q = $_GET['q'] ?? '';
$where = "1=1";
$params = [];

if ($f_q) {
    $where = "(name LIKE ? OR note LIKE ?)";
    $params = ["%$f_q%", "%$f_q%"];
}

// Edit-Modus
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM drivertype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Liste laden
$stmt = $pdo->prepare("SELECT * FROM drivertype WHERE $where ORDER BY name ASC");
$stmt->execute($params);
$list = $stmt->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #6366f1;">
    <h3 style="margin-top:0;">🏷️ <?= $edit ? 'Fahrertyp bearbeiten' : 'Neuen Fahrertyp anlegen' ?></h3>
    
    <form method="post" action="/?route=module/drivertype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Bezeichnung</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Festangestellt oder Aushilfe" required>
        </div>

        <div class="form-row" style="align-items: flex-start;">
            <label style="margin-top:8px;">Notiz</label>
            <textarea name="note" rows="3" placeholder="Interne Beschreibung..."><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="/?route=module/drivertype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Diesen Typ wirklich löschen?')">🗑 Löschen</a>
                <a href="/?route=module/drivertype" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_drivertype" class="btn save" style="padding: 10px 40px; font-weight: bold; background:#6366f1;">
                <?= $edit ? 'Aktualisieren' : 'Speichern' ?>
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 style="margin:0;">Vorhandene Typen</h4>
        <form method="get" action="/" style="display:flex; gap:5px;">
            <input type="hidden" name="route" value="module/drivertype">
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Suche..." style="padding:5px; border:1px solid #ddd; border-radius:4px;">
            <button type="submit" class="btn" style="background:#cbd5e1; padding:5px 10px;">🔍</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 200px;">Name</th>
                <th>Notiz</th>
                <th style="text-align:right; width: 100px;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($list)): ?>
                <tr><td colspan="3" style="text-align:center; color:#94a3b8;">Keine Einträge gefunden.</td></tr>
            <?php endif; ?>
            <?php foreach ($list as $item): ?>
            <tr>
                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($item['name']) ?></td>
                <td style="color: #64748b; font-size: 13px;"><?= nl2br(htmlspecialchars($item['note'] ?? '')) ?></td>
                <td style="text-align:right;">
                    <a href="/?route=module/drivertype&edit=<?= $item['id'] ?>" class="action-link edit-link" style="font-size: 18px;">✎</a>
                    <a href="/?route=module/drivertype&delete=<?= $item['id'] ?>" class="action-link delete-link" style="font-size: 18px; color:#ef4444; margin-left:10px;" onclick="return confirm('Wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; align-items: center; }
.form-row label { width: 150px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn.save { color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.action-link { text-decoration: none; }
</style>