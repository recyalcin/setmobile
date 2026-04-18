<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_POST['speichern'])) {
    $p_type = $_POST['cashtypeid'] ?? null;
    $p_amount = $_POST['amount'] ?? 0;
    $p_desc = $_POST['description'] ?? '';
    $p_date = $_POST['entrydate'] ?? date('Y-m-d');

    if (!empty($_POST['id'])) {
        $pdo->prepare("UPDATE cash SET cashtypeid=?, amount=?, description=?, entrydate=? WHERE id=?")->execute([$p_type, $p_amount, $p_desc, $p_date, $_POST['id']]);
        $save_id = $_POST['id'];
    } else {
        $pdo->prepare("INSERT INTO cash (cashtypeid, amount, description, entrydate) VALUES (?,?,?,?)")->execute([$p_type, $p_amount, $p_desc, $p_date]);
        $save_id = $pdo->lastInsertId();
    }
    header("Location: cash.php?edit=" . $save_id . "&success=1"); exit;
}

$id = $_GET['edit'] ?? '';
$cashtypeid = ''; $amount = ''; $description = ''; $entrydate = date('Y-m-d');

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM cash WHERE id = ?");
    $stmt->execute([(int)$id]);
    if ($row = $stmt->fetch()) {
        $cashtypeid = $row['cashtypeid']; $amount = $row['amount']; $description = $row['description']; $entrydate = $row['entrydate'];
    }
}

$types = $pdo->query("SELECT * FROM cashtype ORDER BY name")->fetchAll();
$list = $pdo->query("SELECT c.*, ct.name as typename FROM cash c LEFT JOIN cashtype ct ON c.cashtypeid = ct.id ORDER BY c.entrydate DESC")->fetchAll();

include 'header.php';
?>
<div class="card">
    <h2>Cash Buchungen</h2>
    <form method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
        <div class="form-grid">
            <div><label>Kategorie</label>
                <select name="cashtypeid">
                    <?php foreach($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($cashtypeid == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Betrag</label><input type="number" step="0.01" name="amount" value="<?= htmlspecialchars((string)$amount) ?>" required></div>
            <div><label>Datum</label><input type="date" name="entrydate" value="<?= htmlspecialchars($entrydate ?? '') ?>"></div>
            <div style="grid-column: span 3;"><label>Beschreibung</label><textarea name="description"><?= htmlspecialchars($description ?? '') ?></textarea></div>
        </div>
        <div class="btn-row"><button type="submit" name="speichern" class="btn save">Speichern</button><a href="cash.php" class="btn new">Neu</a></div>
    </form>
    <table>
        <thead><tr><th>Datum</th><th>Kategorie</th><th>Beschreibung</th><th>Betrag</th></tr></thead>
        <tbody>
            <?php foreach($list as $item): ?>
                <tr onclick="window.location='?edit=<?= $item['id'] ?>'" style="<?= ($id == $item['id']) ? 'background:#f0f7ff;' : '' ?>">
                    <td><?= $item['entrydate'] ?></td>
                    <td><small><?= htmlspecialchars($item['typename'] ?? 'Keine') ?></small></td>
                    <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                    <td style="font-weight:bold; color:<?= $item['amount'] < 0 ? 'red' : 'green' ?>"><?= number_format($item['amount'], 2) ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>