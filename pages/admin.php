<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$me = app_require_admin();
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
$allowedTabs = array('users', 'packages', 'pay', 'account', 'site');
if (!in_array($tab, $allowedTabs, true)) $tab = 'users';
$msg = '';
$err = '';
$settings = load_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'جلسة غير صالحة، حدّث الصفحة';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $uid = isset($_POST['uid']) ? $_POST['uid'] : '';
        if ($action === 'approve') {
            $pkgId = isset($_POST['package_id']) ? $_POST['package_id'] : 'trial';
            $pkg = package_by_id($pkgId);
            $days = $pkg && !empty($pkg['days']) ? (int)$pkg['days'] : 7;
            update_user($uid, array(
                'status' => 'active',
                'package_id' => $pkgId,
                'expires_at' => date('Y-m-d H:i', time() + max(1, $days) * 86400),
                'pay_method' => 'zaincash'
            ));
            ensure_tenant_files($uid);
            $msg = 'تمت الموافقة وتفعيل الحساب';
            $tab = 'users';
        } elseif ($action === 'reject') {
            update_user($uid, array('status' => 'rejected'));
            $msg = 'تم رفض الطلب';
            $tab = 'users';
        } elseif ($action === 'pause') {
            $target = find_user_by('id', $uid);
            if ($target && !is_admin_user($target)) update_user($uid, array('status' => 'paused'));
            $msg = 'تم إيقاف المستخدم';
            $tab = 'users';
        } elseif ($action === 'resume') {
            $user = find_user_by('id', $uid);
            $pkgId = $user && !empty($user['package_id']) ? $user['package_id'] : 'trial';
            $pkg = package_by_id($pkgId);
            $days = $pkg && !empty($pkg['days']) ? (int)$pkg['days'] : 30;
            $exp = $user && !empty($user['expires_at']) && strtotime($user['expires_at']) > time()
                ? $user['expires_at']
                : date('Y-m-d H:i', time() + $days * 86400);
            update_user($uid, array('status' => 'active', 'package_id' => $pkgId, 'expires_at' => $exp));
            $msg = 'تم تشغيل المستخدم';
            $tab = 'users';
        } elseif ($action === 'assign') {
            $pkgId = isset($_POST['package_id']) ? $_POST['package_id'] : '';
            $pkg = package_by_id($pkgId);
            if ($pkg) {
                $days = !empty($pkg['days']) ? (int)$pkg['days'] : 30;
                update_user($uid, array(
                    'package_id' => $pkgId,
                    'expires_at' => date('Y-m-d H:i', time() + max(1, $days) * 86400),
                    'status' => 'active',
                    'pay_method' => 'zaincash'
                ));
                $msg = 'تم تعيين الباقة عبر زين كاش وتجديد الاشتراك';
            }
            $tab = 'users';
        } elseif ($action === 'set_pass') {
            $p1 = isset($_POST['new_pass']) ? $_POST['new_pass'] : '';
            $p2 = isset($_POST['new_pass2']) ? $_POST['new_pass2'] : '';
            $target = find_user_by('id', $uid);
            if (!$target) {
                $err = 'المستخدم غير موجود';
            } elseif (strlen($p1) < 4) {
                $err = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل';
            } elseif ($p1 !== $p2) {
                $err = 'تأكيد كلمة المرور غير مطابق';
            } else {
                update_user($uid, array('password' => password_hash($p1, PASSWORD_BCRYPT)));
                $msg = 'تم تغيير كلمة المرور لـ ' . $target['username'];
            }
            $tab = 'users';
        } elseif ($action === 'delete_user') {
            $target = find_user_by('id', $uid);
            if ($target && delete_user_account($uid)) {
                $msg = 'تم حذف المستخدم ' . $target['username'];
            } else {
                $err = 'لا يمكن حذف حساب المدير';
            }
            $tab = 'users';
        } elseif ($action === 'save_pkg') {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', isset($_POST['id']) ? $_POST['id'] : '');
            if ($id === '') $id = 'p' . substr(md5(uniqid('', true)), 0, 8);
            $rows = load_packages();
            $found = false;
            $row = array(
                'id' => $id,
                'name' => trim(isset($_POST['name']) ? $_POST['name'] : ''),
                'price' => (int)(isset($_POST['price']) ? $_POST['price'] : 0),
                'days' => (int)(isset($_POST['days']) ? $_POST['days'] : 30),
                'max_products' => (int)(isset($_POST['max_products']) ? $_POST['max_products'] : 0),
                'max_sales' => (int)(isset($_POST['max_sales']) ? $_POST['max_sales'] : 0),
                'active' => isset($_POST['pkg_active']) ? 1 : 0,
                'pay_method' => 'zaincash'
            );
            for ($i = 0; $i < count($rows); $i++) {
                if ($rows[$i]['id'] === $id) { $rows[$i] = $row; $found = true; break; }
            }
            if (!$found) $rows[] = $row;
            save_packages($rows);
            $msg = 'تم حفظ الباقة';
            $tab = 'packages';
        } elseif ($action === 'delete_pkg') {
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            $rows = load_packages();
            $out = array();
            foreach ($rows as $r) { if ($r['id'] !== $id) $out[] = $r; }
            save_packages($out);
            $msg = 'تم حذف الباقة';
            $tab = 'packages';
        } elseif ($action === 'save_pay') {
            $settings['zaincash_enabled'] = isset($_POST['zaincash_enabled']) ? 1 : 0;
            $settings['zaincash_number'] = trim(isset($_POST['zaincash_number']) ? $_POST['zaincash_number'] : '');
            $settings['zaincash_name'] = trim(isset($_POST['zaincash_name']) ? $_POST['zaincash_name'] : '');
            $settings['zaincash_note'] = trim(isset($_POST['zaincash_note']) ? $_POST['zaincash_note'] : '');
            save_settings($settings);
            $msg = 'تم حفظ إعدادات زين كاش';
            $tab = 'pay';
        } elseif ($action === 'confirm_renewal') {
            $rid = isset($_POST['rid']) ? $_POST['rid'] : '';
            $rows = load_renewals();
            for ($i = 0; $i < count($rows); $i++) {
                if ($rows[$i]['id'] === $rid && $rows[$i]['status'] === 'pending') {
                    $pkg = package_by_id($rows[$i]['package_id']);
                    $days = $pkg && !empty($pkg['days']) ? (int)$pkg['days'] : 30;
                    update_user($rows[$i]['user_id'], array(
                        'package_id' => $rows[$i]['package_id'],
                        'expires_at' => date('Y-m-d H:i', time() + max(1, $days) * 86400),
                        'status' => 'active',
                        'pay_method' => 'zaincash'
                    ));
                    $rows[$i]['status'] = 'done';
                    $rows[$i]['done_at'] = date('Y-m-d H:i');
                    $msg = 'تم تجديد اشتراك ' . $rows[$i]['username'];
                    break;
                }
            }
            save_renewals($rows);
            $tab = 'pay';
        } elseif ($action === 'save_site') {
            $settings['site_name'] = trim(isset($_POST['site_name']) ? $_POST['site_name'] : '');
            $settings['site_about'] = trim(isset($_POST['site_about']) ? $_POST['site_about'] : '');
            if (!empty($_FILES['site_logo']['tmp_name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
                $allow = array('jpg','jpeg','png','gif','webp');
                if (in_array($ext, $allow, true) && @getimagesize($_FILES['site_logo']['tmp_name'])) {
                    $dir = APP_ROOT . '/uploads/site';
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    $fname = 'logo_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dir . '/' . $fname)) {
                        if (!empty($settings['site_logo']) && file_exists($dir . '/' . $settings['site_logo'])) {
                            @unlink($dir . '/' . $settings['site_logo']);
                        }
                        $settings['site_logo'] = $fname;
                    }
                }
            }
            save_settings($settings);
            $msg = 'تم حفظ إعدادات الموقع';
            $tab = 'site';
        } elseif ($action === 'change_admin') {
            $newUser = strtolower(trim(isset($_POST['admin_username']) ? $_POST['admin_username'] : ''));
            $newPass = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
            $newPass2 = isset($_POST['admin_password2']) ? $_POST['admin_password2'] : '';
            $newName = trim(isset($_POST['admin_name']) ? $_POST['admin_name'] : '');
            if (!preg_match('/^[a-z0-9._-]{3,20}$/', $newUser)) {
                $err = 'اسم المستخدم 3-20 حرف إنجليزي أو رقم';
            } else {
                $taken = find_user_by('username', $newUser);
                if ($taken && (string)$taken['id'] !== (string)$me['id']) {
                    $err = 'اسم المستخدم مستخدم مسبقاً';
                } elseif ($newPass !== '' && $newPass !== $newPass2) {
                    $err = 'تأكيد كلمة المرور غير مطابق';
                } elseif ($newPass !== '' && strlen($newPass) < 4) {
                    $err = 'كلمة المرور قصيرة';
                } else {
                    $patch = array('username' => $newUser);
                    if ($newName !== '') $patch['name'] = $newName;
                    if ($newPass !== '') $patch['password'] = password_hash($newPass, PASSWORD_BCRYPT);
                    $me = update_user($me['id'], $patch);
                    $msg = 'تم تحديث حساب المدير';
                }
            }
            $tab = 'account';
        }
    }
}

$users = load_users();
$packages = load_packages();
$settings = load_settings();
$pending = 0; $active = 0; $paused = 0;
foreach ($users as $u) {
    if ($u['status'] === 'pending') $pending++;
    if ($u['status'] === 'active') $active++;
    if ($u['status'] === 'paused') $paused++;
}
function pkg_name($id) {
    $p = package_by_id($id);
    return $p ? $p['name'] : 'بدون باقة';
}
function status_label($s) {
    $map = array('active' => 'نشط', 'paused' => 'متوقف', 'pending' => 'انتظار', 'rejected' => 'مرفوض');
    return isset($map[$s]) ? $map[$s] : $s;
}
function initial_of($name) {
    $name = trim((string)$name);
    if ($name === '') return 'م';
    if (function_exists('mb_substr')) return mb_substr($name, 0, 1, 'UTF-8');
    return substr($name, 0, 1);
}
$adminUrl = url('admin');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#070b14; --side:#0c1220; --card:#10182a; --line:rgba(255,255,255,.08);
  --text:#f8fafc; --muted:#94a3b8; --accent:#38bdf8; --violet:#8b5cf6;
  --ok:#10b981; --warn:#f59e0b; --bad:#f43f5e; --zain:#6d28d9;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:'Cairo',Tahoma,sans-serif}
body{background:
  radial-gradient(1200px 500px at 100% -10%,rgba(56,189,248,.18),transparent 50%),
  radial-gradient(900px 400px at -10% 110%,rgba(139,92,246,.16),transparent 46%),
  var(--bg)}
a{color:inherit}
.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.side{display:flex;flex-direction:column;background:linear-gradient(180deg,rgba(12,18,32,.98),rgba(8,12,22,.98));border-left:1px solid var(--line);padding:22px 16px;position:sticky;top:0;height:100vh}
.brand{display:flex;align-items:center;gap:10px;margin:0 0 28px}
.logo-mark{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#38bdf8,#8b5cf6);font-size:20px}
.brand b{display:block;font-size:15px}.brand span{color:var(--muted);font-size:12px}
.nav-l{display:flex;flex-direction:column;gap:6px}
.nav-l a{display:flex;align-items:center;gap:10px;text-decoration:none;padding:12px 14px;border-radius:14px;color:#dbeafe;font-weight:700}
.nav-l a .ic{width:28px;text-align:center}
.nav-l a.active{background:rgba(56,189,248,.14);color:#fff;box-shadow:inset 0 0 0 1px rgba(56,189,248,.25)}
.nav-l a:hover{background:rgba(255,255,255,.05)}
.side-foot{margin-top:auto;padding-top:18px}
.main{padding:28px 28px 48px}
.top{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin:0 0 22px}
.top h1{margin:0;font-size:28px;letter-spacing:-.03em}
.top p{margin:6px 0 0;color:var(--muted);font-weight:600}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 22px}
.stat{background:linear-gradient(180deg,rgba(16,24,42,.92),rgba(12,18,32,.9));border:1px solid var(--line);border-radius:22px;padding:18px}
.stat span{color:var(--muted);font-size:13px;font-weight:700}
.stat b{display:block;font-size:30px;margin-top:8px}
.panel{background:rgba(16,24,42,.86);border:1px solid var(--line);border-radius:24px;padding:20px;margin:0 0 18px;backdrop-filter:blur(16px)}
.panel h2{margin:0 0 16px;font-size:18px}
.flash{padding:12px 14px;border-radius:16px;margin:0 0 16px;font-weight:800}
.flash.ok{background:rgba(16,185,129,.16);color:#6ee7b7}
.flash.bad{background:rgba(244,63,94,.16);color:#fda4af}
.member{display:grid;grid-template-columns:1.2fr .9fr auto;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:18px;margin:0 0 10px;background:rgba(255,255,255,.03)}
.who{display:flex;align-items:center;gap:12px;min-width:0}
.ava{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#1d4ed8,#7c3aed);font-weight:800;flex:0 0 auto}
.who strong{display:block;font-size:15px}
.who small{color:var(--muted);font-weight:700}
.meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;color:var(--muted);font-size:13px;font-weight:700}
.pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800}
.pill.on{background:rgba(16,185,129,.16);color:#6ee7b7}
.pill.off{background:rgba(245,158,11,.16);color:#fcd34d}
.pill.wait{background:rgba(56,189,248,.16);color:#7dd3fc}
.pill.no{background:rgba(244,63,94,.16);color:#fda4af}
.pill.admin{background:rgba(139,92,246,.18);color:#ddd6fe}
.acts{position:relative}
.acts summary{list-style:none;cursor:pointer;background:#1e293b;border:1px solid var(--line);border-radius:12px;padding:8px 14px;font-weight:800;font-size:13px}
.acts summary::-webkit-details-marker{display:none}
.sheet{position:absolute;left:0;top:calc(100% + 6px);z-index:20;min-width:220px;background:#0f172a;border:1px solid var(--line);border-radius:16px;padding:8px;box-shadow:0 18px 40px rgba(0,0,0,.35)}
.sheet form,.sheet .row{display:flex;flex-direction:column;gap:6px;padding:6px}
.sheet select,.sheet input,.field input,.field textarea,.field select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#0b1220;color:#fff;font-family:inherit}
.btn{border:0;border-radius:12px;padding:10px 12px;color:#fff;font-weight:800;font-family:inherit;cursor:pointer;width:100%}
.btn.ok{background:#059669}.btn.warn{background:#d97706}.btn.bad{background:#e11d48}.btn.info{background:#2563eb}.btn.ghost{background:#1e293b}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.pkgs{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.pkg{border:1px solid var(--line);border-radius:20px;padding:18px;background:rgba(255,255,255,.03);display:flex;flex-direction:column;gap:8px}
.pkg .price{font-size:26px;font-weight:800}
.pkg .pay{display:inline-flex;align-items:center;gap:6px;color:#ddd6fe;font-size:12px;font-weight:800;background:rgba(109,40,217,.22);padding:4px 10px;border-radius:999px;width:max-content}
.field{display:flex;flex-direction:column;gap:6px;margin:0 0 12px}
.field label{font-size:13px;color:var(--muted);font-weight:800}
.zain{background:linear-gradient(135deg,rgba(109,40,217,.35),rgba(15,23,42,.9));border:1px solid rgba(167,139,250,.3);border-radius:24px;padding:22px}
.zain h2{margin:0 0 6px}
.preview{margin-top:16px;background:rgba(0,0,0,.22);border-radius:18px;padding:16px}
.preview b{display:block;font-size:22px;letter-spacing:.04em;margin:6px 0}
@media(max-width:980px){
  .shell{grid-template-columns:1fr}
  .side{position:sticky;top:0;height:auto;z-index:30;border-left:0;border-bottom:1px solid var(--line)}
  .nav-l{flex-direction:row;flex-wrap:wrap}
  .nav-l a{padding:8px 10px;font-size:13px}
  .stats{grid-template-columns:1fr 1fr}
  .member,.grid2{grid-template-columns:1fr}
  .main{padding:16px}
  .sheet{right:0;left:auto}
}
</style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand">
      <div class="logo-mark">💊</div>
      <div><b>لوحة الإدارة</b><span>استضافة المندوبين</span></div>
    </div>
    <nav class="nav-l">
      <a class="<?= $tab==='users'?'active':'' ?>" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>?tab=users"><span class="ic">👥</span> المستخدمون</a>
      <a class="<?= $tab==='packages'?'active':'' ?>" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>?tab=packages"><span class="ic">📦</span> الباقات</a>
      <a class="<?= $tab==='pay'?'active':'' ?>" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>?tab=pay"><span class="ic">💳</span> الدفع · زين كاش</a>
      <a class="<?= $tab==='site'?'active':'' ?>" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>?tab=site"><span class="ic">🏠</span> عن الموقع</a>
      <a class="<?= $tab==='account'?'active':'' ?>" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>?tab=account"><span class="ic">🔐</span> حساب المدير</a>
      <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>"><span class="ic">📊</span> نظام المبيعات</a>
    </nav>
    <div class="side-foot">
      <a class="nav-l" href="<?= htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') ?>" style="display:block;padding:12px 14px;border-radius:14px;text-decoration:none;color:#fda4af;font-weight:800">خروج</a>
    </div>
  </aside>
  <div class="main">
    <div class="top">
      <div>
        <h1><?php
          $titles = array('users'=>'المستخدمون','packages'=>'الباقات','pay'=>'الدفع بزين كاش','site'=>'عن الموقع','account'=>'حساب المدير');
          echo isset($titles[$tab]) ? $titles[$tab] : 'لوحة الإدارة';
        ?></h1>
        <p>مرحباً <?= htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8') ?> — إدارة كاملة ومنسّقة لكل اللوحة</p>
      </div>
    </div>
    <?php if ($msg): ?><div class="flash ok"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash bad"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="stats">
      <div class="stat"><span>كل المستخدمين</span><b><?= count($users) ?></b></div>
      <div class="stat"><span>بانتظار الموافقة</span><b><?= $pending ?></b></div>
      <div class="stat"><span>نشطون</span><b><?= $active ?></b></div>
      <div class="stat"><span>متوقفون</span><b><?= $paused ?></b></div>
    </div>

    <?php if ($tab === 'users'): ?>
    <div class="panel">
      <h2>المندوبون</h2>
      <?php foreach ($users as $u):
        $st = isset($u['status']) ? $u['status'] : '';
        $pill = $st==='active'?'on':($st==='paused'?'off':($st==='pending'?'wait':'no'));
        if (is_admin_user($u)) $pill = 'admin';
      ?>
      <div class="member">
        <div class="who">
          <div class="ava"><?= htmlspecialchars(initial_of($u['name']), ENT_QUOTES, 'UTF-8') ?></div>
          <div>
            <strong><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <small>@<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></small>
          </div>
        </div>
        <div class="meta">
          <span class="pill <?= $pill ?>"><?= is_admin_user($u) ? 'مدير' : htmlspecialchars(status_label($st), ENT_QUOTES, 'UTF-8') ?></span>
          <span><?= htmlspecialchars(pkg_name(isset($u['package_id'])?$u['package_id']:''), ENT_QUOTES, 'UTF-8') ?></span>
          <span><?= htmlspecialchars(isset($u['expires_at']) && $u['expires_at']!=='' ? $u['expires_at'] : '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if (is_admin_user($u)): ?>
          <span class="pill admin">حساب الإدارة</span>
        <?php else: ?>
        <details class="acts">
          <summary>إجراءات</summary>
          <div class="sheet">
            <?php if ($st === 'pending'): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="approve">
              <select name="package_id">
                <?php foreach ($packages as $p): ?>
                <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> — <?= number_format((int)$p['price']) ?> د.ع</option>
                <?php endforeach; ?>
              </select>
              <button class="btn ok" type="submit">موافقة وتفعيل</button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="reject">
              <button class="btn bad" type="submit">رفض</button>
            </form>
            <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="assign">
              <select name="package_id">
                <?php foreach ($packages as $p): $sel = (!empty($u['package_id']) && $u['package_id']===$p['id']) ? ' selected' : ''; ?>
                <option value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> — <?= number_format((int)$p['price']) ?> د.ع</option>
                <?php endforeach; ?>
              </select>
              <button class="btn info" type="submit">تعيين باقة (زين كاش)</button>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="<?= $st==='paused'?'resume':'pause' ?>">
              <button class="btn warn" type="submit"><?= $st==='paused'?'تشغيل':'إيقاف' ?></button>
            </form>
            <?php endif; ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="set_pass">
              <input type="password" name="new_pass" placeholder="كلمة مرور جديدة" required>
              <input type="password" name="new_pass2" placeholder="تأكيد كلمة المرور" required>
              <button class="btn ghost" type="submit">تغيير كلمة المرور</button>
            </form>
            <form method="post" onsubmit="return confirm('حذف هذا المستخدم نهائياً؟');">
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete_user">
              <button class="btn bad" type="submit">حذف المستخدم</button>
            </form>
          </div>
        </details>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'packages'): ?>
    <div class="panel">
      <h2>إنشاء أو تعديل باقة</h2>
      <form method="post" class="grid2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_pkg">
        <div class="field"><label>رمز الباقة</label><input name="id" placeholder="basic"></div>
        <div class="field"><label>اسم الباقة</label><input name="name" placeholder="أساسي" required></div>
        <div class="field"><label>السعر (د.ع) — زين كاش</label><input type="number" name="price" value="0"></div>
        <div class="field"><label>المدة بالأيام</label><input type="number" name="days" value="30"></div>
        <div class="field"><label>حد المنتجات (0 مفتوح)</label><input type="number" name="max_products" value="0"></div>
        <div class="field"><label>حد المبيعات (0 مفتوح)</label><input type="number" name="max_sales" value="0"></div>
        <label class="field" style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="pkg_active" checked> مفعّلة للبيع</label>
        <div class="field"><label>&nbsp;</label><button class="btn ok" type="submit">حفظ الباقة</button></div>
      </form>
    </div>
    <div class="pkgs">
      <?php foreach ($packages as $p): ?>
      <div class="pkg">
        <span class="pay">زين كاش</span>
        <strong><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></strong>
        <div class="price"><?= number_format((int)$p['price']) ?> <small style="font-size:14px;color:var(--muted)">د.ع</small></div>
        <div class="meta"><?= (int)$p['days'] ?> يوم · منتجات <?= ((int)$p['max_products']===0?'مفتوح':(int)$p['max_products']) ?> · مبيعات <?= ((int)$p['max_sales']===0?'مفتوح':(int)$p['max_sales']) ?></div>
        <form method="post" onsubmit="return confirm('حذف الباقة؟');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_pkg">
          <input type="hidden" name="id" value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn bad" type="submit">حذف الباقة</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'site'): ?>
    <div class="panel">
      <h2>إعدادات الموقع</h2>
      <p style="color:var(--muted);font-weight:700">الاسم والصورة والنبذة تظهر للمندوب في صفحة «عن الموقع».</p>
      <?php $logoNow = site_logo_url(); if ($logoNow !== ''): ?>
      <img src="<?= htmlspecialchars($logoNow, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 0 14px">
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_site">
        <div class="field"><label>اسم الموقع</label><input name="site_name" value="<?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>صورة الموقع / الشعار</label><input type="file" name="site_logo" accept="image/*"></div>
        <div class="field"><label>نبذة عن الموقع</label><textarea name="site_about" rows="6"><?= htmlspecialchars($settings['site_about'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
        <button class="btn ok" type="submit">حفظ إعدادات الموقع</button>
      </form>
    </div>

    <?php elseif ($tab === 'pay'): ?>
    <div class="zain">
      <h2>وسيلة الدفع: زين كاش</h2>
      <p style="color:#ddd6fe;font-weight:700;margin-top:0">هذا الرقم يظهر عند بيع باقات الاستضافة للمندوبين.</p>
      <form method="post" class="grid2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_pay">
        <label class="field" style="flex-direction:row;align-items:center;gap:8px;grid-column:1/-1"><input type="checkbox" name="zaincash_enabled" <?= !empty($settings['zaincash_enabled'])?'checked':'' ?>> تفعيل زين كاش</label>
        <div class="field"><label>رقم محفظة زين كاش</label><input name="zaincash_number" value="<?= htmlspecialchars($settings['zaincash_number'], ENT_QUOTES, 'UTF-8') ?>" placeholder="07xxxxxxxxx"></div>
        <div class="field"><label>اسم المستلم</label><input name="zaincash_name" value="<?= htmlspecialchars($settings['zaincash_name'], ENT_QUOTES, 'UTF-8') ?>" placeholder="اسم صاحب المحفظة"></div>
        <div class="field" style="grid-column:1/-1"><label>ملاحظة للمندوب</label><textarea name="zaincash_note" rows="3"><?= htmlspecialchars($settings['zaincash_note'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
        <div class="field"><button class="btn ok" type="submit">حفظ بيانات زين كاش</button></div>
      </form>
      <div class="preview">
        <span class="pay">معاينة بطاقة الدفع</span>
        <div>حوّل إلى زين كاش</div>
        <b><?= htmlspecialchars($settings['zaincash_number']!==''?$settings['zaincash_number']:'— لم يُحدد الرقم —', ENT_QUOTES, 'UTF-8') ?></b>
        <div><?= htmlspecialchars($settings['zaincash_name']!==''?$settings['zaincash_name']:'اسم المستلم', ENT_QUOTES, 'UTF-8') ?></div>
        <p style="margin:8px 0 0;color:#cbd5e1"><?= htmlspecialchars($settings['zaincash_note'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
    <div class="panel" style="margin-top:16px">
      <h2>طلبات التجديد (زين كاش)</h2>
      <?php
        $renewals = load_renewals();
        $anyR = false;
        foreach ($renewals as $rn) {
            if ($rn['status'] !== 'pending') continue;
            $anyR = true;
      ?>
      <div class="member">
        <div class="who"><div><strong><?= htmlspecialchars($rn['name'], ENT_QUOTES, 'UTF-8') ?></strong><small>@<?= htmlspecialchars($rn['username'], ENT_QUOTES, 'UTF-8') ?></small></div></div>
        <div class="meta"><span><?= htmlspecialchars($rn['package_name'], ENT_QUOTES, 'UTF-8') ?></span><span><?= number_format((int)$rn['amount']) ?> د.ع</span><span><?= htmlspecialchars($rn['created_at'], ENT_QUOTES, 'UTF-8') ?></span></div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm_renewal">
          <input type="hidden" name="rid" value="<?= htmlspecialchars($rn['id'], ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn ok" type="submit">تأكيد التجديد</button>
        </form>
      </div>
      <?php } if (!$anyR): ?>
        <p style="color:var(--muted);font-weight:700">لا توجد طلبات تجديد حالياً.</p>
      <?php endif; ?>
    </div>
    <div class="panel" style="margin-top:16px">
      <h2>الباقات المرتبطة بزين كاش</h2>
      <div class="pkgs">
        <?php foreach ($packages as $p): ?>
        <div class="pkg">
          <span class="pay">يدفع بزين كاش</span>
          <strong><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></strong>
          <div class="price"><?= number_format((int)$p['price']) ?> د.ع</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php else: ?>
    <div class="panel">
      <h2>تغيير دخول المدير</h2>
      <p style="color:var(--muted);font-weight:700">الحساب الحالي: <?= htmlspecialchars($me['username'], ENT_QUOTES, 'UTF-8') ?></p>
      <form method="post" class="grid2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_admin">
        <div class="field"><label>الاسم</label><input name="admin_name" value="<?= htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="field"><label>اسم المستخدم</label><input name="admin_username" value="<?= htmlspecialchars($me['username'], ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>كلمة مرور جديدة</label><input type="password" name="admin_password" placeholder="اتركها فارغة إذا لا تريد التغيير"></div>
        <div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="admin_password2"></div>
        <div class="field"><label>&nbsp;</label><button class="btn ok" type="submit">حفظ حساب المدير</button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
