<?php
/**
 * module/vehicleservicetype.php
 * Fahrzeug-Service-Typen: Formular + Journal mit dynamischem Layout
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM vehicleservicetype WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/vehicleservicetype&msg=deleted";
}

if (isset($_POST['save_vehicleservicetype'])) {
    $id   = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $note = $_POST['note'] ?? '';

    $params = [$name, $note];

    if (!empty($id)) {
        $sql = "UPDATE vehicleservicetype SET name=?, note=?, updatedat=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicleservicetype&msg=updated";
    } else {
        $sql = "INSERT INTO vehicleservicetype (name, note, createdat) VALUES (?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/vehicleservicetype&msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 2. FILTER & PAGINATION ---
$limit  = 25;
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;
$f_q    = $_GET['q'] ?? '';
$isSearching = !empty($f_q);

$where = ["1=1"];
$p_sql = [];
if ($f_q) {
    $where[] = "(name LIKE ? OR note LIKE ?)";
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%";
}
$whereSql = implode(" AND ", $where);

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM vehicleservicetype WHERE $whereSql");
$totalCount->execute($p_sql);
$totalCount = $totalCount->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. DATEN LADEN ---
$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM vehicleservicetype WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$listStmt = $pdo->prepare("SELECT * FROM vehicleservicetype WHERE $whereSql ORDER BY name ASC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1;">
    <form method="get" action="/" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/vehicleservicetype">
        
        <div style="flex: 1; min-width: 250px;">
            <label class="filter-label">🔍 Suche (Name, Notiz)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" class="filter-input" placeholder="Suchen..." style="width: 100%; box-sizing: border-box; margin: 0;">
        </div>
        
        <button type="submit" class="btn save" style="height: 38px; padding: 0 20px; margin: 0; display: inline-flex; align-items: center; justify-content: center; border: none; font-size: 14px; font-weight: 600; cursor: pointer; box-sizing: border-box;">Suchen</button>
        
        <a href="/?route=module/vehicleservicetype" class="btn reset-btn" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; background: #cbd5e1; color: #333; text-decoration: none; padding: 0 15px; border-radius: 4px; font-size: 14px; font-weight: 600; box-sizing: border-box; margin: 0;">Reset</a>
    </form>
</div>

<?php 
// Formular-Renderer
$renderForm = function() use ($edit) { ?>
    <div class="card" style="margin-bottom: 25px; border-left: 5px solid #10b981;">
        <h3 style="margin-bottom: 15px;">🔧 <?= $edit ? 'Service-Typ bearbeiten' : 'Neuen Service-Typ anlegen' ?></h3>
        <form method="post" action="/?route=module/vehicleservicetype" class="form-container">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                <div class="form-row"><label>Bezeichnung</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="z.B. Inspektion, Ölwechsel" required>
                </div>
            </div>
            <div class="form-row" style="align-items: flex-start; margin-top: 5px;">
                <label style="margin-top:8px;">Notiz</label>
                <textarea name="note" rows="2"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if($edit): ?>
                    <a href="/?route=module/vehicleservicetype&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Eintrag wirklich löschen?')">🗑 Löschen</a>
                    <a href="/?route=module/vehicleservicetype" class="btn-action cancel-bg">Abbrechen</a>
                <?php endif; ?>
                <button type="submit" name="save_vehicleservicetype" class="btn save" style="padding: 10px 40px; font-weight: bold;">Speichern</button>
            </div>
        </form>
    </div>
<?php };

$renderList = function() use ($list, $totalPages, $page, $f_q) { ?>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Service-Typ</th>
                    <th>Notiz</th>
                    <th style="text-align:right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                    <td style="font-size:13px; color:#64748b;"><?= htmlspecialchars($v['note'] ?: '-') ?></td>
                    <td style="text-align:right;">
                        <a href="/?route=module/vehicleservicetype&edit=<?= $v['id'] ?>" class="action-link edit-link" style="font-size: 18px; text-decoration: none;">✎</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
            <?php $pUrl = "/?route=module/vehicleservicetype&q=".urlencode($f_q)."&p="; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= $pUrl . $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
<?php }; ?>

<?php 
if ($isSearching) {
    $renderList(); echo "<br>"; $renderForm();
} else {
    $renderForm(); echo "<br>"; $renderList();
}
?>

<style>
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; }
.form-row label { width: 140px; min-width: 140px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
.btn.save { background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; display: inline-flex; align-items: center; box-sizing: border-box; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; font-weight: 600; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.pagination .page-link { padding: 6px 14px; border: 1px solid #ddd; text-decoration: none; border-radius: 4px; color: #333; }
.pagination .active { background: #10b981; color: #fff; border-color: #10b981; }
.filter-input { height: 38px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 10px; font-size: 14px; box-sizing: border-box; }
.filter-label { display: block; font-size: 11px; font-weight: bold; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
</style>