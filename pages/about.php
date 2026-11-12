<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
app_require_login();
$settings = load_settings();
$logo = site_logo_url();
$name = !empty($settings['site_name']) ? $settings['site_name'] : 'Medical Sales';
$about = !empty($settings['site_about']) ? $settings['site_about'] : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>عن الموقع</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#8b5cf6,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:900px;margin:auto;padding:16px}
.header{position:sticky;top:8px;z-index:850;display:flex;flex-direction:column;align-items:stretch}
.logo{font-size:18px;font-weight:800}
<?= app_nav_styles() ?>
.nav{display:flex;flex-wrap:nowrap;gap:4px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:28px;padding:6px}
.nav a{flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e2e8f0;text-decoration:none;padding:11px 6px;border-radius:22px;font-weight:800;font-size:12px;border:0;background:transparent}
.nav a.active{background:rgba(255,255,255,.22);color:#fff}
.card{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:32px;padding:28px 20px;text-align:center}
.card img{width:120px;height:120px;border-radius:50%;object-fit:cover;margin:0 auto 14px;display:block;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.card h1{margin:0 0 12px}
.card p{color:#e2e8f0;font-weight:700;line-height:1.9;white-space:pre-wrap}
</style>
</head>
<body>
<div class="container">
  <div class="header"><?php app_nav('about'); ?></div>
  <div class="card">
    <?php if ($logo !== ''): ?><img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><div style="font-size:64px">💊</div><?php endif; ?>
    <h1><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($about, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
</div>
</body>
</html>
