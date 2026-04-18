<?php
/**
 * module/cash.php
 * Verwaltung von Bar-Buchungen
 */
if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$baseurl = "/cash";

// --- 1. AKTIONEN ---
if (isset($_POST['speichern'])) {
    $p_type   = $_POST['cashtypeid'] ?: null;
    $p_amount = $_POST['amount'] ?? 0;
    $p_desc   = $_POST['description'] ?? '';
    $p_date   = $_POST['entrydate'] ?? date('Y-m-d');

    if (!empty($_POST['id'])) {
        $pdo->prepare("UPDATE cash SET cashtypeid=?, amount=?, description=?, entrydate=? WHERE id=?")
            ->execute([$p_type, $p_amount, $p_desc, $p_date, $_POST['id']]);
        $save_id = $_POST['id'];
    } else {
        $pdo->prepare("INSERT INTO cash (cashtypeid, amount, description, entrydate) VALUES (?,?,?,?)")
            ->execute([$p_type, $p_amount, $p_desc, $p_date]);
        $save_id = $pdo->lastInsertId();
    }
    header("Location: $baseurl&edit=" . $save_id . "&success=1"); exit;
}

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $pdo->prepare("DELETE FROM cash WHERE id = ?")->execute([(int)$_GET['delete']]);
    header("Location: $baseurl"); exit;
}

// --- 2. DATEN LADEN ---
$id = $_GET['edit'] ?? '';
$cashtypeid = ''; $amount = ''; $description = ''; $entrydate = date('Y-m-d');

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM cash WHERE id = ?");
    $stmt->execute([(int)$id]);
    if ($row = $stmt->fetch()) {
        $cashtypeid  = $row['cashtypeid'];
        $amount      = $row['amount'];
        $description = $row['description'];
        $entrydate   = $row['entrydate'];
    }
}

$types = $pdo->query("SELECT * FROM cashtype ORDER BY name")->fetchAll();
$list  = $pdo->query("SELECT c.*, ct.name as typename FROM cash c LEFT JOIN cashtype ct ON c.cashtypeid = ct.id ORDER BY c.entrydate DESC LIMIT 200")->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Bar-Buchungen</h2>
        <a href="<?= $baseurl ?>" class="btn new">+ Neuer Eintrag</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; margin-bottom:15px;">✓ Gespeichert!</div>
    <?php endif; ?>

    <form method="post" action="<?= $baseurl ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
        <div class="form-grid">
            <div>
                <label>Kategorie</label>
                <select name="cashtypeid">
                    <option value="">-- keine --</option>
                    <?php foreach($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($cashtypeid == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Betrag (€)</label>
                <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars((string)$amount) ?>" required>
            </div>
            <div>
                <label>Datum</label>
                <input type="date" name="entrydate" value="<?= htmlspecialchars((string)$entrydate) ?>">
            </div>
            <div style="grid-column: span 3;">
                <label>Beschreibung</label>
                <textarea name="description" style="width:100%; min-height:60px;"><?= htmlspecialchars($description ?? '') ?></textarea>
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" name="speichern" class="btn save">Speichern</button>
            <a href="<?= $baseurl ?>" class="btn new">Neu</a>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Kategorie</th>
                <th>Beschreibung</th>
                <th>Betrag</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($list as $item): ?>
                <tr style="<?= ($id == $item['id']) ? 'background:#f0f7ff;' : '' ?>">
                    <td><?= htmlspecialchars($item['entrydate'] ?? '') ?></td>
                    <td><small><?= htmlspecialchars($item['typename'] ?? 'Keine') ?></small></td>
                    <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                    <td style="font-weight:bold; color:<?= ($item['amount'] < 0) ? 'red' : 'green' ?>">
                        <?= number_format((float)$item['amount'], 2, ',', '.') ?> €
                    </td>
                    <td>
                        <a href="<?= $baseurl ?>&edit=<?= $item['id'] ?>" style="color:#007bff;">Edit</a>
                        &nbsp;|&nbsp;
                        <a href="<?= $baseurl ?>&delete=<?= $item['id'] ?>" onclick="return confirm('Löschen?')" style="color:#dc3545;">Del</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($list)): ?>
                <tr><td colspan="5" style="text-align:center; color:#999; padding:20px;">Keine Einträge vorhanden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
