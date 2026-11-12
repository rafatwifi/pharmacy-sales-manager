<?php
if (!defined('APP_BOOT')) {
    define('APP_BOOT', true);
}

define('APP_ROOT', __DIR__);
define('STORAGE_PATH', __DIR__ . '/storage');

if (!is_dir(STORAGE_PATH)) @mkdir(STORAGE_PATH, 0750, true);
if (!is_dir(STORAGE_PATH . '/tenants')) @mkdir(STORAGE_PATH . '/tenants', 0750, true);

function app_base() {
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    $dir = rtrim(dirname($script), '/');
    if ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') return '';
    return $dir;
}

function url($route) {
    $route = ltrim((string)$route, '/');
    $base = app_base();
    if ($route === '') return $base === '' ? '/' : $base . '/';
    return ($base === '' ? '' : $base) . '/' . $route;
}

function redirect($route, $params) {
    if (!is_array($params)) $params = array();
    $q = !empty($params) ? ('?' . http_build_query($params)) : '';
    header('Location: ' . url($route) . $q);
    exit;
}

function app_route() {
    if (!empty($_GET['r'])) {
        return trim((string)$_GET['r'], '/');
    }
    $uri = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $base = app_base();
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    $uri = trim($uri, '/');
    if (strlen($uri) > 4 && substr($uri, -4) === '.php') {
        $uri = substr($uri, 0, -4);
    }
    if ($uri === 'index' || $uri === 'index.php') $uri = '';
    if ($uri === 'reports') $uri = 'sales';
    return $uri;
}

function app_key_raw() {
    $file = STORAGE_PATH . '/app.key';
    if (!file_exists($file) || !filesize($file)) {
        $hex = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
        file_put_contents($file, $hex, LOCK_EX);
        @chmod($file, 0600);
    }
    $hex = trim((string)file_get_contents($file));
    $bin = @hex2bin($hex);
    return $bin ? $bin : hash('sha256', $hex, true);
}

function encrypt_payload($plain) {
    $key = app_key_raw();
    $iv = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return $plain;
    $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return 'ENC1:' . base64_encode($iv . $hmac . $cipher);
}

function decrypt_payload($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || strpos($raw, 'ENC1:') !== 0) return $raw;
    $bin = base64_decode(substr($raw, 5), true);
    if ($bin === false || strlen($bin) < 49) return '';
    $iv = substr($bin, 0, 16);
    $hmac = substr($bin, 16, 32);
    $cipher = substr($bin, 48);
    $key = app_key_raw();
    $calc = hash_hmac('sha256', $iv . $cipher, $key, true);
    if (!hash_equals($hmac, $calc)) return '';
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function saveJson($file, $data) {
    $payload = is_array($data) && array_keys($data) === range(0, count($data) - 1)
        ? array_values($data)
        : $data;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $ok = file_put_contents($file, encrypt_payload($json), LOCK_EX);
    @chmod($file, 0600);
    return $ok !== false;
}

function load_json_file($file, $default) {
    if (!is_array($default)) $default = array();
    if (!file_exists($file)) return $default;
    $raw = decrypt_payload(file_get_contents($file));
    if ($raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function license_ok() {
    if (php_sapi_name() === 'cli') return true;
    $allowed = array('127.0.0.1', '::1', 'localhost', '195.7.9.14');
    $lockFile = STORAGE_PATH . '/lock.json';
    if (file_exists($lockFile)) {
        $lock = json_decode(decrypt_payload(file_get_contents($lockFile)), true);
        if (is_array($lock) && !empty($lock['hosts']) && is_array($lock['hosts'])) {
            $allowed = $lock['hosts'];
        }
    }
    $ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';
    for ($i = 0; $i < count($allowed); $i++) {
        if ($allowed[$i] === $ip || $allowed[$i] === $host) return true;
    }
    return false;
}

function license_guard() {
    if (license_ok()) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>ترخيص</title></head><body style="font-family:Tahoma;background:#0f172a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh"><div style="text-align:center;max-width:420px"><h2>هذا النظام مرخّص لهذا السيرفر فقط</h2><p>النسخة المنسوخة غير صالحة للتشغيل.</p></div></body></html>';
    exit;
}

function app_nav_styles() {
    return '.userbar{display:flex;justify-content:space-between;align-items:center;gap:8px;margin:0 0 8px}'
        . '.userchip{display:flex;align-items:center;gap:8px;flex-wrap:wrap}'
        . '.userchip b{font-size:13px}'
        . '.userchip a,.userchip button{color:#fff;text-decoration:none;border:0;background:rgba(255,255,255,.14);border-radius:12px;padding:8px 12px;font-weight:800;font-family:inherit;font-size:12px;cursor:pointer}'
        . '.userchip a.admin{background:linear-gradient(180deg,rgba(250,204,21,.55),rgba(245,158,11,.35))}'
        . '.userchip a.out{background:rgba(225,29,72,.55)}';
}

function app_nav($active) {
    $user = current_user();
    $name = $user ? $user['name'] : '';
    $isAdmin = $user && !empty($user['role']) && $user['role'] === 'admin';
    echo '<div class="userbar"><div class="logo">💊 Medical Sales</div><div class="userchip">';
    echo '<b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
    if ($isAdmin) echo '<a class="admin" href="' . htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') . '">لوحة المدير</a>';
    echo '<a class="out" href="' . htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') . '">خروج</a>';
    echo '</div></div>';
    echo '<div class="nav">';
    $links = array(
        'dashboard' => array('الداشبورد', 'btn-nav-dash'),
        'products' => array('المنتجات', 'btn-nav-products'),
        'sales' => array('المبيعات', 'btn-nav-sales'),
        'pharmacies' => array('الصيدليات', 'btn-nav-pharmacies')
    );
    foreach ($links as $key => $info) {
        $cls = $info[1] . ($active === $key ? ' active' : '');
        echo '<a class="' . $cls . '" href="' . htmlspecialchars(url($key), ENT_QUOTES, 'UTF-8') . '">' . $info[0] . '</a>';
    }
    echo '</div>';
}
