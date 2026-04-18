<?php
/**
 * module/cashboxjournal.php
 * Sortierung: 
 * 1. Datum (ASC)
 * 2. Incomes zuerst (DESC Check)
 * 3. Erstellungszeitpunkt (ASC)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

// 1. ENTITY-TYPE IDs LADEN
$stmt = $pdo->query("SELECT id, tablename FROM transactionentitytype");
$entityTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$typeToTable = $entityTypes; 
$cashboxEntityTypeId = array_search('cashbox', $entityTypes);

// Buchungs-Arten für den Filter
$officialTypes = $pdo->query("SELECT id, name FROM officialtype ORDER BY id ASC")->fetchAll();

if (!$cashboxEntityTypeId) {
    echo "<div class='alert error'>Fehler: 'cashbox' Entitäts-Typ nicht gefunden.</div>";
    return;
}

// 2. FILTER-PARAMETER
$filter_cashbox      = $_GET['cashboxid'] ?? '';
$filter_officialtype = $_GET['officialtypeid'] ?? '';
$filter_date_from    = $_GET['from'] ?? date('Y-m-01');
$filter_date_to      = $_GET['to'] ?? date('Y-m-d');

// 3. NAMENS-LOOKUP
$raw_p = $pdo->query("SELECT id, CONCAT(lastname, ', ', firstname) as n FROM person")->fetchAll();
$raw_v = $pdo->query("SELECT id, licenseplate as n FROM vehicle")->fetchAll();
$raw_c = $pdo->query("SELECT id, name as n FROM company")->fetchAll();
$raw_x = $pdo->query("SELECT id, name as n FROM cashbox")->fetchAll();

$nameIndex = [
    'person'  => array_column($raw_p, 'n', 'id'),
    'vehicle' => array_column($raw_v, 'n', 'id'),
    'company' => array_column($raw_c, 'n', 'id'),
    'cashbox' => array_column($raw_x, 'n', 'id')
];
$cashboxNames = $nameIndex['cashbox'];

// 4. SQL FILTER BAUEN
$offSql = "";
$offParams = [];
if (!empty($filter_officialtype)) {
    $offSql = " AND t.officialtypeid = ? ";
    $offParams[] = $filter_officialtype;
}

// 5. DATEN LADEN
$journal = [];
$startBalance = 0;

if (!empty($filter_cashbox)) {
    // Vortrag berechnen (Saldo vor dem Startdatum)
    $sqlStart = "SELECT 
        SUM(CASE WHEN totypeid = ? AND toid = ? THEN amount ELSE 0 END) - 
        SUM(CASE WHEN fromtypeid = ? AND fromid = ? THEN amount ELSE 0 END) 
        FROM transaction t WHERE date < ?" . $offSql;
    
    $stmtStart = $pdo->prepare($sqlStart);
    $paramsStart = array_merge([$cashboxEntityTypeId, $filter_cashbox, $cashboxEntityTypeId, $filter_cashbox, $filter_date_from], $offParams);
    $stmtStart->execute($paramsStart);
    $startBalance = (float)$stmtStart->fetchColumn();

    // Journal-Daten mit spezialisierter Sortierung
    $sqlLog = "SELECT t.*, tt.name as category 
               FROM transaction t
               LEFT JOIN transactiontype tt ON t.transactiontypeid = tt.id
               WHERE ( (t.fromtypeid = ? AND t.fromid = ?) OR (t.totypeid = ? AND t.toid = ?) )
               AND t.date BETWEEN ? AND ?" . $offSql . "
               ORDER BY 
                  t.date ASC, 
                  (t.totypeid = ? AND t.toid = ?) DESC, 
                  t.createddate ASC";
    
    $stmtLog = $pdo->prepare($sqlLog);
    $paramsLog = array_merge([
        $cashboxEntityTypeId, $filter_cashbox, 
        $cashboxEntityTypeId, $filter_cashbox, 
        $filter_date_from, $filter_date_to
    ], $offParams, [$cashboxEntityTypeId, $filter_cashbox]);
    
    $stmtLog->execute($paramsLog);
    $journal = $stmtLog->fetchAll();
} else {
    // Übersicht über alle Kassen
    $sqlLog = "SELECT t.*, tt.name as category 
               FROM transaction t
               LEFT JOIN transactiontype tt ON t.transactiontypeid = tt.id
               WHERE (t.fromtypeid = ? OR t.totypeid = ?)
               AND t.date BETWEEN ? AND ?" . $offSql . "
               ORDER BY t.date ASC, t.createddate ASC LIMIT 500";
    
    $stmtLog = $pdo->prepare($sqlLog);
    $paramsLog = array_merge([$cashboxEntityTypeId, $cashboxEntityTypeId, $filter_date_from, $filter_date_to], $offParams);
    $stmtLog->execute($paramsLog);
    $journal = $stmtLog->fetchAll();
}
?>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin:0; margin-bottom: 15px;">🔍 Kassenbuch Filter</h3>
    <form method="get" action="/" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/cashboxjournal">
        
        <div style="flex: 1.5; min-width: 180px;">
            <label class="filter-label">Kasse</label>
            <select name="cashboxid" class="filter-input">
                <option value="">-- Alle --</option>
                <?php foreach ($raw_x as $cb): ?>
                    <option value="<?= $cb['id'] ?>" <?= ($filter_cashbox == $cb['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cb['n'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label class="filter-label">Buchungs-Art</label>
            <select name="officialtypeid" class="filter-input">
                <option value="">-- Alle --</option>
                <?php foreach ($officialTypes as $ot): ?>
                    <option value="<?= $ot['id'] ?>" <?= ($filter_officialtype == $ot['id']) ? 'selected' : '' ?>><?= htmlspecialchars($ot['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 0.8; min-width: 120px;">
            <label class="filter-label">Von</label>
            <input type="date" name="from" value="<?= $filter_date_from ?>" class="filter-input">
        </div>

        <div style="flex: 0.8; min-width: 120px;">
            <label class="filter-label">Bis</label>
            <input type="date" name="to" value="<?= $filter_date_to ?>" class="filter-input">
        </div>

        <div style="display: flex; gap: 5px; height: 38px;">
            <button type="submit" class="btn save" style="height:38px; padding: 0 15px;">Anzeigen</button>
            <a href="/cashboxjournal" class="btn reset-btn" style="height:38px; display:flex; align-items:center;">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">
            <?= $filter_cashbox ? '📖 Journal: ' . htmlspecialchars($cashboxNames[$filter_cashbox] ?? '') : '📋 Kassenbewegungen' ?>
        </h3>
        <?php if ($filter_cashbox): ?>
            <div class="balance-box">
                <small>Vortrag (Stand <?= date('d.m.Y', strtotime($filter_date_from)) ?>):</small>
                <div style="font-size: 1.2em;"><strong><?= number_format($startBalance, 2, ',', '.') ?> €</strong></div>
            </div>
        <?php endif; ?>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 90px;">Datum</th>
                <th>Details (Partner — Kategorie — Zweck)</th>
                <th style="text-align:right; width: 100px;">Einnahme</th>
                <th style="text-align:right; width: 100px;">Ausgabe</th>
                <?php if ($filter_cashbox): ?>
                    <th style="text-align:right; width: 120px; background: #f8fafc;">Saldo</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $currentBalance = $startBalance;
            $totalIn = 0; $totalOut = 0;

            foreach ($journal as $t): 
                $isIn = ($t['totypeid'] == $cashboxEntityTypeId && (!$filter_cashbox || $t['toid'] == $filter_cashbox));
                $amount = (float)$t['amount'];
                
                $valIn = $isIn ? $amount : 0;
                $valOut = !$isIn ? $amount : 0;
                
                $totalIn += $valIn; 
                $totalOut += $valOut;
                $currentBalance += ($valIn - $valOut);

                // Partner bestimmen
                if ($filter_cashbox) {
                    $pType = $isIn ? $t['fromtypeid'] : $t['totypeid'];
                    $pId   = $isIn ? $t['fromid'] : $t['toid'];
                    $pTab  = $typeToTable[$pType] ?? '';
                    $partner = $nameIndex[$pTab][$pId] ?? "ID:".$pId;
                } else {
                    $fT = $typeToTable[$t['fromtypeid']] ?? ''; $tT = $typeToTable[$t['totypeid']] ?? '';
                    $partner = ($nameIndex[$fT][$t['fromid']] ?? 'ID:'.$t['fromid']) . ' ➔ ' . ($nameIndex[$tT][$t['toid']] ?? 'ID:'.$t['toid']);
                }
            ?>
            <tr <?= ($t['officialtypeid'] == 2) ? 'style="background-color: #fdfdfd; opacity: 0.8;"' : '' ?>>
                <td style="font-size: 13px; color: #666;"><?= date('d.m.Y', strtotime($t['date'])) ?></td>
                <td>
                    <div style="font-size: 14px;">
                        <a href="/transaction&edit=<?= $t['id'] ?>" 
                           target="_blank" 
                           title="Buchung bearbeiten" 
                           style="text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: 5px;">
                            
                            <span style="font-weight: 600; color: #2563eb; border-bottom: 1px dotted #2563eb;">
                                <?= htmlspecialchars($partner ?? '') ?>
                            </span>
                            <small style="color: #94a3b8; font-size: 10px;">✎</small>
                        </a>

                        <span style="color: #cbd5e1; margin: 0 4px;">—</span>
                        <small style="color: #64748b;"><?= htmlspecialchars($t['category'] ?? 'Allgemein') ?></small>
                        <span style="color: #cbd5e1; margin: 0 4px;">—</span>
                        <span style="color: #475569; font-size: 13px;"><?= htmlspecialchars($t['description'] ?? '-') ?></span>
                    </div>
                </td>
                <td style="text-align:right; color: #059669; font-weight: 600;">
                    <?= $valIn > 0 ? number_format($valIn, 2, ',', '.') . ' €' : '' ?>
                </td>
                <td style="text-align:right; color: #dc2626; font-weight: 600;">
                    <?= $valOut > 0 ? number_format($valOut, 2, ',', '.') . ' €' : '' ?>
                </td>
                <?php if ($filter_cashbox): ?>
                    <td style="text-align:right; font-weight: bold; background: #f8fafc; border-left: 1px solid #e2e8f0;">
                        <?= number_format($currentBalance, 2, ',', '.') ?> €
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #334155; color: white;">
                <td colspan="2" style="text-align: right; font-weight: bold; padding-right: 20px;">Summen (Zeitraum):</td>
                <td style="text-align:right; font-weight: bold; color: #34d399;"><?= number_format($totalIn, 2, ',', '.') ?> €</td>
                <td style="text-align:right; font-weight: bold; color: #f87171;"><?= number_format($totalOut, 2, ',', '.') ?> €</td>
                <?php if ($filter_cashbox): ?> <td style="background: #1e293b;"></td> <?php endif; ?>
            </tr>
            <tr style="background: #1e293b; color: white;">
                <td colspan="2" style="text-align: right; font-weight: bold; padding-right: 20px;">Ergebnis (Netto):</td>
                <td colspan="2" style="text-align: center; font-weight: bold; color: <?= ($totalIn-$totalOut >= 0) ? '#34d399':'#f87171' ?>;">
                    <?= number_format($totalIn - $totalOut, 2, ',', '.') ?> €
                </td>
                <?php if ($filter_cashbox): ?> <td style="background: #0f172a;"></td> <?php endif; ?>
            </tr>
            <?php if ($filter_cashbox): ?>
            <tr style="background: #0f172a; color: white;">
                <td colspan="4" style="text-align: right; font-weight: bold; padding: 15px;">Finaler Endbestand (inkl. Vortrag):</td>
                <td style="text-align:right; font-weight: bold; font-size: 1.1em; color: #fbbf24;"><?= number_format($currentBalance, 2, ',', '.') ?> €</td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<style>
.filter-label { display: block; font-size: 11px; margin-bottom: 5px; color: #64748b; text-transform: uppercase; font-weight: bold; }
.filter-input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
.reset-btn { background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; padding: 0 12px; text-decoration: none; border-radius: 4px; font-size: 13px; }
.balance-box { background: #f1f5f9; padding: 8px 15px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: right; line-height: 1.2; }
.data-table tfoot td { padding: 10px; border-top: 1px solid #475569; }
.data-table a:hover span { color: #1d4ed8 !important; border-bottom-style: solid !important; }
</style>