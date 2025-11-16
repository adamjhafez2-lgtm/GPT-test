
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