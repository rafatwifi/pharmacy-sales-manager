<?php
require_once __DIR__ . '/auth.php';

$route = app_route();
if ($route === '') {
    $u = current_user();
    if ($u && is_admin_user($u)) redirect('admin', array());
    if ($u && subscription_ok($u)) redirect('dashboard', array());
    if ($u && isset($u['status']) && $u['status'] === 'pending') redirect('pending', array());
    redirect('login', array());
}

$map = array(
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'pending' => 'pages/pending.php',
    'paused' => 'pages/paused.php',
    'expired' => 'pages/expired.php',
    'admin' => 'pages/admin.php',
    'profile' => 'pages/profile.php',
    'dashboard' => 'dashboard.php',
    'products' => 'products.php',
    'sales' => 'reports.php',
    'pharmacies' => 'pharmacies.php',
    'subscribe' => 'pages/subscribe.php',
    'about' => 'pages/about.php'
);

if ($route === 'logout') {
    logout_user();
    redirect('login', array());
}

if ($route === 'api/wait') {
    header('Content-Type: application/json; charset=utf-8');
    $token = isset($_GET['token']) ? $_GET['token'] : (isset($_SESSION['wait_token']) ? $_SESSION['wait_token'] : '');
    $user = $token !== '' ? find_user_by('wait_token', $token) : null;
    if (!$user) {
        echo json_encode(array('status' => 'unknown'));
        exit;
    }
    if ($user['status'] === 'active' && subscription_ok($user)) {
        login_user($user);
        echo json_encode(array('status' => 'active', 'redirect' => url('dashboard')));
        exit;
    }
    if ($user['status'] === 'rejected') {
        echo json_encode(array('status' => 'rejected'));
        exit;
    }
    if ($user['status'] === 'paused') {
        echo json_encode(array('status' => 'paused'));
        exit;
    }
    echo json_encode(array('status' => $user['status']));
    exit;
}

if (!isset($map[$route])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>غير موجود</title></head><body style="font-family:Tahoma;background:#020617;color:#fff;text-align:center;padding:80px"><h2>الصفحة غير موجودة</h2><p><a href="' . htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') . '" style="color:#f9a8d4">العودة</a></p></body></html>';
    exit;
}

require APP_ROOT . '/' . $map[$route];
