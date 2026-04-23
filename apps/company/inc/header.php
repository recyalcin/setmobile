<?php
if (!isset($pdo)) { die("Zugriff verweigert."); }

// Da header.php und menuinc.php beide im Ordner /inc/ liegen, reicht __DIR__
require_once __DIR__ . '/menuinc.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>SM1 System</title>
    <base href="/">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body class="<?= isset($_SESSION['appuser']) ? 'logged-in' : 'logged-out' ?>">

<?php if (isset($_SESSION['appuser'])): ?>
<aside class="sidebar">
    <div class="sidebar-header">SM1 ADMIN</div>
    <nav>
        <?php renderSideMenu($menuTree, $currentRoute); ?>
        <a href="inc/logout.php" class="nav-link" style="color:#e74c3c; margin-top: auto; border-top: 1px solid #2c3e50;">✕ Abmelden</a>
    </nav>
</aside>
<?php endif; ?>

<div class="main">