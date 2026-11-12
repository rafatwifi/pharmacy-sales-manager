<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
if (current_user() && subscription_ok(current_user())) redirect('dashboard', array());

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'جلسة منتهية، حدّث الصفحة';
    } else {
        $username = strtolower(trim(isset($_POST['username']) ? $_POST['username'] : ''));
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $user = find_user_by('username', $username);
        if (!$user || !password_verify($password, $user['password'])) {
            $err = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        } elseif ($user['status'] === 'pending') {
            $_SESSION['wait_token'] = $user['wait_token'];
            redirect('pending', array());
        } elseif ($user['status'] === 'rejected') {
            $err = 'تم رفض طلب العضوية';
        } elseif ($user['status'] === 'paused') {
            login_user($user);
            redirect('paused', array());
        } elseif (!subscription_ok($user)) {
            login_user($user);
            redirect('expired', array());
        } else {
            login_user($user);
            redirect('dashboard', array());
        }
    }
}
$qerr = isset($_GET['err']) ? $_GET['err'] : '';
if ($qerr === 'rejected') $err = 'تم رفض طلب العضوية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>دخول المندوبين</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:'Cairo',Tahoma,sans-serif;color:#fff;overflow:hidden;background:#020617}
.bg{position:fixed;inset:0;background:radial-gradient(circle at 20% 20%,#0ea5e9 0%,transparent 32%),radial-gradient(circle at 80% 0%,#db2777 0%,transparent 30%),radial-gradient(circle at 70% 90%,#22c55e 0%,transparent 28%),linear-gradient(180deg,#020617,#0f172a)}
.orb{position:absolute;border-radius:50%;filter:blur(8px);opacity:.35;animation:float 8s ease-in-out infinite}
.o1{width:180px;height:180px;background:#38bdf8;top:12%;right:8%;animation-delay:0s}
.o2{width:120px;height:120px;background:#f472b6;bottom:18%;left:12%;animation-delay:1.4s}
.o3{width:90px;height:90px;background:#facc15;top:40%;left:18%;animation-delay:.7s}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-18px)}}
.bits{position:fixed;inset:0;pointer-events:none}
.bit{position:absolute;font-size:22px;animation:rise 7s linear infinite;opacity:.0}
@keyframes rise{0%{transform:translateY(20px) scale(.7);opacity:0}15%{opacity:.85}100%{transform:translateY(-110vh) scale(1.2);opacity:0}}
.wrap{position:relative;z-index:2;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:18px}
.card{width:100%;max-width:430px;background:rgba(15,23,42,.72);border:1px solid rgba(255,255,255,.18);border-radius:28px;padding:28px 22px 22px;backdrop-filter:blur(18px);box-shadow:0 24px 60px rgba(0,0,0,.35)}
.face{width:126px;height:126px;margin:0 auto 8px;border-radius:50%;background:linear-gradient(180deg,#fde68a,#f59e0b);position:relative;box-shadow:0 12px 30px rgba(245,158,11,.35);animation:bob 3.4s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.eye{position:absolute;top:46px;width:16px;height:16px;background:#0f172a;border-radius:50%;animation:blink 4.2s infinite}
.e1{right:36px}.e2{left:36px}
@keyframes blink{0%,92%,100%{transform:scaleY(1)}96%{transform:scaleY(.08)}}
.mouth{position:absolute;left:50%;bottom:32px;width:42px;height:20px;border:4px solid #0f172a;border-top:0;border-radius:0 0 24px 24px;transform:translateX(-50%);transition:.25s}
.face.listen .mouth{width:18px;height:18px;border-radius:50%;border:4px solid #0f172a;background:transparent}
.face.happy .mouth{width:48px;height:24px;bottom:28px}
.face.sad .mouth{width:36px;height:16px;bottom:28px;transform:translateX(-50%) rotate(180deg);border-radius:24px 24px 0 0}
.blush{position:absolute;top:62px;width:18px;height:10px;background:rgba(244,114,182,.45);border-radius:50%;filter:blur(1px)}
.b1{right:18px}.b2{left:18px}
h1{margin:6px 0 4px;text-align:center;font-size:26px}
.sub{text-align:center;color:#cbd5e1;margin:0 0 18px;font-weight:700}
.live{min-height:28px;text-align:center;color:#f9a8d4;font-weight:800;margin:0 0 12px}
input{width:100%;padding:14px;border:0;border-radius:14px;margin:0 0 12px;font-family:inherit;font-size:16px;text-align:center}
.btn{width:100%;padding:14px;border:0;border-radius:14px;background:linear-gradient(180deg,#f472b6,#db2777);color:#fff;font-weight:800;font-size:17px;font-family:inherit;cursor:pointer}
.alt{display:block;text-align:center;margin-top:14px;color:#e2e8f0;text-decoration:none;font-weight:700}
.err{background:#e11d48;padding:10px;border-radius:12px;text-align:center;margin:0 0 12px;font-weight:800}
</style>
</head>
<body>
<div class="bg"></div>
<div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
<div class="bits" id="bits"></div>
<div class="wrap">
  <form class="card" method="post" id="box">
    <div class="face" id="face"><span class="eye e1"></span><span class="eye e2"></span><span class="blush b1"></span><span class="blush b2"></span><span class="mouth"></span></div>
    <h1>مرحباً بك</h1>
    <p class="sub">نظام المندوبين الطبي — دخول آمن</p>
    <div class="live" id="live">😊 جاهز لاستقبالك...</div>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input name="username" id="user" placeholder="اسم المستخدم" required autocomplete="username">
    <input type="password" name="password" id="pass" placeholder="كلمة المرور" required>
    <button class="btn" type="submit">دخول</button>
    <a class="alt" href="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>">مستخدم جديد؟ تسجيل عضوية</a>
  </form>
</div>
<script>
const face=document.getElementById('face');
const live=document.getElementById('live');
const lines=['😊 النظام لايف الآن','💊 بياناتك محفوظة لكل مندوب','✨ بانتظار دخولك','🔐 الدخول مشفّر'];
let i=0;
setInterval(function(){ i=(i+1)%lines.length; live.textContent=lines[i]; }, 2200);
['user','pass'].forEach(function(id){
  const el=document.getElementById(id);
  el.addEventListener('focus', function(){ face.className='face listen'; live.textContent='👀 أسمعك...'; });
  el.addEventListener('blur', function(){ face.className='face'; });
});
document.getElementById('box').addEventListener('submit', function(){ face.className='face happy'; live.textContent='😄 جاري التحقق...'; });
<?php if ($err): ?>face.className='face sad'; live.textContent='😕 <?= addslashes($err) ?>';<?php endif; ?>
const bits=document.getElementById('bits');
const em=['💊','✨','😊','💚','🟣','⭐'];
for(let n=0;n<14;n++){
  const s=document.createElement('div');
  s.className='bit'; s.textContent=em[n%em.length];
  s.style.right=(Math.random()*100)+'%';
  s.style.animationDelay=(Math.random()*7)+'s';
  s.style.fontSize=(16+Math.random()*18)+'px';
  bits.appendChild(s);
}
</script>
</body>
</html>
