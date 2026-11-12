<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$me = app_require_admin();
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'جلسة غير صالحة';
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
                'expires_at' => date('Y-m-d H:i', time() + max(1, $days) * 86400)
            ));
            ensure_tenant_files($uid);
            $msg = 'تمت الموافقة وتشغيل الحساب';
            $tab = 'users';
        } elseif ($action === 'reject') {
            update_user($uid, array('status' => 'rejected'));
            $msg = 'تم رفض الطلب';
        } elseif ($action === 'pause') {
            if ($uid !== 'omar') update_user($uid, array('status' => 'paused'));
            $msg = 'تم إيقاف المستخدم';
        } elseif ($action === 'resume') {
            $user = find_user_by('id', $uid);
            $pkgId = $user && !empty($user['package_id']) ? $user['package_id'] : (isset($_POST['package_id']) ? $_POST['package_id'] : 'trial');
            $pkg = package_by_id($pkgId);
            $days = $pkg && !empty($pkg['days']) ? (int)$pkg['days'] : 30;
            $exp = $user && !empty($user['expires_at']) && strtotime($user['expires_at']) > time()
                ? $user['expires_at']
                : date('Y-m-d H:i', time() + $days * 86400);
            update_user($uid, array('status' => 'active', 'package_id' => $pkgId, 'expires_at' => $exp));
            $msg = 'تم تشغيل المستخدم';
        } elseif ($action === 'assign') {
            $pkgId = isset($_POST['package_id']) ? $_POST['package_id'] : '';
            $pkg = package_by_id($pkgId);
            if ($pkg) {
                $days = !empty($pkg['days']) ? (int)$pkg['days'] : 30;
                update_user($uid, array(
                    'package_id' => $pkgId,
                    'expires_at' => date('Y-m-d H:i', time() + max(1, $days) * 86400),
                    'status' => 'active'
                ));
                $msg = 'تم تعيين الباقة وتجديد الاشتراك';
            }
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
                'active' => isset($_POST['pkg_active']) ? 1 : 0
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
        }
    }
}

$users = load_users();
$packages = load_packages();
$pending = 0; $active = 0; $paused = 0;
foreach ($users as $u) {
    if ($u['status'] === 'pending') $pending++;
    if ($u['status'] === 'active') $active++;
    if ($u['status'] === 'paused') $paused++;
}
function pkg_name($id) {
    $p = package_by_id($id);
    return $p ? $p['name'] : '—';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#111827,#020617 55%);color:#fff;min-height:100vh}
.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}
.side{background:rgba(15,23,42,.92);border-left:1px solid rgba(255,255,255,.08);padding:18px 14px;position:sticky;top:0;height:100vh}
.brand{font-size:20px;font-weight:800;margin:0 0 18px}
.side a{display:block;color:#e2e8f0;text-decoration:none;padding:12px 12px;border-radius:14px;font-weight:800;margin:0 0 6px}
.side a.active,.side a:hover{background:rgba(255,255,255,.1)}
.main{padding:18px}
.top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 16px}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 18px}
.stat{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:16px}
.stat b{display:block;font-size:26px;margin-top:6px}
.card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:22px;padding:16px;margin:0 0 16px}
.table{width:100%;border-collapse:separate;border-spacing:0 8px}
td,th{padding:10px;text-align:center}
tr td{background:rgba(255,255,255,.08);font-size:13px}
input,select{width:100%;padding:10px;border:0;border-radius:12px;font-family:inherit}
.btn{border:0;border-radius:12px;padding:8px 10px;color:#fff;font-weight:800;font-family:inherit;cursor:pointer}
.ok{background:#16a34a}.warn{background:#d97706}.bad{background:#e11d48}.info{background:#2563eb}
.rowacts{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;align-items:end}
.msg{background:#16a34a;padding:10px;border-radius:12px;margin:0 0 12px;font-weight:800}
.err{background:#e11d48;padding:10px;border-radius:12px;margin:0 0 12px;font-weight:800}
.mob{display:none}
.user-card{display:none}
@media(max-width:900px){
  .shell{grid-template-columns:1fr}
  .side{position:relative;height:auto;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
  .side a{margin:0}
  .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .table-wrap{display:none}
  .user-card{display:block;background:rgba(255,255,255,.08);border-radius:18px;padding:14px;margin:0 0 10px}
  .mob{display:block}
}
</style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand">👑 لوحة المدير</div>
    <a class="<?= $tab==='users'?'active':'' ?>" href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>?tab=users">المستخدمون</a>
    <a class="<?= $tab==='packages'?'active':'' ?>" href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>?tab=packages">الباقات</a>
    <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">نظام المبيعات</a>
    <a href="<?= htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') ?>">خروج</a>
  </aside>
  <div class="main">
    <div class="top">
      <h2 style="margin:0">مرحباً <?= htmlspecialchars($me['name'], ENT_QUOTES, 'UTF-8') ?></h2>
      <span style="color:#cbd5e1;font-weight:700">إدارة المندوبين والاشتراكات</span>
    </div>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="grid">
      <div class="stat">كل المستخدمين<b><?= count($users) ?></b></div>
      <div class="stat">بانتظار الموافقة<b><?= $pending ?></b></div>
      <div class="stat">نشطون<b><?= $active ?></b></div>
      <div class="stat">متوقفون<b><?= $paused ?></b></div>
    </div>

    <?php if ($tab !== 'packages'): ?>
    <div class="card">
      <h3>المندوبون</h3>
      <div class="table-wrap">
      <table class="table">
        <tr><th>الاسم</th><th>المستخدم</th><th>الحالة</th><th>الباقة</th><th>ينتهي</th><th>إجراء</th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($u['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(pkg_name(isset($u['package_id'])?$u['package_id']:''), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(isset($u['expires_at'])?$u['expires_at']:'—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?php include_user_actions($u, $packages); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <?php foreach ($users as $u): ?>
      <div class="user-card">
        <b><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></b>
        <div><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($u['status'], ENT_QUOTES, 'UTF-8') ?></div>
        <div>الباقة: <?= htmlspecialchars(pkg_name(isset($u['package_id'])?$u['package_id']:''), ENT_QUOTES, 'UTF-8') ?></div>
        <div>ينتهي: <?= htmlspecialchars(isset($u['expires_at'])?$u['expires_at']:'—', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="margin-top:8px"><?php include_user_actions($u, $packages); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
      <h3>إضافة / تعديل باقة</h3>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_pkg">
        <input name="id" placeholder="رمز الباقة (basic)">
        <input name="name" placeholder="اسم الباقة" required>
        <input type="number" name="price" placeholder="السعر د.ع" value="0">
        <input type="number" name="days" placeholder="المدة بالأيام" value="30">
        <input type="number" name="max_products" placeholder="حد المنتجات 0=مفتوح" value="0">
        <input type="number" name="max_sales" placeholder="حد المبيعات 0=مفتوح" value="0">
        <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="pkg_active" checked> مفعّلة</label>
        <button class="btn ok" type="submit">حفظ الباقة</button>
      </form>
    </div>
    <div class="card">
      <h3>الباقات الحالية</h3>
      <?php foreach ($packages as $p): ?>
      <div class="user-card" style="display:block">
        <b><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></b>
        <div><?= number_format((int)$p['price']) ?> د.ع — <?= (int)$p['days'] ?> يوم</div>
        <div>منتجات: <?= ((int)$p['max_products']===0?'مفتوح':(int)$p['max_products']) ?> | مبيعات: <?= ((int)$p['max_sales']===0?'مفتوح':(int)$p['max_sales']) ?></div>
        <form method="post" style="margin-top:8px" onsubmit="return confirm('حذف الباقة؟');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_pkg">
          <input type="hidden" name="id" value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn bad">حذف</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
<?php
function include_user_actions($u, $packages) {
    if ($u['id'] === 'omar') { echo '<span>مدير</span>'; return; }
    echo '<div class="rowacts">';
    if ($u['status'] === 'pending') {
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="uid" value="'.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'">';
        echo '<input type="hidden" name="action" value="approve">';
        echo '<select name="package_id">';
        foreach ($packages as $p) echo '<option value="'.htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8').'</option>';
        echo '</select><button class="btn ok">موافقة</button></form>';
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="uid" value="'.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="action" value="reject"><button class="btn bad">رفض</button></form>';
    } else {
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="uid" value="'.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'">';
        echo '<input type="hidden" name="action" value="assign">';
        echo '<select name="package_id">';
        foreach ($packages as $p) {
            $sel = (!empty($u['package_id']) && $u['package_id']===$p['id']) ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8').'"'.$sel.'>'.htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8').'</option>';
        }
        echo '</select><button class="btn info">تعيين باقة</button></form>';
        if ($u['status'] === 'paused') {
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="uid" value="'.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="action" value="resume"><button class="btn ok">تشغيل</button></form>';
        } else {
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="uid" value="'.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="action" value="pause"><button class="btn warn">إيقاف</button></form>';
        }
    }
    echo '</div>';
}
