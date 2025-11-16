
---

## api/generate.php

```php
<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
$email = $_POST['email'] ?? '';
$token = $_POST['token'] ?? '';
if ($email === '' || $token === '') { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND session_token = ?');
$stmt->execute([$email,$token]);
if (!$stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
$pdo->beginTransaction();
$k = $pdo->query('SELECT id,code FROM keys WHERE used = 0 ORDER BY RANDOM() LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$k) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'nokeys']); exit; }
$upd = $pdo->prepare('UPDATE keys SET used = 1 WHERE id = ?');
$upd->execute([$k['id']]);
$ins = $pdo->prepare('INSERT INTO issued (key_id,email,issued_at) VALUES (?,?,datetime("now"))');
$ins->execute([$k['id'],$email]);
$pdo->commit();
echo json_encode(['ok'=>true,'code'=>$k['code']]);