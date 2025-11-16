# Project: Vista License System (2006 Windows Vista style)

## Project root

```
vista-license-system/
├─ index.php
├─ login.php
├─ admin.php
├─ logout.php
├─ api/
│  ├─ generate.php
│  ├─ auth.php
│  ├─ admin_action.php
├─ includes/
│  └─ db.php
├─ assets/
│  ├─ style.css
│  └─ vista.js
├─ data/
│  ├─ database.sqlite   (created automatically on first run)
│  └─ README.md
└─ README.md
```

---

Below are every file contents. Save each file exactly as shown (no extra comments). All PHP files assume the server supports PDO and SQLite.

---

## includes/db.php

```php
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
```
```

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
```
```

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
```
```

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
```
```

---

## index.php

```php
<?php
require_once __DIR__ . '/includes/db.php';
$session = $_COOKIE['session_token'] ?? '';
$user = null;
if ($session) {
    $stmt = $pdo->prepare('SELECT email,is_admin FROM users WHERE session_token = ?');
    $stmt->execute([$session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vista License System</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="vista">
<div class="window">
  <div class="titlebar">Vista License Center</div>
  <div class="content">
    <?php if (!$user): ?>
      <div class="public">You are not logged in. <a href="login.php" class="btn">Login / Register</a></div>
    <?php else: ?>
      <div class="userline">Logged in as <?php echo htmlspecialchars($user['email']); ?> <button class="btn" id="gen">Generate License</button> <a href="logout.php" class="btn">Logout</a></div>
      <?php if ($user['is_admin']): ?><a href="admin.php" class="btn">Admin</a><?php endif; ?>
      <div id="out" class="output"></div>
    <?php endif; ?>
  </div>
  <div class="footer">© 2025 Xyss. All rights reserved.</div>
</div>
<script src="assets/vista.js"></script>
<script>
const sess = '<?php echo $session; ?>';
const userEmail = '<?php echo $user ? addslashes($user['email']) : ''; ?>';
if (document.getElementById('gen')) document.getElementById('gen').addEventListener('click', async ()=>{
  if (!userEmail || !sess) return alert('login');
  const res = await fetch('api/generate.php', {method:'POST',body:new URLSearchParams({email:userEmail,token:sess})});
  const j = await res.json();
  document.getElementById('out').textContent = j.ok ? ('License: '+j.code) : ('Error: '+(j.error||'unknown'));
});
</script>
</body>
</html>
```
```

---

## login.php

```php
<?php
require_once __DIR__ . '/includes/db.php';
$session = $_COOKIE['session_token'] ?? '';
if ($session) {
    header('Location: index.php'); exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login - Vista</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="vista">
<div class="window">
  <div class="titlebar">Sign in / Register</div>
  <div class="content">
    <div class="form">
      <input id="email" placeholder="Email">
      <input id="password" type="password" placeholder="Password">
      <div class="row">
        <button class="btn" id="login">Login</button>
        <button class="btn" id="register">Register</button>
      </div>
    </div>
    <div id="msg"></div>
  </div>
  <div class="footer">© 2025 Xyss. All rights reserved.</div>
</div>
<script src="assets/vista.js"></script>
<script>
async function call(action, data){
  const res = await fetch('api/auth.php', {method:'POST',body:new URLSearchParams(Object.assign({action},data))});
  return res.json();
}
document.getElementById('login').addEventListener('click', async ()=>{
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const j = await call('login',{email,password});
  if (j.ok) { document.cookie = 'session_token='+j.token+';path=/'; location.href='index.php'; }
  else document.getElementById('msg').textContent = 'Login failed';
});
document.getElementById('register').addEventListener('click', async ()=>{
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const j = await call('register',{email,password});
  if (j.ok) document.getElementById('msg').textContent = 'Registered, please login';
  else document.getElementById('msg').textContent = 'Register failed';
});
</script>
</body>
</html>
```
```

---

## logout.php

```php
<?php
require_once __DIR__ . '/includes/db.php';
$token = $_COOKIE['session_token'] ?? '';
if ($token) {
    $pdo->prepare('UPDATE users SET session_token = NULL WHERE session_token = ?')->execute([$token]);
    setcookie('session_token', '', time()-3600, '/');
}
header('Location: index.php');
```
```

---

## admin.php

```php
<?php
require_once __DIR__ . '/includes/db.php';
$session = $_COOKIE['session_token'] ?? '';
$user = null;
if ($session) {
    $stmt = $pdo->prepare('SELECT email,is_admin FROM users WHERE session_token = ?');
    $stmt->execute([$session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin - Vista</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="vista">
<div class="window">
  <div class="titlebar">Database Console</div>
  <div class="content">
    <?php if (!$user || !$user['is_admin']): ?>
      <div>Access denied. <a href="index.php" class="btn">Home</a></div>
    <?php else: ?>
      <div>
        <div>
          <input id="secret" placeholder="Enter admin secret">
          <button class="btn" id="unlock">Unlock</button>
        </div>
        <div id="adminArea" style="display:none">
          <h3>Users</h3>
          <div id="users"></div>
          <h3>Keys</h3>
          <div id="keys"></div>
          <div>
            <input id="addKeyInput" placeholder="New key code">
            <button class="btn" id="addKeyBtn">Add Key</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div class="footer">© 2025 Xyss. All rights reserved.</div>
</div>
<script src="assets/vista.js"></script>
<script>
const sess = '<?php echo $session; ?>';
async function adminCall(action, body){
  body = body || {};
  body.action = action;
  body.token = sess;
  body.secret = document.getElementById('secret').value;
  const res = await fetch('api/admin_action.php',{method:'POST',body:new URLSearchParams(body)});
  return res.json();
}
document.getElementById('unlock').addEventListener('click', async ()=>{
  const j = await adminCall('list_users');
  if (!j.ok) return alert('bad secret or auth');
  document.getElementById('adminArea').style.display = 'block';
  renderUsers(j.users);
  const k = await adminCall('list_keys');
  renderKeys(k.keys);
});
function renderUsers(rows){
  const wrap = document.getElementById('users'); wrap.innerHTML='';
  rows.forEach(r=>{ const d = document.createElement('div'); d.textContent = r.email + ' admin:' + r.is_admin; const b = document.createElement('button'); b.textContent='Delete'; b.className='btn small'; b.onclick=async()=>{ await adminCall('delete_user',{id:r.id}); d.remove(); }; d.appendChild(b); wrap.appendChild(d); });
}
function renderKeys(rows){
  const wrap = document.getElementById('keys'); wrap.innerHTML='';
  rows.forEach(r=>{ const d = document.createElement('div'); d.textContent = r.code + ' used:' + r.used; const t = document.createElement('button'); t.textContent='Toggle Used'; t.className='btn small'; t.onclick=async()=>{ await adminCall('toggle_used',{id:r.id}); d.remove(); }; d.appendChild(t); const e = document.createElement('button'); e.textContent='Edit'; e.className='btn small'; e.onclick=async()=>{ const nc = prompt('New code', r.code); if (!nc) return; await adminCall('edit_key',{id:r.id,code:nc}); d.textContent = nc; }; d.appendChild(e); wrap.appendChild(d); });
}
document.getElementById('addKeyBtn').addEventListener('click', async ()=>{
  const v = document.getElementById('addKeyInput').value; if (!v) return; await adminCall('add_key',{code:v}); alert('added');
});
</script>
</body>
</html>
```
```

---

## assets/style.css

```css
body.vista{background:linear-gradient(#cfe8ff,#9fc7ff);font-family: Tahoma, Arial, sans-serif}
.window{width:600px;margin:40px auto;border:2px solid #00184a;border-radius:6px;box-shadow:0 8px 30px rgba(0,0,0,0.3);background:linear-gradient(#eef6ff,#d9eefe)}
.titlebar{background:linear-gradient(#1b4f9b,#0b2a66);color:#fff;padding:10px 12px;font-weight:bold;border-bottom:1px solid rgba(255,255,255,0.2);border-top-left-radius:4px;border-top-right-radius:4px}
.content{padding:18px}
.footer{background:#f1f1f1;border-top:1px solid #ccc;padding:8px 12px;border-bottom-left-radius:6px;border-bottom-right-radius:6px;font-size:12px;color:#333;text-align:right}
.btn{background:linear-gradient(#ffffff,#d0e7ff);border:1px solid #6fa8ff;padding:6px 10px;border-radius:4px;cursor:pointer;margin-left:6px}
.btn.small{padding:4px 6px;font-size:12px;margin-left:8px}
.input, input{display:block;padding:8px;border:1px solid #aaa;border-radius:3px;margin:6px 0}
.output{margin-top:12px;padding:10px;background:#fff;border:1px solid #a0bfe8;border-radius:4px}
.titlebar, .footer, .btn{font-family: Tahoma, Arial, sans-serif}
.form input{width:100%}
.row{display:flex;gap:8px}
```
```

---

## assets/vista.js

```javascript
// minimal helpers
async function post(url, data){
  const res = await fetch(url,{method:'POST',body: new URLSearchParams(data)});
  return res.json();
}
```

---

## README.md

```md
Setup: place project on PHP-enabled server. ensure write access to /data. On first run the system will fetch keys from the provided raw GitHub URL. Admin user pre-created for Adam.j.hafez2@gmail.com with password 'changeme' - change it immediately.
```

