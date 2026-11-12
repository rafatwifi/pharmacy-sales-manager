<?php
require_once __DIR__ . '/boot.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    session_name('phsess');
    session_start();
}

license_guard();
app_install();

function users_file() { return STORAGE_PATH . '/users.json'; }
function packages_file() { return STORAGE_PATH . '/packages.json'; }

function load_users() { return load_json_file(users_file(), array()); }
function save_users($users) { return saveJson(users_file(), array_values($users)); }
function load_packages() { return load_json_file(packages_file(), array()); }
function save_packages($rows) { return saveJson(packages_file(), array_values($rows)); }
function settings_file() { return STORAGE_PATH . '/settings.json'; }
function load_settings() {
    $defaults = array(
        'zaincash_enabled' => 1,
        'zaincash_number' => '',
        'zaincash_name' => '',
        'zaincash_note' => 'حوّل مبلغ الباقة عبر زين كاش ثم أخبر الإدارة لتفعيل الاشتراك.'
    );
    $data = load_json_file(settings_file(), array());
    if (!is_array($data)) $data = array();
    return array_merge($defaults, $data);
}
function save_settings($data) { return saveJson(settings_file(), $data); }
function delete_user_account($id) {
    $user = find_user_by('id', $id);
    if (!$user || is_admin_user($user)) return false;
    $users = load_users();
    $out = array();
    foreach ($users as $u) {
        if ((string)$u['id'] !== (string)$id) $out[] = $u;
    }
    return save_users($out);
}

function find_user_by($field, $value) {
    $users = load_users();
    for ($i = 0; $i < count($users); $i++) {
        if (isset($users[$i][$field]) && (string)$users[$i][$field] === (string)$value) return $users[$i];
    }
    return null;
}

function find_user_index($id) {
    $users = load_users();
    for ($i = 0; $i < count($users); $i++) {
        if ((string)$users[$i]['id'] === (string)$id) return $i;
    }
    return -1;
}

function update_user($id, $patch) {
    $users = load_users();
    for ($i = 0; $i < count($users); $i++) {
        if ((string)$users[$i]['id'] === (string)$id) {
            foreach ($patch as $k => $v) $users[$i][$k] = $v;
            save_users($users);
            return $users[$i];
        }
    }
    return null;
}

function package_by_id($id) {
    $rows = load_packages();
    for ($i = 0; $i < count($rows); $i++) {
        if ((string)$rows[$i]['id'] === (string)$id) return $rows[$i];
    }
    return null;
}

function current_user() {
    if (empty($_SESSION['uid'])) return null;
    return find_user_by('id', $_SESSION['uid']);
}

function set_flash($text) { $_SESSION['flash'] = $text; }
function take_flash() {
    if (empty($_SESSION['flash'])) return '';
    $t = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $t;
}

function user_avatar_dir($userId) {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $dir = APP_ROOT . '/uploads/' . $id;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function user_avatar_url($user) {
    if (!$user || empty($user['avatar'])) return '';
    $fs = user_avatar_dir($user['id']) . '/' . $user['avatar'];
    if (!file_exists($fs)) return '';
    $base = app_base();
    return ($base === '' ? '' : $base) . '/uploads/' . rawurlencode($user['id']) . '/' . rawurlencode($user['avatar']) . '?t=' . filemtime($fs);
}

function user_initial($user) {
    $name = ($user && !empty($user['name'])) ? $user['name'] : 'م';
    if (function_exists('mb_substr')) return mb_substr($name, 0, 1, 'UTF-8');
    return substr($name, 0, 1);
}

function is_admin_user($user) {
    return $user && !empty($user['role']) && $user['role'] === 'admin';
}

function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['role'] = isset($user['role']) ? $user['role'] : 'rep';
}

function logout_user() {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_ok() {
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function user_expired($user) {
    if (!$user) return true;
    if (is_admin_user($user)) return false;
    if (empty($user['expires_at'])) return true;
    return strtotime($user['expires_at']) < time();
}

function subscription_ok($user) {
    if (!$user) return false;
    if (is_admin_user($user)) return true;
    if (empty($user['status']) || $user['status'] !== 'active') return false;
    return !user_expired($user);
}

function tenant_paths() {
    $user = current_user();
    $id = $user ? preg_replace('/[^a-zA-Z0-9_-]/', '', $user['id']) : '_guest';
    if ($id === '') $id = '_guest';
    $dir = STORAGE_PATH . '/tenants/' . $id;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $up = APP_ROOT . '/uploads/' . $id . '/products';
    if (!is_dir($up)) @mkdir($up, 0755, true);
    return array(
        'dir' => $dir,
        'data' => $dir . '/data.json',
        'sales' => $dir . '/sales.json',
        'meta' => $dir . '/meta.json',
        'upload_fs' => $up . '/',
        'upload_url' => 'uploads/' . $id . '/products/'
    );
}

function ensure_tenant_files($userId) {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $dir = STORAGE_PATH . '/tenants/' . $id;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $up = APP_ROOT . '/uploads/' . $id . '/products';
    if (!is_dir($up)) @mkdir($up, 0755, true);
    if (!file_exists($dir . '/data.json')) saveJson($dir . '/data.json', array());
    if (!file_exists($dir . '/sales.json')) saveJson($dir . '/sales.json', array());
    if (!file_exists($dir . '/meta.json')) {
        saveJson($dir . '/meta.json', array(
            'current_period' => 1,
            'cycles' => array(array('id' => 1, 'started_at' => date('Y-m-d H:i'), 'ended_at' => null))
        ));
    }
}

function app_require_login() {
    $user = current_user();
    if (!$user) redirect('login', array());
    if (!empty($user['status']) && $user['status'] === 'pending') redirect('pending', array());
    if (!empty($user['status']) && $user['status'] === 'rejected') {
        logout_user();
        redirect('login', array('err' => 'rejected'));
    }
    if (!empty($user['status']) && $user['status'] === 'paused') redirect('paused', array());
    if (!subscription_ok($user)) redirect('expired', array());
    return $user;
}

function app_require_admin() {
    $user = current_user();
    if (!$user) redirect('login', array());
    if (!is_admin_user($user)) redirect('dashboard', array());
    return $user;
}

function default_admin_user() {
    return array(
        'id' => 'admin',
        'username' => 'admin',
        'password' => password_hash('1234', PASSWORD_BCRYPT),
        'name' => 'المدير',
        'role' => 'admin',
        'status' => 'active',
        'package_id' => 'pro',
        'expires_at' => '2035-12-31 23:59',
        'created_at' => date('Y-m-d H:i'),
        'wait_token' => ''
    );
}

function default_omar_user() {
    return array(
        'id' => 'omar',
        'username' => 'omar',
        'password' => password_hash('1234', PASSWORD_BCRYPT),
        'name' => 'Omar',
        'role' => 'rep',
        'status' => 'active',
        'package_id' => 'pro',
        'expires_at' => '2035-12-31 23:59',
        'created_at' => date('Y-m-d H:i'),
        'wait_token' => ''
    );
}

function app_migrate_accounts() {
    $users = load_users();
    $changed = false;
    $hasAdmin = false;
    $hasOmar = false;
    for ($i = 0; $i < count($users); $i++) {
        if ($users[$i]['id'] === 'omar' || (isset($users[$i]['username']) && $users[$i]['username'] === 'omar')) {
            $hasOmar = true;
            if (!empty($users[$i]['role']) && $users[$i]['role'] === 'admin') {
                $users[$i]['role'] = 'rep';
                $users[$i]['status'] = 'active';
                if (empty($users[$i]['package_id'])) $users[$i]['package_id'] = 'pro';
                if (empty($users[$i]['expires_at'])) $users[$i]['expires_at'] = '2035-12-31 23:59';
                $changed = true;
            }
        }
        if ($users[$i]['id'] === 'admin' || (!empty($users[$i]['role']) && $users[$i]['role'] === 'admin')) {
            $hasAdmin = true;
        }
    }
    if (!$hasOmar) {
        $users[] = default_omar_user();
        $changed = true;
    }
    if (!$hasAdmin) {
        $users[] = default_admin_user();
        $changed = true;
    }
    if ($changed) save_users($users);
    ensure_tenant_files('omar');
    $lockFile = STORAGE_PATH . '/lock.json';
    $hosts = licensed_hosts();
    file_put_contents($lockFile, json_encode(array('hosts' => $hosts), JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod($lockFile, 0600);
}

function default_packages() {
    return array(
        array('id' => 'trial', 'name' => 'تجريبي', 'price' => 0, 'days' => 7, 'max_products' => 15, 'max_sales' => 80, 'active' => 1),
        array('id' => 'basic', 'name' => 'أساسي', 'price' => 25000, 'days' => 30, 'max_products' => 80, 'max_sales' => 800, 'active' => 1),
        array('id' => 'pro', 'name' => 'احترافي', 'price' => 100000, 'days' => 365, 'max_products' => 0, 'max_sales' => 0, 'active' => 1)
    );
}

function app_install() {
    $lockFile = STORAGE_PATH . '/lock.json';
    if (!file_exists($lockFile)) {
        file_put_contents($lockFile, json_encode(array(
            'hosts' => licensed_hosts()
        ), JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($lockFile, 0600);
    }
    if (!file_exists(packages_file())) {
        save_packages(default_packages());
    }
    if (!file_exists(users_file())) {
        save_users(array(default_admin_user(), default_omar_user()));
        ensure_tenant_files('omar');
        $seedDir = STORAGE_PATH . '/tenants/omar';
        $roots = array(
            'data.json' => APP_ROOT . '/data.json',
            'sales.json' => APP_ROOT . '/sales.json',
            'meta.json' => APP_ROOT . '/meta.json'
        );
        foreach ($roots as $name => $src) {
            $dest = $seedDir . '/' . $name;
            if (file_exists($src)) {
                $incoming = json_decode(file_get_contents($src), true);
                if (is_array($incoming) && count($incoming) > 0) {
                    saveJson($dest, $incoming);
                }
            }
        }
        $oldUp = APP_ROOT . '/uploads/products';
        $newUp = APP_ROOT . '/uploads/omar/products';
        if (is_dir($oldUp) && is_dir($newUp)) {
            $dh = opendir($oldUp);
            if ($dh) {
                while (($f = readdir($dh)) !== false) {
                    if ($f === '.' || $f === '..') continue;
                    if (is_file($oldUp . '/' . $f) && !file_exists($newUp . '/' . $f)) {
                        @copy($oldUp . '/' . $f, $newUp . '/' . $f);
                    }
                }
                closedir($dh);
            }
        }
    }
    app_migrate_accounts();
}
