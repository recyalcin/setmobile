<?php
// inc/menuinc.php

/**
 * Prüft rekursiv, ob ein Kind-Element (oder ein tiefere Ebene) aktiv ist.
 * Wird verwendet, um Eltern-Menüpunkte automatisch zu öffnen.
 */
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

/**
 * Wandelt die flache SQL-Ergebnisliste in eine Baumstruktur um.
 */
function buildMenuTree(array $items, $parentid = 0) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parentid'] == $parentid) {
            $children = buildMenuTree($items, $item['id']);
            if ($children) {
                $item['children'] = $children;
            }
            $branch[] = $item;
        }
    }
    return $branch;
}

/**
 * Generiert das HTML für das Seitenmenü.
 */
function renderSideMenu($items, $currentroute, $level = 0) {
    foreach ($items as $item) {
        $hasChildren = !empty($item['children']);
        $active = (trim((string)$currentroute, '/') === trim((string)$item['url'], '/')) ? 'active' : '';
        
        // Check, ob dieses Untermenü offen sein muss (weil ein Kind aktiv ist)
        $childActive = $hasChildren && isChildActive($item['children'], $currentroute);
        $displayStyle = $childActive ? 'block' : 'none';
        $openClass = $childActive ? 'open' : '';

        if ($hasChildren) {
            // Element mit Untermenü
            echo '<a href="javascript:void(0);" class="nav-link nav-parent '.$openClass.'">';
            echo htmlspecialchars((string)$item['name']) . '</a>';
            
            echo '<div class="submenu-container" style="display:'.$displayStyle.';">';
            renderSideMenu($item['children'], $currentroute, $level + 1);
            echo '</div>';
        } else {
            // Einfacher Link
            $padding = ($level * 15 + 20);
            echo '<a href="'.htmlspecialchars((string)$item['url']).'" class="nav-link '.$active.'" style="padding-left:'.$padding.'px;">';
            echo htmlspecialchars((string)$item['name']) . '</a>';
        }
    }
}

// 1. Daten aus PostgreSQL laden
// Felder: id, parentid, name, url, sortorder, note, createdat, updatedat
$stmt = $pdo->query("SELECT id, parentid, name, url, sortorder FROM menu ORDER BY sortorder ASC, name ASC");
$allMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Baumstruktur generieren
$menuTree = buildMenuTree($allMenuItems);

// 3. Aktuelle Route ermitteln (für Active-Status)
$currentRoute = $_GET['route'] ?? 'dashboard';