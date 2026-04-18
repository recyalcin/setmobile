<?php
// admin/schema/schemaadmin.php

// Tabellen erstellen
$pdo->exec("CREATE TABLE IF NOT EXISTS user (userid INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS menu (id INT AUTO_INCREMENT PRIMARY KEY, parentid INT DEFAULT 0, name VARCHAR(100), url VARCHAR(255), sortorder INT DEFAULT 0) ENGINE=InnoDB");

// Standard-Admin anlegen
if ($pdo->query("SELECT COUNT(*) FROM user")->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO user (username, password) VALUES (?, ?)")->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);
    echo "<small style='color:blue;'>! Admin-User angelegt.</small>";
}