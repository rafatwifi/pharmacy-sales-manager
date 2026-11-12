<?php
if (!defined('APP_BOOT')) { require_once dirname(__DIR__) . '/auth.php'; }
$user = app_require_login();
$allowed = array('dashboard', 'products', 'sales', 'pharmacies');
$back = isset($_POST['back']) ? $_POST['back'] : 'dashboard';
if (!in_array($back, $allowed, true)) $back = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    redirect($back, array());
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($action === 'info') {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    if ($name === '') {
        set_flash('أدخل الاسم');
        redirect($back, array());
    }
    update_user($user['id'], array('name' => $name, 'phone' => $phone));
    set_flash('تم حفظ المعلومات الشخصية');
} elseif ($action === 'pass') {
    $p1 = isset($_POST['new_pass']) ? $_POST['new_pass'] : '';
    $p2 = isset($_POST['new_pass2']) ? $_POST['new_pass2'] : '';
    if (strlen($p1) < 4) {
        set_flash('كلمة المرور قصيرة');
        redirect($back, array());
    }
    if ($p1 !== $p2) {
        set_flash('تأكيد كلمة المرور غير مطابق');
        redirect($back, array());
    }
    update_user($user['id'], array('password' => password_hash($p1, PASSWORD_BCRYPT)));
    set_flash('تم تعديل كلمة المرور');
} elseif ($action === 'photo') {
    if (empty($_FILES['avatar']['tmp_name']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        set_flash('اختر صورة للرفع');
        redirect($back, array());
    }
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $allow = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    if (!in_array($ext, $allow, true)) {
        set_flash('صيغة الصورة غير مدعومة');
        redirect($back, array());
    }
    $info = @getimagesize($_FILES['avatar']['tmp_name']);
    if ($info === false) {
        set_flash('الملف ليس صورة صالحة');
        redirect($back, array());
    }
    $dir = user_avatar_dir($user['id']);
    $name = 'avatar_' . uniqid() . '.' . $ext;
    if (!empty($user['avatar']) && file_exists($dir . '/' . $user['avatar'])) {
        @unlink($dir . '/' . $user['avatar']);
    }
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $name)) {
        set_flash('تعذر رفع الصورة');
        redirect($back, array());
    }
    @chmod($dir . '/' . $name, 0644);
    update_user($user['id'], array('avatar' => $name));
    set_flash('تم تحديث الصورة الشخصية');
}
redirect($back, array());
