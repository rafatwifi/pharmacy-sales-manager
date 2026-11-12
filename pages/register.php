<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'جلسة منتهية، حدّث الصفحة';
    } else {
        $username = strtolower(trim(isset($_POST['username']) ? $_POST['username'] : ''));
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        if (!preg_match('/^[a-z0-9._-]{3,20}$/', $username)) {
            $err = 'اسم المستخدم 3-20 حرف إنجليزي أو رقم';
        } elseif ($name === '') {
            $err = 'أدخل الاسم الظاهر';
        } elseif (strlen($password) < 4) {
            $err = 'كلمة المرور قصيرة';
        } elseif (find_user_by('username', $username)) {
            $err = 'اسم المستخدم مستخدم مسبقاً';
        } else {
            $token = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
            $users = load_users();
            $users[] = array(
                'id' => $username,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'name' => $name,
                'phone' => $phone,
                'role' => 'rep',
                'status' => 'pending',
                'package_id' => '',
                'expires_at' => '',
                'created_at' => date('Y-m-d H:i'),
                'wait_token' => $token
            );
            save_users($users);
            ensure_tenant_files($username);
            $_SESSION['wait_token'] = $token;
            redirect('pending', array());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل عضوية</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:'Cairo',Tahoma,sans-serif;color:#fff;background:radial-gradient(circle at top,#22c55e,#0f172a 42%,#020617)}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:18px}
.card{width:100%;max-width:430px;background:rgba(15,23,42,.72);border:1px solid rgba(255,255,255,.18);border-radius:28px;padding:26px 22px;backdrop-filter:blur(18px)}
.face{width:110px;height:110px;margin:0 auto 10px;border-radius:50%;background:linear-gradient(180deg,#bbf7d0,#22c55e);position:relative;animation:bob 3s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.eye{position:absolute;top:40px;width:14px;height:14px;background:#0f172a;border-radius:50%;animation:blink 4s infinite}
.e1{right:32px}.e2{left:32px}
@keyframes blink{0%,92%,100%{transform:scaleY(1)}96%{transform:scaleY(.1)}}
.mouth{position:absolute;left:50%;bottom:28px;width:36px;height:16px;border:4px solid #0f172a;border-top:0;border-radius:0 0 20px 20px;transform:translateX(-50%)}
h1{text-align:center;margin:0 0 6px}
.sub{text-align:center;color:#cbd5e1;margin:0 0 16px;font-weight:700}
input{width:100%;padding:14px;border:0;border-radius:14px;margin:0 0 10px;font-family:inherit;font-size:16px;text-align:center}
.btn{width:100%;padding:14px;border:0;border-radius:14px;background:linear-gradient(180deg,#4ade80,#16a34a);color:#fff;font-weight:800;font-size:17px;font-family:inherit;cursor:pointer}
.alt{display:block;text-align:center;margin-top:14px;color:#e2e8f0;text-decoration:none;font-weight:700}
.err{background:#e11d48;padding:10px;border-radius:12px;text-align:center;margin:0 0 12px;font-weight:800}
.live{text-align:center;color:#bbf7d0;font-weight:800;margin:0 0 10px}
</style>
</head>
<body>
<div class="wrap">
  <form class="card" method="post">
    <div class="face"><span class="eye e1"></span><span class="eye e2"></span><span class="mouth"></span></div>
    <h1>عضوية مندوب جديد</h1>
    <p class="sub">بعد التسجيل تنتظر موافقة الإدارة، والصفحة تبقى لايف حتى يُقبل طلبك.</p>
    <div class="live" id="live">🟢 النموذج لايف — عبّئ بياناتك</div>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input name="name" placeholder="الاسم الكامل" required>
    <input name="username" placeholder="اسم المستخدم (إنجليزي)" required>
    <input name="phone" placeholder="رقم الهاتف (اختياري)">
    <input type="password" name="password" placeholder="كلمة المرور" required>
    <button class="btn" type="submit">إرسال طلب العضوية</button>
    <a class="alt" href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>">لديك حساب؟ تسجيل الدخول</a>
  </form>
</div>
<script>
const live=document.getElementById('live');
const msgs=['🟢 النموذج لايف — عبّئ بياناتك','🤝 الإدارة توافق يدوياً','⏳ بعدها يدخل حسابك تلقائياً'];
let i=0; setInterval(function(){ i=(i+1)%msgs.length; live.textContent=msgs[i]; }, 2400);
</script>
</body>
</html>
