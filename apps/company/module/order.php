<?php
// module/trip/order.php
// Prüfen, ob wir über die index.php kommen
if (!isset($pdo)) { header("Location: /trip/order"); exit; }

$id = $_GET['edit'] ?? $_POST['id'] ?? '';
$customerid = 0; $pickuplocation = ''; $destination = ''; 
$tripdate = date('Y-m-d\TH:i'); $amount = 0; $status = 'pending';

// SPEICHERN
if (isset($_POST['save'])) {
    $cid = (int)$_POST['customerid'];
    $pick = $_POST['pickuplocation'];
    $dest = $_POST['destination'];
    $tdate = $_POST['tripdate'];
    $amt = $_POST['amount'];
    $stat = $_POST['status'];

    if (!empty($_POST['id'])) {
        $sql = "UPDATE trip SET customerid=?, pickuplocation=?, destination=?, tripdate=?, amount=?, status=? WHERE id=?";
        $pdo->prepare($sql)->execute([$cid, $pick, $dest, $tdate, $amt, $stat, $_POST['id']]);
        $saveid = $_POST['id'];
    } else {
        $sql = "INSERT INTO trip (customerid, pickuplocation, destination, tripdate, amount, status) VALUES (?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([$cid, $pick, $dest, $tdate, $amt, $stat]);
        $saveid = $pdo->lastInsertId();
    }
    // WICHTIG: Absoluter Pfad für den Redirect
    header("location: /module/trip/order?edit=$saveid&success=1"); 
    exit;
}

// DATEN LADEN
$customers = $pdo->query("SELECT id, name FROM person ORDER BY name ASC")->fetchAll();

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM trip WHERE id=?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $customerid = $row['customerid']; $pickuplocation = $row['pickuplocation'];
        $destination = $row['destination']; $tripdate = date('Y-m-d\TH:i', strtotime($row['tripdate']));
        $amount = $row['amount']; $status = $row['status'];
    }
}
$trips = $pdo->query("SELECT t.*, p.name as customername FROM trip t LEFT JOIN person p ON t.customerid = p.id ORDER BY t.tripdate DESC LIMIT 10")->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Fahrtbestellung (Trip Order)</h2>
        <a href="module/trip/order" class="btn new">+ Neue Bestellung</a>
    </div>

    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
            <div>
                <label>Kunde</label>
                <select name="customerid" required>
                    <option value="">-- bitte wählen --</option>
                    <?php foreach($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($customerid==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Datum & Uhrzeit</label>
                <input type="datetime-local" name="tripdate" value="<?= $tripdate ?>" required>
            </div>
            <div>
                <label>Abholort</label>
                <input type="text" name="pickuplocation" value="<?= htmlspecialchars($pickuplocation) ?>" placeholder="Von...">
            </div>
            <div>
                <label>Zielort</label>
                <input type="text" name="destination" value="<?= htmlspecialchars($destination) ?>" placeholder="Nach...">
            </div>
            <div>
                <label>Preis (€)</label>
                <input type="number" step="0.01" name="amount" value="<?= $amount ?>">
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="pending" <?= ($status=='pending')?'selected':'' ?>>Offen (Pending)</option>
                    <option value="confirmed" <?= ($status=='confirmed')?'selected':'' ?>>Bestätigt</option>
                    <option value="completed" <?= ($status=='completed')?'selected':'' ?>>Erledigt</option>
                </select>
            </div>
        </div>
        <button type="submit" name="save" class="btn save" style="width:100%;">Bestellung speichern</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Zeit</th>
                <th>Kunde</th>
                <th>Route</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($trips as $t): ?>
            <tr onclick="window.location='/module/trip/order?edit=<?= $t['id'] ?>'" <?= ($id==$t['id'])?'style="background:#f0f7ff"':'' ?>>
                <td><?= date('d.m. H:i', strtotime($t['tripdate'])) ?></td>
                <td><strong><?= htmlspecialchars($t['customername'] ?? 'Unbekannt') ?></strong></td>
                <td><small><?= htmlspecialchars($pickuplocation) ?> → <?= htmlspecialchars($destination) ?></small></td>
                <td><span style="font-size:9px; border:1px solid #ddd; padding:2px 5px; border-radius:3px;"><?= strtoupper($t['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>