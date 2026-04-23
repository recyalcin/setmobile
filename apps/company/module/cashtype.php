<?php
// 1. FEHLER-REPORTING & LOGIK
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once __DIR__ . '/../inc/session.php';

ensureCompanySessionStorage();

// Variablen initialisieren
$id = $_GET['edit'] ?? $_POST['id'] ?? '';
$name = '';
$mode = 'new';

// 2. LOGIK: Speichern
if (isset($_POST['speichern'])) {
    $p_name = $_POST['name'] ?? '';
    
    if (!empty($_POST['id'])) {
        // UPDATE
        $sql = "UPDATE cashtype SET name = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$p_name, $_POST['id']]);
        $save_id = $_POST['id'];
    } else {
        // INSERT
        $sql = "INSERT INTO cashtype (name) VALUES (?)";
        $pdo->prepare($sql)->execute([$p_name]);
        $save_id = $pdo->lastInsertId();
    }
    header("Location: cashtype.php?edit=" . $save_id . "&success=1");
    exit;
}

// 3. LOGIK: Löschen
if (isset($_POST['delete']) && !empty($id)) {
    try {
        $pdo->prepare("DELETE FROM cashtype WHERE id = ?")->execute([$id]);
        header("Location: cashtype.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error = "Löschen nicht möglich: Diese Kategorie wird noch in 'Cash' verwendet.";
    }
}

// 4. DATEN LADEN
if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM cashtype WHERE id = ?");
    $stmt->execute([(int)$id]);
    if ($row = $stmt->fetch()) {
        $name = $row['name'];
        $mode = 'edit';
    }
}

$list = $pdo->query("SELECT * FROM cashtype ORDER BY id DESC")->fetchAll();

// 5. LAYOUT STARTEN
include 'header.php'; 
?>

<div class="card">
    <h2>Cash Kategorien verwalten</h2>

    <?php if (isset($_GET['success'])): ?>
        <div id="popup" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            Kategorie erfolgreich gespeichert!
        </div>
        <script>setTimeout(() => { document.getElementById('popup').style.display='none'; }, 3000);</script>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
        
        <div class="form-grid">
            <div style="grid-column: span 2;">
                <label>Name der Kategorie (z.B. Gehalt, Miete, Hobby)</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required autofocus>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" name="speichern" class="btn save">Speichern</button>
            <?php if ($mode == 'edit'): ?>
                <button type="submit" name="delete" class="btn delete" onclick="return confirm('Soll diese Kategorie wirklich gelöscht werden?')">Löschen</button>
                <a href="cashtype.php" class="btn new">Abbrechen / Neu</a>
            <?php endif; ?>
        </div>
    </form>

    <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>Kategorie Name</th>
                <th style="width: 150px;">Zeit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $item): ?>
                <tr onclick="window.location='?edit=<?= $item['id'] ?>'" 
                    style="<?= ($id == $item['id']) ? 'background: #f0f7ff; border-left: 3px solid #28a745;' : '' ?>">
                    <td><?= $item['id'] ?></td>
                    <td><strong><?= htmlspecialchars($item['name'] ?? '') ?></strong></td>
                    <td class="ts">
                        u: <?= date('d.m. H:i', strtotime($item['update'] ?? 'now')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
