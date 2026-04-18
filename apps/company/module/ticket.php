<?php
/**
 * module/ticket.php - Fokus: Fix "Hoch"-Button Logik (Einen Platz höher, ignorieren wenn ganz oben)
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

// --- 0. LOOKUP: personid ermitteln ---
$sessionUserId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
$loggedInPersonId = 0;

if ($sessionUserId > 0) {
    $stmtUser = $pdo->prepare("SELECT personid FROM appuser WHERE id = ?");
    $stmtUser->execute([$sessionUserId]);
    $userRow = $stmtUser->fetch();
    if ($userRow) {
        $loggedInPersonId = (int)$userRow['personid'];
    }
}

$message = '';
$redirect = false;
$searchTerm = $_GET['search'] ?? '';
$edit = null;

// Sichtbarkeits-Logik für das Formular
$showForm = isset($_GET['edit']) || isset($_POST['duplicate_ticket']);

/**
 * HILFSFUNKTION: Normalisiert die Sortorder in der DB
 */
function normalizeSortOrder($pdo) {
    $sql = "SELECT t.id FROM ticket t
            LEFT JOIN ticketstatus ts ON t.ticketstatusid = ts.id
            ORDER BY 
                (CASE 
                    WHEN ts.name = 'Erledigt' THEN 6
                    WHEN t.dueat IS NOT NULL AND t.dueat < NOW() THEN 1
                    WHEN t.dueat IS NOT NULL AND t.dueat <= NOW() + INTERVAL '24 hours' THEN 2
                    WHEN t.scheduledat IS NOT NULL AND t.scheduledat < NOW() THEN 3
                    WHEN t.scheduledat IS NOT NULL AND t.scheduledat <= NOW() + INTERVAL '24 hours' THEN 4
                    ELSE 5
                END) ASC,
                t.resolvedat DESC,
                t.dueat ASC,
                t.scheduledat ASC,
                t.sortorder ASC, 
                t.createdat DESC";
    $all = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    $i = 1;
    foreach ($all as $tid) {
        $pdo->prepare("UPDATE ticket SET sortorder = ? WHERE id = ?")->execute([$i++, $tid]);
    }
}

// --- 1. LOGIK: AKTIONEN ---

// QUICK-RESOLVE (✔ Icon)
if (isset($_GET['resolve'])) {
    $resId = (int)$_GET['resolve'];
    $stmtStatus = $pdo->prepare("SELECT id FROM ticketstatus WHERE name = 'Erledigt' LIMIT 1");
    $stmtStatus->execute();
    $resolvedStatusId = $stmtStatus->fetchColumn();

    if ($resolvedStatusId) {
        $pdo->prepare("UPDATE ticket SET ticketstatusid = ?, resolvedat = NOW() WHERE id = ?")
            ->execute([$resolvedStatusId, $resId]);
    }
    normalizeSortOrder($pdo);
    $redirect = "/ticket&msg=resolved";
}

// MOVE LOGIK (Anfang, Hoch, Runter, Ende)
if (isset($_GET['move']) && isset($_GET['dir'])) {
    $moveId = (int)$_GET['move'];
    $dir = $_GET['dir'];
    $stmt = $pdo->prepare("SELECT sortorder FROM ticket WHERE id = ?");
    $stmt->execute([$moveId]);
    $currentOrder = (float)$stmt->fetchColumn();

    $changed = false;
    // Wenn schon an 1. Reihe (sortorder 1), dann ignorieren bei top/up
    if ($dir === 'top' && $currentOrder > 1) { 
        $pdo->prepare("UPDATE ticket SET sortorder = -1 WHERE id = ?")->execute([$moveId]); 
        $changed = true;
    } 
    elseif ($dir === 'up' && $currentOrder > 1) { 
        // Rückt durch -1.5 vor den vorherigen Datensatz (z.B. von 5 auf 3.5, landet nach Normalisierung auf 4)
        $pdo->prepare("UPDATE ticket SET sortorder = ? WHERE id = ?")->execute([$currentOrder - 1.5, $moveId]); 
        $changed = true;
    } 
    elseif ($dir === 'down') { 
        $pdo->prepare("UPDATE ticket SET sortorder = ? WHERE id = ?")->execute([$currentOrder + 1.5, $moveId]); 
        $changed = true;
    } 
    elseif ($dir === 'bottom') { 
        $pdo->prepare("UPDATE ticket SET sortorder = 999999 WHERE id = ?")->execute([$moveId]); 
        $changed = true;
    }
    
    if ($changed) { normalizeSortOrder($pdo); }
    $redirect = "/ticket";
}

// DUPLIZIEREN
if (isset($_POST['duplicate_ticket'])) {
    $edit = $_POST;
    $edit['id'] = '';
    if (!empty($edit['dueat'])) $edit['dueat'] = date('Y-m-d H:i:s', strtotime($edit['dueat']));
    if (!empty($edit['scheduledat'])) $edit['scheduledat'] = date('Y-m-d H:i:s', strtotime($edit['scheduledat']));
    if (!empty($edit['resolvedat'])) $edit['resolvedat'] = date('Y-m-d H:i:s', $_POST['resolvedat'] ? strtotime($_POST['resolvedat']) : time());
}

// SPEICHERN / UPDATE
if (isset($_POST['save_ticket'])) {
    $id = $_POST['id'] ?? null;
    
    $stmtCheck = $pdo->prepare("SELECT id FROM ticketstatus WHERE name = 'Erledigt' LIMIT 1");
    $stmtCheck->execute();
    $idErledigt = $stmtCheck->fetchColumn();

    if (!empty($_POST['ticketstatusid']) && (int)$_POST['ticketstatusid'] === (int)$idErledigt) {
        if (empty($_POST['resolvedat'])) { $_POST['resolvedat'] = date('d.m.Y H:i'); }
    }

    if (empty($id)) {
        $targetOrder = null;
        $compareDate = null;
        if (!empty($_POST['dueat'])) { $compareDate = date('Y-m-d H:i:s', strtotime($_POST['dueat'])); } 
        elseif (!empty($_POST['scheduledat'])) { $compareDate = date('Y-m-d H:i:s', strtotime($_POST['scheduledat'])); }

        if ($compareDate) {
            $stmtPos = $pdo->prepare("SELECT MIN(sortorder) FROM ticket WHERE COALESCE(dueat, scheduledat) > ?");
            $stmtPos->execute([$compareDate]);
            $targetOrder = $stmtPos->fetchColumn();
        } elseif (!empty($_POST['sortorder'])) {
            $targetOrder = (int)$_POST['sortorder'];
        }

        if ($targetOrder !== false && $targetOrder !== null) {
            $pdo->prepare("UPDATE ticket SET sortorder = sortorder + 1 WHERE sortorder >= ?")->execute([$targetOrder]);
            $_POST['sortorder'] = $targetOrder;
        } else {
            $maxOrder = $pdo->query("SELECT MAX(sortorder) FROM ticket")->fetchColumn();
            $_POST['sortorder'] = ($maxOrder !== false) ? (int)$maxOrder + 1 : 1;
        }
    }

    $fields = ['tickettypeid', 'ticketcategoryid', 'createdbyid', 'requesterid', 'assignedtoid', 'priorityid', 'ticketstatusid', 'sortorder', 'subject', 'description', 'dueat', 'scheduledat', 'resolvedat', 'closedat', 'note'];
    $params = [];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        if (in_array($f, ['dueat', 'scheduledat', 'resolvedat', 'closedat'])) {
            if (!empty($val)) {
                $timestamp = strtotime($val);
                $params[] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
            } else { $params[] = null; }
        } else { $params[] = ($val !== '') ? $val : null; }
    }

    if (!empty($id)) {
        $setClause = implode("=?, ", $fields) . "=?, updatedat=NOW()";
        $sql = "UPDATE ticket SET $setClause WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        normalizeSortOrder($pdo);
        $redirect = "/ticket&msg=updated";
    } else {
        $placeholders = str_repeat('?,', count($fields)) . 'NOW()';
        $colNames = implode(', ', $fields) . ', createdat';
        $sql = "INSERT INTO ticket ($colNames) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        normalizeSortOrder($pdo);
        $redirect = "/ticket&msg=created";
    }
}

// LÖSCHEN
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM ticket WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    normalizeSortOrder($pdo);
    $redirect = "/ticket&msg=deleted";
}

if ($redirect) { echo "<script>window.location.href='$redirect';</script>"; exit; }

// --- 2. STAMMDATEN ---
$ticketTypes    = $pdo->query("SELECT id, name FROM tickettype ORDER BY name ASC")->fetchAll();
$categories     = $pdo->query("SELECT id, name FROM ticketcategory ORDER BY name ASC")->fetchAll();
$ticketStatuses = $pdo->query("SELECT id, name FROM ticketstatus ORDER BY name ASC")->fetchAll();
$priorities     = $pdo->query("SELECT id, name FROM ticketpriority ORDER BY id ASC")->fetchAll();
$users          = $pdo->query("SELECT id, lastname, firstname FROM person ORDER BY lastname ASC")->fetchAll();

if ($edit === null) {
    $isNew = (isset($_GET['edit']) && $_GET['edit'] === 'new');
    if (isset($_GET['edit']) && !$isNew) {
        $stmt = $pdo->prepare("SELECT * FROM ticket WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
} else { $isNew = false; }

// --- 3. LISTE ---
$list = [];
if ($loggedInPersonId > 0) {
    $sql = "SELECT t.*, tp.name as priority_name, ts.name as status_name, tc.name as category_name, tt.name as type_name,
            p_creator.lastname as creator_lname, p_requester.lastname as requester_lname, p_assignee.lastname as assignee_lname
            FROM ticket t
            LEFT JOIN ticketpriority tp ON t.priorityid = tp.id
            LEFT JOIN ticketstatus ts ON t.ticketstatusid = ts.id
            LEFT JOIN ticketcategory tc ON t.ticketcategoryid = tc.id
            LEFT JOIN tickettype tt ON t.tickettypeid = tt.id
            LEFT JOIN person p_creator ON t.createdbyid = p_creator.id
            LEFT JOIN person p_requester ON t.requesterid = p_requester.id
            LEFT JOIN person p_assignee ON t.assignedtoid = p_assignee.id
            WHERE (t.createdbyid = ? OR t.requesterid = ? OR t.assignedtoid = ?)";
    
    $queryParams = [$loggedInPersonId, $loggedInPersonId, $loggedInPersonId];
    if (!empty($searchTerm)) {
        $sql .= " AND (t.subject LIKE ? OR t.description LIKE ? OR t.note LIKE ? OR t.id LIKE ?)";
        $like = "%$searchTerm%";
        $queryParams = array_merge($queryParams, [$like, $like, $like, $like]);
    }
    
    $sql .= " ORDER BY 
                (CASE 
                    WHEN ts.name = 'Erledigt' THEN 6
                    WHEN t.dueat IS NOT NULL AND t.dueat < NOW() THEN 1
                    WHEN t.dueat IS NOT NULL AND t.dueat <= NOW() + INTERVAL '24 hours' THEN 2
                    WHEN t.scheduledat IS NOT NULL AND t.scheduledat < NOW() THEN 3
                    WHEN t.scheduledat IS NOT NULL AND t.scheduledat <= NOW() + INTERVAL '24 hours' THEN 4
                    ELSE 5
                END) ASC,
                t.resolvedat DESC, t.dueat ASC, t.scheduledat ASC, t.sortorder ASC, t.createdat DESC LIMIT 100";
                
    $stmtList = $pdo->prepare($sql);
    $stmtList->execute($queryParams);
    $list = $stmtList->fetchAll();
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .datepicker-container { position: relative; display: flex; align-items: center; flex: 1; }
    .datepicker-container input { padding-right: 35px !important; width: 100%; }
    .calendar-icon { position: absolute; right: 10px; color: #64748b; cursor: pointer; z-index: 2; }
    .datepicker-container .flatpickr-calendar.static.open { top: 0 !important; left: 100% !important; margin-left: 15px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    .form-container { display: flex; flex-direction: column; gap: 8px; }
    .form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
    .form-row label { width: 110px; font-weight: bold; font-size: 12px; color: #475569; }
    .form-row input, .form-row select, .form-row textarea { flex: 1; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .btn-action { padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; }
    .neu-bg { background: #eff6ff; color: #3b82f6; border: 1px solid #3b82f6; }
    .dupli-bg { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .cancel-bg { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .data-table td, .data-table th { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .badge { padding: 3px 8px; background:#eff6ff; color:#1e40af; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .edit-link { font-size: 18px; text-decoration: none; color: #3b82f6; margin-left: 10px; }
    .resolve-link { font-size: 18px; text-decoration: none; color: #22c55e; }
    .sort-group { display: flex; flex-direction: column; gap: 1px; }
    .btn-sort { padding: 1px 3px; font-size: 8px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 2px; color: #64748b; text-decoration: none; text-transform: uppercase; width: 45px; text-align: center; }
    .btn-sort:hover { background: #3b82f6; color: white; border-color: #3b82f6; }
</style>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; <?= $showForm ? 'margin-bottom: 15px;' : '' ?>">
        <h3 style="margin:0;">🎫 Ticket</h3>
        <a href="/ticket&edit=new" class="btn-action neu-bg">+ Neues Ticket</a>
    </div>
    
    <?php if ($showForm): ?>
    <form method="post" action="/ticket" class="form-container">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
        <div style="display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 30px;">
            <div>
                <div class="form-row"><label>Betreff</label><input type="text" name="subject" value="<?= htmlspecialchars($edit['subject'] ?? '') ?>" placeholder="Titel..." required></div>
                <div class="form-row"><label>Typ / Kat.</label>
                    <select name="tickettypeid" style="width:48%; margin-right:2%;"><option value="">-- Typ --</option>
                        <?php foreach($ticketTypes as $tt): $sel = ($edit['tickettypeid'] ?? '') == $tt['id'] || ($isNew && $tt['name'] == 'Intern') ? 'selected' : ''; ?><option value="<?= $tt['id'] ?>" <?= $sel ?>><?= htmlspecialchars($tt['name']) ?></option><?php endforeach; ?>
                    </select>
                    <select name="ticketcategoryid" style="width:48%;"><option value="">-- Kat --</option>
                        <?php foreach($categories as $cat): $sel = ($edit['ticketcategoryid'] ?? '') == $cat['id'] || ($isNew && $cat['name'] == 'Büro') ? 'selected' : ''; ?><option value="<?= $cat['id'] ?>" <?= $sel ?>><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Anforderer</label>
                    <select name="requesterid"><option value="">-- wählen --</option>
                        <?php foreach($users as $u): $sel = ($edit['requesterid'] ?? '') == $u['id'] || ($isNew && $u['lastname'] == 'Sulak' && $u['firstname'] == 'Süleyman') ? 'selected' : ''; ?><option value="<?= $u['id'] ?>" <?= $sel ?>><?= htmlspecialchars($u['lastname'].", ".$u['firstname']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Zuständig</label>
                    <select name="assignedtoid"><option value="">-- wählen --</option>
                        <?php foreach($users as $u): $sel = ($edit['assignedtoid'] ?? '') == $u['id'] ? 'selected' : ''; ?><option value="<?= $u['id'] ?>" <?= $sel ?>><?= htmlspecialchars($u['lastname'].", ".$u['firstname']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Erstellt am</label><div class="datepicker-container"><input type="text" value="<?= (!empty($edit['createdat'])) ? date('d.m.Y H:i', strtotime($edit['createdat'])) : '' ?>" readonly style="background-color: #f8fafc; cursor: default;"><span class="calendar-icon" style="color: #cbd5e1; cursor: default;">📅</span></div></div>
                <div class="form-row"><label>Fällig am</label><div class="datepicker-container"><input type="text" class="dt-picker" id="dueat_picker" name="dueat" value="<?= (!empty($edit['dueat'])) ? date('d.m.Y H:i', strtotime($edit['dueat'])) : '' ?>"><span class="calendar-icon" onclick="document.getElementById('dueat_picker')._flatpickr.open()">📅</span></div></div>
                <div class="form-row"><label>Geplant am</label><div class="datepicker-container"><input type="text" class="dt-picker" id="scheduledat_picker" name="scheduledat" value="<?= (!empty($edit['scheduledat'])) ? date('d.m.Y H:i', strtotime($edit['scheduledat'])) : '' ?>"><span class="calendar-icon" onclick="document.getElementById('scheduledat_picker')._flatpickr.open()">📅</span></div></div>
                <div class="form-row"><label>Erledigt am</label><div class="datepicker-container"><input type="text" id="resolvedat_picker" name="resolvedat" value="<?= (!empty($edit['resolvedat'])) ? date('d.m.Y H:i', strtotime($edit['resolvedat'])) : '' ?>" readonly style="background-color: #f8fafc; cursor: default;"><span class="calendar-icon" style="color: #cbd5e1; cursor: default;">📅</span></div></div>
                <div class="form-row"><label>Status / Prio</label>
                    <select name="ticketstatusid" style="width:48%; margin-right:2%;"><option value="">-- Status --</option>
                        <?php foreach($ticketStatuses as $ts): $sel = (!$isNew && ($edit['ticketstatusid'] ?? '') == $ts['id']) ? 'selected' : ''; ?><option value="<?= $ts['id'] ?>" <?= $sel ?>><?= htmlspecialchars($ts['name']) ?></option><?php endforeach; ?></select>
                    <select name="priorityid" style="width:48%;"><option value="">-- Prio --</option>
                        <?php foreach($priorities as $p): $sel = (!$isNew && ($edit['priorityid'] ?? '') == $p['id']) ? 'selected' : ''; ?><option value="<?= $p['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-row"><label>SortOrder</label><input type="number" name="sortorder" value="<?= htmlspecialchars($edit['sortorder'] ?? '') ?>" placeholder="Position..."></div>
                <input type="hidden" name="createdbyid" value="<?= $edit['createdbyid'] ?? $loggedInPersonId ?>">
            </div>
            <div>
                <div class="form-row" style="align-items: flex-start;"><label style="margin-top:8px;">Beschreibung</label><textarea name="description" rows="6"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea></div>
                <div class="form-row" style="align-items: flex-start; margin-top:10px;"><label style="margin-top:8px;">Notiz</label><textarea name="note" rows="4"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <div><?php if(!empty($edit['id'])): ?><a href="/ticket&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Löschen?')">🗑 Löschen</a><?php endif; ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="/ticket" class="btn-action cancel-bg">Abbrechen</a>
                <?php if(!empty($edit['id'])): ?><button type="submit" name="duplicate_ticket" class="btn dupli-bg" style="cursor:pointer; border:none; padding:10px 20px; border-radius:4px;">📑 Duplizieren</button><?php endif; ?>
                <button type="submit" name="save_ticket" class="btn save-bg" style="cursor:pointer; border:none; padding:10px 40px; border-radius:4px; color:white; font-weight:bold; background:#3b82f6;"><?= (!empty($edit['id'])) ? '💾 Update' : '💾 Speichern' ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom: 25px;">
    <form method="get" action="/" style="display: flex; gap: 10px; width: 100%;">
        <input type="hidden" name="route" value="module/ticket">
        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Suche in Betreff, Beschreibung, Notiz oder ID..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn-action neu-bg" style="cursor:pointer;">🔍 Suchen</button>
        <?php if(!empty($searchTerm)): ?><a href="/ticket" class="btn-action cancel-bg">✖ Filter löschen</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead><tr><th style="width: 100px;">Ord</th><th>ID</th><th>Betreff / Typ</th><th>Anforderer</th><th>Zuständig</th><th>Termine</th><th>Status</th><th style="text-align:right;">Aktionen</th></tr></thead>
        <tbody>
            <?php foreach ($list as $t): ?>
            <tr>
                <td><div style="display: flex; align-items: center; gap: 10px;"><div class="sort-group"><a href="/ticket&move=<?= $t['id'] ?>&dir=top" class="btn-sort">Anfang</a><a href="/ticket&move=<?= $t['id'] ?>&dir=up" class="btn-sort">Hoch</a><a href="/ticket&move=<?= $t['id'] ?>&dir=down" class="btn-sort">Runter</a><a href="/ticket&move=<?= $t['id'] ?>&dir=bottom" class="btn-sort">Ende</a></div><span style="font-weight: bold; color: #1e40af;"><?= $t['sortorder'] ?? 0 ?></span></div></td>
                <td><small>#<?= $t['id'] ?></small></td>
                <td><strong><?= htmlspecialchars($t['subject']) ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($t['type_name'] ?? '-') ?></small></td>
                <td><small><?= htmlspecialchars($t['requester_lname'] ?? '-') ?></small></td>
                <td><small><strong><?= htmlspecialchars($t['assignee_lname'] ?? '-') ?></strong></small></td>
                <td><small style="color: #0369a1;">📅 G: <?= $t['scheduledat'] ? date('d.m.y H:i', strtotime($t['scheduledat'])) : '-' ?></small><br><small style="color: #64748b;">⌛ F: <?= $t['dueat'] ? date('d.m.y H:i', strtotime($t['dueat'])) : '-' ?></small></td>
                <td><span class="badge"><?= htmlspecialchars($t['status_name'] ?? 'Neu') ?></span></td>
                <td style="text-align:right;">
                    <a href="/ticket&resolve=<?= $t['id'] ?>" class="resolve-link" title="Als erledigt markieren" onclick="return confirm('Ticket als erledigt markieren?')">✔</a>
                    <a href="/ticket&edit=<?= $t['id'] ?>" class="edit-link">✎</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".dt-picker", { enableTime: true, dateFormat: "d.m.Y H:i", time_24hr: true, locale: "de", allowInput: true, static: true });
});
</script>