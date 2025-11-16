
---

## api/admin_action.php

```php
<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';
$secret = $_POST['secret'] ?? '';
$ADMIN_SECRET = 'xyss@verifyXEoNw3QxKMdg9L5apVkqj1uvJPBblmsRcOH6r7hAtnT04C82GFWDYfzSyiIUeZ';
if ($secret !== $ADMIN_SECRET) { echo json_encode(['ok'=>false,'error'=>'bad_secret']); exit; }
$stmt = $pdo->prepare('SELECT id,is_admin FROM users WHERE session_token = ?');
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || !$user['is_admin']) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
if ($action === 'list_users') {
    $rows = $pdo->query('SELECT id,email,is_admin,session_token FROM users')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'users'=>$rows]); exit;
}
if ($action === 'list_keys') {
    $rows = $pdo->query('SELECT id,code,used FROM keys')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'keys'=>$rows]); exit;
}
if ($action === 'delete_user') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'add_key') {
    $code = $_POST['code'] ?? '';
    $pdo->prepare('INSERT OR IGNORE INTO keys (code) VALUES (?)')->execute([$code]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'edit_key') {
    $id = (int)($_POST['id'] ?? 0);
    $code = $_POST['code'] ?? '';
    $pdo->prepare('UPDATE keys SET code = ? WHERE id = ?')->execute([$code,$id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'toggle_used') {
    $id = (int)($_POST['id'] ?? 0);
    $row = $pdo->prepare('SELECT used FROM keys WHERE id = ?')->execute([$id]);
    $used = $pdo->query('SELECT used FROM keys WHERE id = ' . $id)->fetchColumn();
    $new = $used ? 0 : 1;
    $pdo->prepare('UPDATE keys SET used = ? WHERE id = ?')->execute([$new,$id]);
    echo json_encode(['ok'=>true]); exit;
}
echo json_encode(['ok'=>false]);