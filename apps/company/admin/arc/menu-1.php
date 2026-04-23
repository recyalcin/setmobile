<?php
/**
 * admin/menu.php
 * Verwaltung mit "id", Notiz-Feld und Schutz gegen Endlosschleifen
 */

if (!isset($pdo)) { die("Direkter Zugriff verweigert."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/admin/menu?msg=deleted";
}

// --- 2. LOGIK: SPEICHERN ---
if (isset($_POST['save_menu'])) {
    $id        = $_POST['id'] ?? null;
    $name      = $_POST['name'] ?? '';
    $url       = $_POST['url'] ?? '';
    $note      = $_POST['note'] ?? '';
    $parentid  = (int)($_POST['parentid'] ?? 0);
    $sortorder = (int)($_POST['sortorder'] ?? 0);

    // --- AUTOMATISCHES RÜCKEN DER SORTIERUNG ---
    $shiftSql = "UPDATE menu SET sortorder = sortorder + 1 WHERE parentid = ? AND sortorder >= ?";
    $pdo->prepare($shiftSql)->execute([$parentid, $sortorder]);

    if (!empty($id)) {
        // Update mit ID
        $sql = "UPDATE menu SET name=?, url=?, parentid=?, sortorder=?, note=?, updatedat=CURRENT_TIMESTAMP WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $url, $parentid, $sortorder, $note, $id]);
        $redirect = "/admin/menu?msg=updated";
    } else {
        // Insert
        $sql = "INSERT INTO menu (name, url, parentid, sortorder, note) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$name, $url, $parentid, $sortorder, $note]);
        $redirect = "/admin/menu?msg=created";
    }
}

if ($redirect) {
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// --- 3. DATEN LADEN & REKURSIVE SORTIERUNG ---

$allRaw = $pdo->query("SELECT * FROM menu ORDER BY sortorder ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Hilfsfunktion mit Rekursions-Schutz (verhindert Memory Exhausted Error)
 */
function buildSortedMenu($items, $parentId = 0, $depth = 0, $visited = []) {
    if ($depth > 10) return []; // Harter Abbruch bei zu tiefer Verschachtelung

    $result = [];
    foreach ($items as $item) {
        if ($item['parentid'] == $parentId) {
            // Falls die ID in diesem Zweig schon vorkam -> Endlosschleife verhindern
            if (in_array($item['id'], $visited)) continue;

            $item['depth'] = $depth;
            $result[] = $item;

            $newVisited = $visited;
            $newVisited[] = $item['id'];
            
            $children = buildSortedMenu($items, $item['id'], $depth + 1, $newVisited);
            $result = array_merge($result, $children);
        }
    }
    return $result;
}

$sortedMenus = buildSortedMenu($allRaw);

// Edit-Modus laden
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'created') $message = '<div class="alert success">✅ Erstellt & Sortierung angepasst.</div>';
    if ($m === 'updated') $message = '<div class="alert success">💾 Gespeichert.</div>';
    if ($m === 'deleted') $message = '<div class="alert error">🗑️ Gelöscht.</div>';
}
?>

<?= $message ?>

<div class="card" style="margin-bottom: 30px;">
    <h3><?= $edit ? '📂 Menüpunkt bearbeiten' : '📂 Neuen Menüpunkt anlegen' ?></h3>
    <form method="post" action="/admin/menu" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-row">
            <label>Anzeigename</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-row">
            <label>Route / URL</label>
            <input type="text" name="url" value="<?= htmlspecialchars($edit['url'] ?? '') ?>" placeholder="z.B. module/person">
        </div>

        <div class="form-row">
            <label>Übergeordnet</label>
            <select name="parentid">
                <option value="0">-- Hauptmenü --</option>
                <?php foreach ($sortedMenus as $m): ?>
                    <?php if ($edit && $m['id'] == $edit['id']) continue; ?>
                    <option value="<?= $m['id'] ?>" <?= (isset($edit['parentid']) && $edit['parentid'] == $m['id']) ? 'selected' : '' ?>>
                        <?= str_repeat('  ', $m['depth']) ?><?= htmlspecialchars($m['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Sortierung</label>
            <input type="number" name="sortorder" value="<?= $edit['sortorder'] ?? '0' ?>" style="width: 80px;">
        </div>

        <div class="form-row">
            <label>Notiz</label>
            <textarea name="note" style="flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions" style="margin-left: 180px; margin-top: 10px;">
            <button type="submit" name="save_menu" class="btn save">Speichern</button>
            <?php if($edit): ?> 
                <a href="/admin/menu" class="btn" style="background:#ccc; color:#333; text-decoration:none; padding:8px 15px; border-radius:4px;">Abbrechen</a> 
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px;">Sort.</th>
                <th>Menüstruktur</th>
                <th>Route</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sortedMenus as $m): ?>
            <tr>
                <td style="color:#94a3b8; text-align:center;"><?= $m['sortorder'] ?></td>
                <td>
                    <?php if ($m['depth'] > 0): ?>
                        <span style="color:#cbd5e1; margin-left: <?= ($m['depth'] * 20) ?>px; margin-right: 10px;">↳</span>
                    <?php endif; ?>
                    <span style="<?= ($m['depth'] == 0) ? 'font-weight:bold;' : '' ?>">
                        <?= htmlspecialchars($m['name']) ?>
                    </span>
                </td>
                <td><code style="font-size:11px;">/<?= htmlspecialchars($m['url']) ?></code></td>
                <td style="text-align:right;">
                    <a href="/admin/menu?edit=<?= $m['id'] ?>" class="action-link edit-link">✎</a>
                    <a href="/admin/menu?delete=<?= $m['id'] ?>" class="action-link delete-link" onclick="return confirm('Löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.form-container { display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; align-items: center; min-height: 40px; }
.form-row label { width: 180px; min-width: 180px; font-weight: 500; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
.alert.success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
.alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
.btn.save { background: #3b82f6; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; }
.action-link { text-decoration: none; margin-left: 10px; }
</style>