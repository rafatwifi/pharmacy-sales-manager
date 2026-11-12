<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>انتهى الاشتراك</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&display=swap" rel="stylesheet">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Cairo',Tahoma,sans-serif;background:#0f172a;color:#fff;padding:20px}
.card{max-width:420px;text-align:center;background:rgba(255,255,255,.08);border-radius:24px;padding:28px}
a{color:#f9a8d4;font-weight:800;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div style="font-size:64px">📦</div>
  <h1>انتهى الاشتراك</h1>
  <p>باقتك انتهت. الإدارة تقدر تجدد لك باقة جديدة.</p>
  <p><a href="<?= htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') ?>">خروج</a></p>
</div>
</body>
</html>
