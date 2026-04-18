<?php
/**
 * module/event.php
 * Events verwalten: Formular + Journal mit Filter & Pagination
 */

if (!isset($pdo)) { die("Kein direkter Zugriff."); }

$message = '';
$redirect = false;

// --- 1. LOGIK: SPEICHERN & LÖSCHEN ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM event WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $redirect = "/?route=module/event&msg=deleted";
}

if (isset($_POST['save_event'])) {
    $id               = $_POST['id'] ?? null;
    $eventtypeid      = $_POST['eventtypeid'] ?: null;
    $name             = $_POST['name'] ?? '';
    $date             = $_POST['date'] ?: date('Y-m-d');
    $timefrom         = $_POST['timefrom'] ?: null;
    $timeto           = $_POST['timeto'] ?: null;
    $locationname     = $_POST['locationname'] ?? '';
    $locationmapslink = $_POST['locationmapslink'] ?? '';
    $note             = $_POST['note'] ?? '';

    $params = [$eventtypeid, $name, $date, $timefrom, $timeto, $locationname, $locationmapslink, $note];

    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE event SET eventtypeid=?, name=?, date=?, timefrom=?, timeto=?, locationname=?, locationmapslink=?, note=?, updateddate=NOW() WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/event&msg=updated";
    } else {
        // INSERT
        $sql = "INSERT INTO event (eventtypeid, name, date, timefrom, timeto, locationname, locationmapslink, note, createddate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute($params);
        $redirect = "/?route=module/event&msg=created";
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

$f_start = $_GET['start'] ?? '';
$f_end   = $_GET['end'] ?? '';
$f_type  = $_GET['typeid'] ?? '';
$f_q     = $_GET['q'] ?? '';

$where = ["1=1"];
$p_sql = [];

if ($f_start) { $where[] = "e.date >= ?"; $p_sql[] = $f_start; }
if ($f_end)   { $where[] = "e.date <= ?"; $p_sql[] = $f_end; }
if ($f_type)  { $where[] = "e.eventtypeid = ?"; $p_sql[] = $f_type; }

if ($f_q) { 
    $where[] = "(e.name LIKE ? OR e.locationname LIKE ? OR e.note LIKE ?)"; 
    $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%"; $p_sql[] = "%$f_q%";
}

$whereSql = implode(" AND ", $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM event e WHERE $whereSql");
$countStmt->execute($p_sql);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// --- 3. STAMMDATEN LADEN ---
// Hinweis: Geht davon aus, dass eine Tabelle 'eventtype' existiert
$eventTypes = [];
try {
    $eventTypes = $pdo->query("SELECT id, name FROM eventtype ORDER BY name ASC")->fetchAll();
} catch (Exception $e) { /* Fallback falls Tabelle fehlt */ }

$edit = null;
if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM event WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

$listStmt = $pdo->prepare("SELECT e.*, et.name as typename 
                           FROM event e 
                           LEFT JOIN eventtype et ON e.eventtypeid = et.id 
                           WHERE $whereSql ORDER BY e.date DESC, e.timefrom DESC LIMIT $limit OFFSET $offset");
$listStmt->execute($p_sql);
$list = $listStmt->fetchAll();
?>

<div class="card" style="margin-bottom: 25px; border-left: 5px solid #8b5cf6;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin:0;">📅 <?= $edit ? 'Event bearbeiten' : 'Neues Event planen' ?></h3>
    </div>
    
    <form method="post" action="/?route=module/event" class="form-container">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-row"><label>Bezeichnung</label><input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="Event Name" required></div>
                <div class="form-row"><label>Datum</label><input type="date" name="date" value="<?= $edit['date'] ?? date('Y-m-d') ?>" required></div>
                <div class="form-row">
                    <label>Zeit (Von/Bis)</label>
                    <div style="display:flex; gap:5px; flex-grow:1;">
                        <input type="time" name="timefrom" value="<?= $edit['timefrom'] ?? '' ?>">
                        <input type="time" name="timeto" value="<?= $edit['timeto'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-row"><label>Kategorie</label>
                    <select name="eventtypeid">
                        <option value="">-- wählen --</option>
                        <?php foreach($eventTypes as $et): ?>
                            <option value="<?= $et['id'] ?>" <?= ($edit['eventtypeid'] ?? '') == $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <div class="form-row"><label>Ort (Name)</label><input type="text" name="locationname" value="<?= htmlspecialchars($edit['locationname'] ?? '') ?>" placeholder="z.B. Hotel Sonne"></div>
                <div class="form-row"><label>Maps Link</label><input type="text" name="locationmapslink" value="<?= htmlspecialchars($edit['locationmapslink'] ?? '') ?>" placeholder="https://goo.gl/maps/..."></div>
                <div class="form-row" style="align-items: flex-start;"><label>Notiz</label><textarea name="note" rows="3"><?= htmlspecialchars($edit['note'] ?? '') ?></textarea></div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            <?php if($edit): ?>
                <a href="/?route=module/event&delete=<?= $edit['id'] ?>" class="btn-action delete-bg" onclick="return confirm('Dieses Event wirklich löschen?')">🗑 Löschen</a>
                <a href="/?route=module/event" class="btn-action cancel-bg">Abbrechen</a>
            <?php endif; ?>
            <button type="submit" name="save_event" class="btn save" style="padding: 10px 40px; font-weight: bold; margin: 0; background: #8b5cf6;">
                <?= $edit ? 'Aktualisieren' : 'Speichern' ?>
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0;">
    <form method="get" action="/" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="route" value="module/event">
        <div style="flex: 2; min-width: 200px;"><label class="filter-label">🔍 Suche</label><input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Name, Ort..." class="filter-input"></div>
        <div style="flex: 1; min-width: 130px;"><label class="filter-label">Von</label><input type="date" name="start" value="<?= htmlspecialchars($f_start) ?>" class="filter-input"></div>
        <div style="flex: 1; min-width: 130px;"><label class="filter-label">Bis</label><input type="date" name="end" value="<?= htmlspecialchars($f_end) ?>" class="filter-input"></div>
        <div style="display: flex; gap: 5px;">
            <button type="submit" class="btn save" style="height: 38px; background: #64748b;">Suchen</button>
            <a href="/?route=module/event" class="btn reset-btn" style="height: 38px; display:flex; align-items:center; text-decoration:none; background:#cbd5e1; color:#333; padding: 0 15px; border-radius:4px; font-size:13px;">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 100px;">Datum</th>
                <th>Event Details</th>
                <th>Ort</th>
                <th style="text-align:right; width: 80px;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $e): ?>
            <tr>
                <td style="font-size: 13px; color: #475569;">
                    <strong><?= date('d.m.Y', strtotime($e['date'])) ?></strong><br>
                    <span style="font-size: 11px;"><?= $e['timefrom'] ? substr($e['timefrom'],0,5) : '' ?> <?= $e['timeto'] ? '- '.substr($e['timeto'],0,5) : '' ?></span>
                </td>
                <td>
                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($e['name']) ?></div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                        <span class="badge"><?= htmlspecialchars($e['typename'] ?? 'Event') ?></span>
                        <?php if($e['note']): ?> <span style="color:#cbd5e1; margin: 0 4px;">|</span> <?= htmlspecialchars(mb_strimwidth($e['note'], 0, 50, "...")) ?><?php endif; ?>
                    </div>
                </td>
                <td>
                    <div style="font-size: 13px;"><?= htmlspecialchars($e['locationname'] ?: '-') ?></div>
                    <?php if($e['locationmapslink']): ?>
                        <a href="<?= htmlspecialchars($e['locationmapslink']) ?>" target="_blank" style="font-size: 11px; color: #3b82f6; text-decoration: none;">📍 In Maps öffnen</a>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <a href="/?route=module/event&edit=<?= $e['id'] ?>" class="action-link edit-link" title="Bearbeiten" style="font-size: 18px; text-decoration: none; margin-right: 10px;">✎</a>
                    <a href="/?route=module/event&delete=<?= $e['id'] ?>" 
                       class="action-link delete-link" 
                       title="Löschen" 
                       style="font-size: 18px; text-decoration: none; color: #dc2626;"
                       onclick="return confirm('Event wirklich löschen?')">🗑</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?><tr><td colspan="4" style="text-align:center; padding: 30px; color: #94a3b8;">Keine Events gefunden.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 8px;">
        <?php $pUrl = "/?route=module/event&q=".urlencode($f_q)."&start=".$f_start."&end=".$f_end."&p="; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="<?= $pUrl . $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* Übernommen und leicht angepasst aus deinem Transaction-Modul */
.form-container { display: flex; flex-direction: column; gap: 8px; }
.form-row { display: flex; align-items: center; min-height: 35px; margin-bottom: 5px; }
.form-row label { width: 130px; min-width: 130px; font-weight: 600; font-size: 13px; color: #475569; }
.form-row input, .form-row select, .form-row textarea { flex-grow: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; }
.btn.save { color: white; border: none; border-radius: 4px; cursor: pointer; transition: opacity 0.2s; }
.btn.save:hover { opacity: 0.9; }
.btn-action { text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; }
.delete-bg { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.cancel-bg { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.filter-label { display: block; font-size: 11px; margin-bottom: 4px; color: #64748b; text-transform: uppercase; font-weight: bold; }
.filter-input { width: 100%; height: 38px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table td, .data-table th { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.badge { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; font-size: 10px; font-weight: bold; }
.pagination .page-link { padding: 6px 14px; border: 1px solid #ddd; text-decoration: none; border-radius: 4px; color: #333; }
.pagination .active { background: #8b5cf6; color: #fff; border-color: #8b5cf6; }
</style>