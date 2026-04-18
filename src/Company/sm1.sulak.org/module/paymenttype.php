<?php
/**
 * module/paymenttype.php
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: AKTIONEN ---

if (isset($_POST['save_paymenttype']) || isset($_POST['duplicate_paymenttype'])) {
    $id   = (isset($_POST['duplicate_paymenttype'])) ? null : ($_POST['id'] ?? null);
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    $params = [$name, $note];

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE paymenttype SET name=?, note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/paymenttype&msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO paymenttype (name, note, createdat) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/paymenttype&msg=created";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM paymenttype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/paymenttype&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. DATEN LADEN ---

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM paymenttype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$list = $pdo->query("SELECT * FROM paymenttype ORDER BY name ASC")->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #06b6d4;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">💳 Zahlungsarten</h3>
        <a href="/?route=module/paymenttype&edit=new" class="btn-action neu-bg" style="text-decoration:none;">+ Neue Zahlungsart</a>
    </div>

    <form method="post" action="/?route=module/paymenttype" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-row">
                <label>Bezeichnung</label>
                <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required placeholder="z.B. Barzahlung">
            </div>
            <div class="form-row">
                <label>Notiz</label>
                <input type="text" name="note" value="<?= htmlspecialchars($edit['note'] ?? '') ?>" placeholder="Zusatzinfos">
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div>
                <?php if($edit): ?>
                    <a href="/?route=module/paymenttype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Zahlungsart wirklich löschen?')">🗑 Löschen</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if($edit): ?>
                    <a href="/?route=module/paymenttype" class="btn-action cancel-bg" style="text-decoration:none;">Abbrechen</a>
                    <button type="submit" name="duplicate_paymenttype" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button>
                <?php endif; ?>
                <button type="submit" name="save_paymenttype" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#06b6d4;">
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
                <th style="width: 50px;">ID</th>
                <th>Zahlungsart</th>
                <th>Notiz</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $item): ?>
            <tr>
                <td><small>#<?= $item['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td><span style="color:#64748b; font-size:12px;"><?= htmlspecialchars($item['note'] ?: '-') ?></span></td>
                <td style="text-align:right;">
                    <a href="/?route=module/paymenttype&edit=<?= $item['id'] ?>" class="edit-link" style="color:#06b6d4;">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
/* CSS Konsistenz */
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 110px; font-weight: bold; font-size: 13px; color: #475569; }
.form-row input { flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
.btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; }
.neu-bg { background: #ecfeff; color: #0891b2; border: 1px solid #06b6d4; }
.dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; text-decoration:none; }
.cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.edit-link { font-size: 18px; text-decoration: none; }
</style>