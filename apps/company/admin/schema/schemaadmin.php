<?php
// admin/schema/schemaadmin.php

// Tabellen erstellen
$pdo->exec("CREATE TABLE IF NOT EXISTS appuser (userid SERIAL PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255))");
$pdo->exec("CREATE TABLE IF NOT EXISTS menu (id SERIAL PRIMARY KEY, parentid INT DEFAULT 0, name VARCHAR(100), url VARCHAR(255), sortorder INT DEFAULT 0)");

// Standard-Admin anlegen
if ($pdo->query("SELECT COUNT(*) FROM appuser")->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO appuser (username, password) VALUES (?, ?)")->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);
    echo "<small style='color:blue;'>! Admin-User angelegt.</small>";
}