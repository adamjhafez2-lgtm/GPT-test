
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