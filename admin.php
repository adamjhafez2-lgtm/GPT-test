
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