
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