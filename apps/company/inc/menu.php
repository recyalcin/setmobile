<?php
// inc/menu.php

// NEU: Prüft rekursiv, ob ein Kind-Element aktiv ist
function isChildActive($children, $currentRoute) {
    foreach ($children as $child) {
        if (trim((string)$child['url'], '/') === trim((string)$currentRoute, '/')) {
            return true;
        }
        if (!empty($child['children'])) {
            if (isChildActive($child['children'], $currentRoute)) return true;
        }
    }
    return false;
}

function buildMenuTree(array $items, $parentid = 0) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parentid'] == $parentid) {
            $children = buildMenuTree($items, $item['id']);
            if ($children) { $item['children'] = $children; }
            $branch[] = $item;
        }
    }
    return $branch;
}

function renderSideMenu($items, $currentroute, $level = 0) {
    foreach ($items as $item) {
        $hasChildren = !empty($item['children']);
        $active = (trim((string)$currentroute, '/') === trim((string)$item['url'], '/')) ? 'active' : '';
        
        // NEU: Check ob dieses Menü offen sein muss
        $childActive = $hasChildren && isChildActive($item['children'], $currentroute);
        $displayStyle = $childActive ? 'block' : 'none';
        $openClass = $childActive ? 'open' : '';

        if ($hasChildren) {
            // Eltern-Element bekommt 'open', wenn ein Kind aktiv ist
            echo '<a href="javascript:void(0);" class="nav-link nav-parent '.$openClass.'">';
            echo htmlspecialchars((string)$item['name']) . '</a>';
            
            // Container bekommt 'display:block', wenn ein Kind aktiv ist
            echo '<div class="submenu-container" style="display:'.$displayStyle.';">';
            renderSideMenu($item['children'], $currentroute, $level + 1);
            echo '</div>';
        } else {
            $padding = ($level * 15 + 20);
            echo '<a href="'.htmlspecialchars((string)$item['url']).'" class="nav-link '.$active.'" style="padding-left:'.$padding.'px;">';
            echo htmlspecialchars((string)$item['name']) . '</a>';
        }
    }
}

// Statik menü (veritabanı bağımsız)
$currentRoute = $_GET['route'] ?? 'module/dashboard';

$menuTree = [
    ['id'=>1,  'name'=>'🏠 Dashboard',        'url'=>'module/dashboard',   'parentid'=>0],
    ['id'=>2,  'name'=>'👤 Personen',          'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>21, 'name'=>'Personenliste',    'url'=>'module/person',         'parentid'=>2],
        ['id'=>22, 'name'=>'Personentypen',    'url'=>'module/persontype',     'parentid'=>2],
    ]],
    ['id'=>3,  'name'=>'🚗 Fahrer',            'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>31, 'name'=>'Fahrerliste',      'url'=>'module/driver',         'parentid'=>3],
        ['id'=>32, 'name'=>'Fahrertypen',      'url'=>'module/drivertype',     'parentid'=>3],
        ['id'=>33, 'name'=>'Performance',      'url'=>'module/performance',    'parentid'=>3],
        ['id'=>34, 'name'=>'Aktivitäten',      'url'=>'module/driveractivity', 'parentid'=>3],
    ]],
    ['id'=>4,  'name'=>'🧑‍💼 Mitarbeiter',    'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>41, 'name'=>'Mitarbeiterliste', 'url'=>'module/employee',       'parentid'=>4],
        ['id'=>42, 'name'=>'Mitarbeitertypen', 'url'=>'module/employeetype',   'parentid'=>4],
        ['id'=>43, 'name'=>'Stellenbezeichn.', 'url'=>'module/jobtitle',       'parentid'=>4],
        ['id'=>44, 'name'=>'Abteilungen',      'url'=>'module/department',     'parentid'=>4],
    ]],
    ['id'=>5,  'name'=>'🚙 Fahrzeuge',         'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>51, 'name'=>'Fahrzeugliste',    'url'=>'module/vehicle',        'parentid'=>5],
        ['id'=>52, 'name'=>'Fahrzeugtypen',    'url'=>'module/vehicletype',    'parentid'=>5],
        ['id'=>53, 'name'=>'Standort',         'url'=>'module/vehiclelocation','parentid'=>5],
        ['id'=>54, 'name'=>'Wartung',          'url'=>'module/vehicleservice', 'parentid'=>5],
    ]],
    ['id'=>6,  'name'=>'🗺️ Fahrten',          'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>61, 'name'=>'Fahrtenliste',     'url'=>'module/trip',           'parentid'=>6],
        ['id'=>62, 'name'=>'Status',           'url'=>'module/tripstatus',     'parentid'=>6],
        ['id'=>63, 'name'=>'Quellen',          'url'=>'module/tripsource',     'parentid'=>6],
        ['id'=>64, 'name'=>'Fahrttypen',       'url'=>'module/triptype',       'parentid'=>6],
    ]],
    ['id'=>7,  'name'=>'⏱️ Arbeitszeiten',    'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>71, 'name'=>'Übersicht',        'url'=>'module/workinghours',   'parentid'=>7],
        ['id'=>72, 'name'=>'Berechnung',       'url'=>'module/workinghourscalc','parentid'=>7],
        ['id'=>73, 'name'=>'Schichten',        'url'=>'module/workinghoursschicht','parentid'=>7],
    ]],
    ['id'=>8,  'name'=>'💰 Finanzen',          'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>81, 'name'=>'Transaktionen',    'url'=>'module/transaction',    'parentid'=>8],
        ['id'=>82, 'name'=>'Kasse',            'url'=>'module/cashbox',        'parentid'=>8],
        ['id'=>83, 'name'=>'Kassenjournal',    'url'=>'module/cashboxjournal', 'parentid'=>8],
        ['id'=>84, 'name'=>'Bar-Buchungen',    'url'=>'module/cash',           'parentid'=>8],
        ['id'=>85, 'name'=>'Bankkonten',       'url'=>'module/bankaccount',    'parentid'=>8],
    ]],
    ['id'=>9,  'name'=>'🎫 Tickets',           'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>91, 'name'=>'Ticketliste',      'url'=>'module/ticket',         'parentid'=>9],
        ['id'=>92, 'name'=>'Kategorien',       'url'=>'module/ticketcategory', 'parentid'=>9],
        ['id'=>93, 'name'=>'Status',           'url'=>'module/ticketstatus',   'parentid'=>9],
        ['id'=>94, 'name'=>'Tickettypen',      'url'=>'module/tickettype',     'parentid'=>9],
    ]],
    ['id'=>10, 'name'=>'📥 CSV Import',        'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>101,'name'=>'Performance',      'url'=>'module/csvperformance', 'parentid'=>10],
        ['id'=>102,'name'=>'Arbeitszeiten',    'url'=>'module/csvarbeitszeit', 'parentid'=>10],
        ['id'=>103,'name'=>'Bolt Fahrten',     'url'=>'module/csvtripbolt',    'parentid'=>10],
        ['id'=>104,'name'=>'Uber Fahrten',     'url'=>'module/csvtripuber',    'parentid'=>10],
        ['id'=>105,'name'=>'Transaktionen',    'url'=>'module/csvtransaction', 'parentid'=>10],
    ]],
    ['id'=>11, 'name'=>'⚙️ Einstellungen',     'url'=>null, 'parentid'=>0, 'children'=>[
        ['id'=>111,'name'=>'Zahlungsarten',    'url'=>'module/paymenttype',    'parentid'=>11],
        ['id'=>112,'name'=>'Prioritäten',      'url'=>'module/priority',       'parentid'=>11],
        ['id'=>113,'name'=>'Benutzer',         'url'=>'module/usermanager',    'parentid'=>11],
        ['id'=>114,'name'=>'Unternehmen',      'url'=>'module/company',        'parentid'=>11],
        ['id'=>115,'name'=>'Schema Update',    'url'=>'admin/schema',          'parentid'=>11],
    ]],
];