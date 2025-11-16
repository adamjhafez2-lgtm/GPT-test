
---

## api/auth.php

```php
<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $pw = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id,password,is_admin FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($pw, $u['password'])) {
        $token = bin2hex(random_bytes(24));
        $upd = $pdo->prepare('UPDATE users SET session_token = ? WHERE id = ?');
        $upd->execute([$token, $u['id']]);
        echo json_encode(['ok' => true, 'token' => $token, 'is_admin' => (int)$u['is_admin']]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}
if ($action === 'register') {
    $email = $_POST['email'] ?? '';
    $pw = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) { echo json_encode(['ok'=>false]); exit; }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (email,password) VALUES (?,?)');
    try { $stmt->execute([$email,$hash]); echo json_encode(['ok'=>true]); } catch (Exception $e) { echo json_encode(['ok'=>false]); }
    exit;
}
if ($action === 'logout') {
    $token = $_POST['token'] ?? '';
    $upd = $pdo->prepare('UPDATE users SET session_token = NULL WHERE session_token = ?');
    $upd->execute([$token]);
    echo json_encode(['ok'=>true]);
    exit;
}