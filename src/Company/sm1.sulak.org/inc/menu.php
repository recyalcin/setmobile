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

// Daten laden
$stmt = $pdo->query("SELECT * FROM menu ORDER BY sortorder ASC, name ASC");
$menuTree = buildMenuTree($stmt->fetchAll(PDO::FETCH_ASSOC));
$currentRoute = $_GET['route'] ?? 'dashboard';