<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$user = app_require_login();
$settings = load_settings();
$packages = load_packages();
$pkg = !empty($user['package_id']) ? package_by_id($user['package_id']) : null;
$pkgName = $pkg ? $pkg['name'] : 'بدون باقة';
$expText = !empty($user['expires_at']) ? $user['expires_at'] : 'غير محدد';
$expTs = !empty($user['expires_at']) ? strtotime($user['expires_at']) : 0;
$daysLeft = $expTs ? (int)floor(($expTs - time()) / 86400) : 0;
$expired = ($expTs > 0 && $expTs < time());
if ($daysLeft < 0) $daysLeft = 0;
if ($expired) {
    $mood = 'sad';
    $emojis = array('😢','😔','💔','🥺');
    $statusTxt = 'انتهى الاشتراك — جدّد عبر زين كاش';
} elseif ($daysLeft <= 7) {
    $mood = 'warn';
    $emojis = array('😰','⏳','🙏','⚠️');
    $statusTxt = 'اشتراكك قرب ينتهي';
} elseif ($daysLeft <= 30) {
    $mood = 'ok';
    $emojis = array('😊','✨','💼','🙂');
    $statusTxt = 'اشتراكك شغال';
} else {
    $mood = 'happy';
    $emojis = array('😄','💚','🎉','🚀');
    $statusTxt = 'اشتراكك ممتاز ونشط';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && isset($_POST['renew'])) {
    $pkgId = isset($_POST['package_id']) ? $_POST['package_id'] : (isset($user['package_id']) ? $user['package_id'] : '');
    $chosen = package_by_id($pkgId);
    if ($chosen) {
        $rows = load_renewals();
        $rows[] = array(
            'id' => 'r' . uniqid(),
            'user_id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'package_id' => $chosen['id'],
            'package_name' => $chosen['name'],
            'amount' => isset($chosen['price']) ? (int)$chosen['price'] : 0,
            'pay_method' => 'zaincash',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i')
        );
        save_renewals($rows);
        set_flash('تم إرسال طلب التجديد. حوّل على زين كاش والإدارة تفعّل الاشتراك.');
        redirect('subscribe', array());
    }
}

$pendingMine = false;
foreach (load_renewals() as $r) {
    if ($r['user_id'] === $user['id'] && $r['status'] === 'pending') $pendingMine = true;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الاشتراك</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:900px;margin:auto;padding:16px}
.header{position:sticky;top:0;z-index:850;display:flex;flex-direction:column;align-items:stretch;gap:10px;margin:0 0 16px;padding:10px 0 12px;background:linear-gradient(180deg,rgba(2,6,23,.96),rgba(2,6,23,.84) 78%,transparent);backdrop-filter:blur(16px)}
.logo{font-size:26px;font-weight:bold}
<?= app_nav_styles() ?>
.nav{display:flex;flex-wrap:nowrap;gap:4px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:28px;padding:6px}
.nav a{flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e2e8f0;text-decoration:none;padding:11px 6px;border-radius:22px;font-weight:800;font-size:12px;border:0;background:transparent}
.nav a:hover{background:rgba(255,255,255,.08)}
.nav a.active{background:rgba(255,255,255,.22);color:#fff}
.hero{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:32px;padding:22px;text-align:center;margin:0 0 16px}
.emo{font-size:84px;line-height:1;display:inline-block;animation:bob 1.4s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0) rotate(-6deg) scale(1)}50%{transform:translateY(-10px) rotate(6deg) scale(1.08)}}
.live{min-height:28px;color:#f9a8d4;font-weight:800;margin:8px 0 0}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:0 0 16px}
.box{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:24px;padding:16px}
.box span{display:block;color:#cbd5e1;font-size:13px;font-weight:700}
.box b{display:block;font-size:22px;margin-top:6px}
.zain{background:linear-gradient(135deg,rgba(109,40,217,.4),rgba(15,23,42,.85));border:1px solid rgba(167,139,250,.35);border-radius:28px;padding:18px;margin:0 0 16px}
.zain h2{margin:0 0 8px}
.num{font-size:28px;font-weight:800;letter-spacing:.04em;margin:8px 0}
select,button{width:100%;padding:12px;border:0;border-radius:16px;font-family:inherit;font-weight:800}
button{margin-top:10px;background:linear-gradient(180deg,#c4b5fd,#7c3aed);color:#fff;cursor:pointer;font-size:16px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <div class="header"><?php app_nav('subscribe'); ?></div>
  <div class="hero">
    <div class="emo" id="emo"><?= $emojis[0] ?></div>
    <h1 style="margin:10px 0 4px">اشتراكي</h1>
    <div class="live" id="live"><?= htmlspecialchars($statusTxt, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <div class="grid">
    <div class="box"><span>نوع الاشتراك / الباقة</span><b><?= htmlspecialchars($pkgName, ENT_QUOTES, 'UTF-8') ?></b></div>
    <div class="box"><span>مدة الانتهاء</span><b><?= htmlspecialchars($expText, ENT_QUOTES, 'UTF-8') ?></b></div>
    <div class="box"><span>الأيام المتبقية</span><b><?= $expired ? 'منتهٍ' : $daysLeft . ' يوم' ?></b></div>
    <div class="box"><span>حالة الحساب</span><b><?= htmlspecialchars($user['status']==='active'?'نشط':$user['status'], ENT_QUOTES, 'UTF-8') ?></b></div>
  </div>
  <div class="zain">
    <h2>التجديد والدفع عبر زين كاش</h2>
    <p style="color:#ddd6fe;font-weight:700">حوّل مبلغ الباقة ثم اضغط طلب تجديد. الإدارة تفعّل الاشتراك بعد التأكد.</p>
    <div>رقم المحفظة</div>
    <div class="num"><?= htmlspecialchars($settings['zaincash_number']!==''?$settings['zaincash_number']:'لم يُحدد بعد', ENT_QUOTES, 'UTF-8') ?></div>
    <div><?= htmlspecialchars($settings['zaincash_name'], ENT_QUOTES, 'UTF-8') ?></div>
    <p><?= htmlspecialchars($settings['zaincash_note'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($pendingMine): ?>
      <p style="color:#fde68a;font-weight:800">طلب التجديد قيد المراجعة عند الإدارة.</p>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="renew" value="1">
      <select name="package_id">
        <?php foreach ($packages as $p): if (empty($p['active'])) continue; $sel = ($pkg && $p['id']===$pkg['id'])?' selected':''; ?>
        <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> — <?= number_format((int)$p['price']) ?> د.ع / <?= (int)$p['days'] ?> يوم</option>
        <?php endforeach; ?>
      </select>
      <button type="submit">طلب تجديد (دفعت بزين كاش)</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
const emos=<?= json_encode(array_values($emojis)) ?>;
const lines=<?= json_encode(array($statusTxt, 'الباقة: '.$pkgName, $expired?'جدد الآن عبر زين كاش':'متبقٍ '.$daysLeft.' يوم')) ?>;
let i=0,j=0;
setInterval(function(){ i=(i+1)%emos.length; document.getElementById('emo').textContent=emos[i]; }, 900);
setInterval(function(){ j=(j+1)%lines.length; document.getElementById('live').textContent=lines[j]; }, 2200);
</script>
</body>
</html>
