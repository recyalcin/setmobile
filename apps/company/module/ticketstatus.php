<?php
/**
 * module/ticketstatus.php
 * Verwaltung der Ticket-Statuswerte
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: AKTIONEN ---

if (isset($_POST['save_status']) || isset($_POST['duplicate_status'])) {
    $id = (isset($_POST['duplicate_status'])) ? null : ($_POST['id'] ?? null);
    
    $fields = ['name', 'note'];
    $params = [];
    foreach ($fields as $f) {
        $params[] = ($_POST[$f] ?? '') !== '' ? $_POST[$f] : null;
    }

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE ticketstatus SET name=?, note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/ticketstatus&msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO ticketstatus (name, note, createdat) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/ticketstatus&msg=created";
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM ticketstatus WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/ticketstatus&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATEN LADEN ---

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM ticketstatus WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM ticketstatus ORDER BY id ASC")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">🚦 Ticket-Status verwalten</h3>
        <a href="/?route=module/ticketstatus&edit=new" class="btn-action neu-bg" style="text-decoration:none;">+ Neuer Status</a>
    </div>

    <form method="post" action="/?route=module/ticketstatus" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row">
                    <label>Status-Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. In Bearbeitung" required>
                </div>
            </div>
            <div>
                <div class="form-row">
                    <label>Notiz</label>
                    <textarea name="note" rows="2" placeholder="Optionale Beschreibung..."><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div>
                <?php if($edit): ?>
                    <a href="/?route=module/ticketstatus&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Diesen Status wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if($edit): ?>
                    <a href="/?route=module/ticketstatus" class="btn-action cancel-bg" style="text-decoration:none;">Abbrechen</a>
                    <button type="submit" name="duplicate_status" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                <?php endif; ?>
                <button type="submit" name="save_status" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#10b981;">
                    <?= $edit ? '💾 Update' : '💾 Speichern' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>Status</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $s): ?>
            <tr>
                <td><small>#<?= $s['id'] ?></small></td>
                <td>
                    <span class="badge" style="background:#ecfdf5; color:#065f46; border: 1px solid #10b981;">
                        <?= htmlspecialchars($s['name']) ?>
                    </span>
                </td>
                <td><small style="color:#64748b;"><?= htmlspecialchars($s['note'] ?? '-') ?></small></td>
                <td style="text-align:right;">
                    <a href="/?route=module/ticketstatus&edit=<?= $s['id'] ?>" class="edit-link" style="color:#10b981;">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
                <tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">Keine Statuswerte definiert.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
.form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; }
.neu-bg { background: #f0fdf4; color: #10b981; border: 1px solid #10b981; }
.dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; text-decoration:none; }
.cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
.edit-link { font-size: 18px; text-decoration: none; }
</style>