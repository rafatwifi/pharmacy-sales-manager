<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$token = isset($_SESSION['wait_token']) ? $_SESSION['wait_token'] : '';
if ($token === '') {
    $u = current_user();
    if ($u && $u['status'] === 'pending') $token = $u['wait_token'];
}
if ($token === '') redirect('login', array());
$api = url('api/wait') . '?token=' . rawurlencode($token);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بانتظار الموافقة</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:'Cairo',Tahoma,sans-serif;color:#fff;background:radial-gradient(circle at 30% 20%,#8b5cf6,#0f172a 40%,#020617)}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:18px}
.card{width:100%;max-width:430px;text-align:center;background:rgba(15,23,42,.75);border:1px solid rgba(255,255,255,.18);border-radius:28px;padding:30px 22px;backdrop-filter:blur(18px)}
.emoji{font-size:86px;line-height:1;animation:pulse 1.6s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1) rotate(-4deg)}50%{transform:scale(1.12) rotate(4deg)}}
h1{margin:12px 0 8px}
.sub{color:#e9d5ff;font-weight:700;min-height:48px}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;margin-left:6px;animation:blink 1s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
a{color:#f9a8d4;font-weight:800;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="emoji" id="em">⏳</div>
    <h1>طلبك لايف عند الإدارة</h1>
    <p class="sub" id="sub"><span class="dot"></span> ننتظر الموافقة... الدخول يتم تلقائياً فور القبول</p>
  </div>
</div>
<script>
const emojis=['⏳','🙏','😊','✨','💼','🔔'];
let n=0;
setInterval(function(){ n=(n+1)%emojis.length; document.getElementById('em').textContent=emojis[n]; }, 900);
const lines=['ننتظر الموافقة... الدخول يتم تلقائياً فور القبول','الصفحة لايف ومتصلة بالإدارة','لا تغلق الصفحة'];
let i=0;
setInterval(function(){ i=(i+1)%lines.length; document.getElementById('sub').innerHTML='<span class="dot"></span> '+lines[i]; }, 2400);
function poll(){
  fetch(<?= json_encode($api) ?>).then(function(r){ return r.json(); }).then(function(d){
    if (d.status==='active' && d.redirect) {
      document.getElementById('em').textContent='😄';
      document.getElementById('sub').textContent='تمت الموافقة! جاري الدخول...';
      setTimeout(function(){ location.href=d.redirect; }, 700);
      return;
    }
    if (d.status==='rejected') {
      document.getElementById('em').textContent='😢';
      document.getElementById('sub').textContent='تم رفض الطلب';
      setTimeout(function(){ location.href=<?= json_encode(url('login')) ?>; }, 1400);
    }
  }).catch(function(){});
}
poll();
setInterval(poll, 2000);
</script>
</body>
</html>
