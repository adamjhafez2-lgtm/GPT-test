<?php
$root = __DIR__ . '/..';
$dbFile = $root . '/data/database.sqlite';
if (!file_exists(dirname($dbFile))) mkdir(dirname($dbFile), 0755, true);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT UNIQUE, password TEXT, is_admin INTEGER DEFAULT 0, session_token TEXT)');
$pdo->exec('CREATE TABLE IF NOT EXISTS keys (id INTEGER PRIMARY KEY, code TEXT UNIQUE, used INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE IF NOT EXISTS issued (id INTEGER PRIMARY KEY, key_id INTEGER, email TEXT, issued_at TEXT, FOREIGN KEY(key_id) REFERENCES keys(id))');
$adminEmail = 'Adam.j.hafez2@gmail.com';
$adminCheck = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$adminCheck->execute([$adminEmail]);
if (!$adminCheck->fetch()) {
    $pw = password_hash('changeme', PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (email, password, is_admin) VALUES (?,?,1)');
    $insert->execute([$adminEmail, $pw]);
}
function ensureKeysLoaded(PDO $pdo){
    $count = $pdo->query('SELECT COUNT(*) FROM keys')->fetchColumn();
    if ($count == 0) {
        $raw = @file_get_contents('https://raw.githubusercontent.com/adamjhafez2-lgtm/agnes-key-system/main/Secretkey');
        if ($raw === false) return;
        $lines = preg_split('/\r?\n/', trim($raw));
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO keys (code) VALUES (?)');
        foreach ($lines as $l) if (trim($l) !== '') $stmt->execute([trim($l)]);
    }
}
ensureKeysLoaded($pdo);