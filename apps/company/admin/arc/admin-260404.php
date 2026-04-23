<?php
// 1. FEHLER-REPORTING & LOGIK
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once __DIR__ . '/../../inc/session.php';

ensureCompanySessionStorage();

// Statistiken für SM1 laden
try {
    // Gesamtsumme berechnen
    $totalCash = $pdo->query("SELECT SUM(amount) FROM cash")->fetchColumn() ?: 0;
    
    // Anzahl Buchungen
    $countEntries = $pdo->query("SELECT COUNT(*) FROM cash")->fetchColumn();
    
    // Letzte 5 Buchungen für die Vorschau
    $recentItems = $pdo->query("SELECT c.*, ct.name as typename FROM cash c LEFT JOIN cashtype ct ON c.cashtypeid = ct.id ORDER BY c.id DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    $totalCash = $countEntries = 0;
    $recentItems = [];
}

include 'header.php'; 
?>

<div class="card">
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 20px; color: #111;">SM1 Finanz-Dashboard</h1>
        <p style="color: #666;">Willkommen zurück. Hier ist die Übersicht deiner aktuellen Cash-Daten.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <div style="background: #f8f9fa; border: 1px solid #eee; padding: 25px; border-radius: 8px; text-align: center;">
            <div style="font-size: 10px; text-transform: uppercase; color: #999; margin-bottom: 5px; letter-spacing: 1px;">Gesamtguthaben</div>
            <div style="font-size: 28px; font-weight: bold; color: <?= $totalCash >= 0 ? '#28a745' : '#dc3545' ?>;">
                <?= number_format((float)$totalCash, 2, ',', '.') ?> €
            </div>
        </div>

        <div style="background: #f8f9fa; border: 1px solid #eee; padding: 25px; border-radius: 8px; text-align: center;">
            <div style="font-size: 10px; text-transform: uppercase; color: #999; margin-bottom: 5px; letter-spacing: 1px;">Anzahl Buchungen</div>
            <div style="font-size: 28px; font-weight: bold; color: #111;"><?= (int)$countEntries ?></div>
        </div>

    </div>

    <h3 style="margin-bottom: 15px; font-size: 14px; color: #444;">Letzte Cash-Einträge</h3>
    <table style="font-size: 12px;">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Kategorie</th>
                <th>Beschreibung</th>
                <th style="text-align: right;">Betrag</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentItems)): ?>
                <tr><td colspan="4" style="text-align: center; color: #999; padding: 20px;">Noch keine Daten vorhanden.</td></tr>
            <?php else: ?>
                <?php foreach ($recentItems as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['entrydate'] ?? '') ?></td>
                    <td><span style="background: #eee; padding: 2px 6px; border-radius: 10px; font-size: 10px;"><?= htmlspecialchars($item['typename'] ?? 'Allgemein') ?></span></td>
                    <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                    <td style="text-align: right; font-weight: bold; color: <?= $item['amount'] < 0 ? '#dc3545' : '#28a745' ?>;">
                        <?= number_format((float)$item['amount'], 2, ',', '.') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <a href="cash.php" class="btn new" style="background: #111;">Alle Buchungen anzeigen</a>
    </div>
</div>

<?php include 'footer.php'; ?>
