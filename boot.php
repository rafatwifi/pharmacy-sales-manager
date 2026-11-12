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

function licensed_hosts() {
    return array(
        '127.0.0.1',
        '::1',
        'localhost',
        '195.7.9.14',
        'admin.isp-wifi.com',
        'isp-wifi.com'
    );
}

function request_host_names() {
    $out = array();
    $keys = array('HTTP_HOST', 'SERVER_NAME', 'HTTP_X_FORWARDED_HOST', 'SERVER_ADDR');
    for ($i = 0; $i < count($keys); $i++) {
        $k = $keys[$i];
        if (empty($_SERVER[$k])) continue;
        $parts = preg_split('/\s*,\s*/', (string)$_SERVER[$k]);
        for ($j = 0; $j < count($parts); $j++) {
            $p = strtolower(preg_replace('/:\d+$/', '', trim($parts[$j])));
            if ($p !== '') $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

function license_ok() {
    if (php_sapi_name() === 'cli') return true;
    $allowed = licensed_hosts();
    $lockFile = STORAGE_PATH . '/lock.json';
    if (file_exists($lockFile)) {
        $raw = file_get_contents($lockFile);
        $lock = json_decode(decrypt_payload($raw), true);
        if (!is_array($lock)) $lock = json_decode($raw, true);
        if (is_array($lock) && !empty($lock['hosts']) && is_array($lock['hosts'])) {
            $allowed = array_values(array_unique(array_merge($allowed, $lock['hosts'])));
        }
    }
    $seen = request_host_names();
    for ($i = 0; $i < count($seen); $i++) {
        $h = $seen[$i];
        for ($j = 0; $j < count($allowed); $j++) {
            $a = strtolower($allowed[$j]);
            if ($h === $a) return true;
            if ($a === 'isp-wifi.com' && strlen($h) > 13 && substr($h, -13) === '.isp-wifi.com') return true;
        }
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
    return '.header{position:sticky;top:8px;z-index:850;overflow:visible !important;margin:0 0 16px !important;padding:10px 12px !important;border-radius:28px !important;background:rgba(2,6,23,.94) !important;border:1px solid rgba(255,255,255,.14) !important;box-shadow:0 10px 28px rgba(0,0,0,.22) !important;gap:10px !important}'
        . '.userbar{display:flex;align-items:center;justify-content:flex-start;gap:10px;margin:0}'
        . '.nav{border-radius:999px !important;overflow-x:auto}'
        . '.nav a{border-radius:999px !important;font-size:12px !important}'
        . '.logo{display:flex;align-items:center;gap:8px;font-size:18px !important;font-weight:800;line-height:1;min-width:0}'
        . '.logo img{width:32px;height:32px;border-radius:50%;object-fit:cover;flex:0 0 auto}'
        . '.search{border-radius:999px !important}'
        . '.card,.widget,.sell-card,.pharmacy-block,.hero,.box,.zain{border-radius:28px !important}'
        . '.prof{position:relative;z-index:960;flex:0 0 auto;width:40px;height:40px}'
        . '.prof-btn{width:40px;height:40px;padding:0;margin:0;border:0;border-radius:50%;overflow:hidden;cursor:pointer;background:linear-gradient(135deg,#38bdf8,#8b5cf6);display:block;line-height:0;-webkit-appearance:none;appearance:none;box-shadow:0 0 0 2px rgba(255,255,255,.42)}'
        . '.prof-btn img{width:40px;height:40px;object-fit:cover;border-radius:50%;display:block}'
        . '.prof-btn span{color:#fff;font-weight:800;font-size:15px;line-height:40px;width:40px;height:40px;display:block;text-align:center}'
        . '.prof-panel{display:none;position:absolute;top:calc(100% + 10px);right:0;left:auto;width:236px;background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:6px;box-shadow:0 18px 40px rgba(0,0,0,.38);z-index:970}'
        . '.prof.open .prof-panel{display:block}'
        . '.prof-mini{display:flex;align-items:center;gap:8px;padding:8px 10px}'
        . '.prof-mini .dot{width:32px;height:32px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#38bdf8,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 auto}'
        . '.prof-mini .dot img{width:100%;height:100%;object-fit:cover;display:block}'
        . '.prof-mini b{display:block;font-size:13px;line-height:1.2}'
        . '.prof-mini small{color:#94a3b8;font-weight:700}'
        . '.prof-panel details{border-top:1px solid rgba(255,255,255,.08)}'
        . '.prof-panel summary{list-style:none;cursor:pointer;padding:9px 12px;font-weight:800;font-size:13px;border-radius:12px}'
        . '.prof-panel summary::-webkit-details-marker{display:none}'
        . '.prof-panel summary:after{content:"▾";float:left;opacity:.7}'
        . '.prof-panel details[open] summary{background:rgba(255,255,255,.06)}'
        . '.drop{padding:0 10px 10px}'
        . '.prof-panel input{width:100%;padding:9px 10px;border:0;border-radius:12px;margin:0 0 7px;font-family:inherit;text-align:center}'
        . '.prof-panel input[type=file]{display:none}'
        . '.pick{display:block;text-align:center;background:rgba(255,255,255,.08);border-radius:12px;padding:9px;font-weight:800;cursor:pointer}'
        . '.prof-panel button,.prof-out{width:100%;border:0;border-radius:12px;padding:9px;font-weight:800;font-family:inherit;cursor:pointer;color:#fff}'
        . '.prof-save{background:linear-gradient(180deg,#38bdf8,#2563eb)}'
        . '.prof-out{display:block;text-align:center;text-decoration:none;background:linear-gradient(180deg,#fb7185,#e11d48);margin-top:4px}'
        . '.flashbar{background:rgba(16,185,129,.18);color:#bbf7d0;border-radius:16px;padding:8px 10px;margin:8px 0 0;font-weight:800;text-align:center}'
        . '.btn-nav-sub.active{background:linear-gradient(180deg,rgba(125,211,252,.55),rgba(14,165,233,.34))}'
        . '.btn-nav-about.active{background:linear-gradient(180deg,rgba(167,139,250,.55),rgba(124,58,237,.34))}';
}

function app_nav($active) {
    $user = current_user();
    $name = $user ? $user['name'] : '';
    $phone = ($user && !empty($user['phone'])) ? $user['phone'] : '';
    $isAdmin = is_admin_user($user);
    $avatar = user_avatar_url($user);
    $initial = htmlspecialchars(user_initial($user), ENT_QUOTES, 'UTF-8');
    $flash = take_flash();
    $set = load_settings();
    $siteName = !empty($set['site_name']) ? $set['site_name'] : 'Medical Sales';
    $siteLogo = site_logo_url();
    $back = htmlspecialchars($active, ENT_QUOTES, 'UTF-8');
    $actionUrl = htmlspecialchars(url('profile'), ENT_QUOTES, 'UTF-8');
    $avatarHtml = $avatar !== ''
        ? '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="">'
        : '<span>' . $initial . '</span>';
    $dotHtml = $avatar !== ''
        ? '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="">'
        : $initial;

    echo '<div class="userbar">';
    echo '<div class="prof" id="profBox">';
    echo '<button type="button" class="prof-btn" id="profBtn" title="الملف الشخصي">' . $avatarHtml . '</button>';
    echo '<div class="prof-panel">';
    echo '<div class="prof-mini"><div class="dot">' . $dotHtml . '</div>';
    echo '<div><b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b><small dir="ltr">@' . htmlspecialchars($user ? $user['username'] : '', ENT_QUOTES, 'UTF-8') . '</small></div></div>';
    if ($isAdmin) {
        echo '<a class="prof-save" style="display:block;text-align:center;text-decoration:none;margin:0 8px 8px;background:linear-gradient(180deg,#fbbf24,#d97706)" href="' . htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') . '">لوحة المدير</a>';
    }
    echo '<details><summary>الصورة الشخصية</summary><div class="drop"><form method="post" action="' . $actionUrl . '" enctype="multipart/form-data">';
    echo csrf_field() . '<input type="hidden" name="action" value="photo"><input type="hidden" name="back" value="' . $back . '">';
    echo '<label class="pick">اختيار صورة<input type="file" name="avatar" accept="image/*" onchange="this.form.submit()"></label></form></div></details>';
    echo '<details><summary>المعلومات الشخصية</summary><div class="drop"><form method="post" action="' . $actionUrl . '">';
    echo csrf_field() . '<input type="hidden" name="action" value="info"><input type="hidden" name="back" value="' . $back . '">';
    echo '<input name="name" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" placeholder="الاسم" required>';
    echo '<input name="phone" value="' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '" placeholder="الهاتف">';
    echo '<button class="prof-save" type="submit">حفظ</button></form></div></details>';
    echo '<details><summary>كلمة المرور</summary><div class="drop"><form method="post" action="' . $actionUrl . '">';
    echo csrf_field() . '<input type="hidden" name="action" value="pass"><input type="hidden" name="back" value="' . $back . '">';
    echo '<input type="password" name="new_pass" placeholder="كلمة مرور جديدة" required>';
    echo '<input type="password" name="new_pass2" placeholder="تأكيد كلمة المرور" required>';
    echo '<button class="prof-save" type="submit">تحديث</button></form></div></details>';
    echo '<a class="prof-out" href="' . htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') . '">تسجيل خروج</a>';
    echo '</div></div>';
    echo '<div class="logo">';
    if ($siteLogo !== '') echo '<img src="' . htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') . '" alt="">';
    else echo '💊 ';
    echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '</div>';
    if ($flash !== '') echo '<div class="flashbar">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<div class="nav">';
    $links = array(
        'dashboard' => array('الداشبورد', 'btn-nav-dash'),
        'products' => array('المنتجات', 'btn-nav-products'),
        'sales' => array('المبيعات', 'btn-nav-sales'),
        'pharmacies' => array('الصيدليات', 'btn-nav-pharmacies'),
        'subscribe' => array('الاشتراك', 'btn-nav-sub'),
        'about' => array('عن الموقع', 'btn-nav-about')
    );
    foreach ($links as $key => $info) {
        $cls = $info[1] . ($active === $key ? ' active' : '');
        echo '<a class="' . $cls . '" href="' . htmlspecialchars(url($key), ENT_QUOTES, 'UTF-8') . '">' . $info[0] . '</a>';
    }
    echo '</div>';
    echo '<script>(function(){var b=document.getElementById("profBox");var t=document.getElementById("profBtn");if(!b||!t)return;t.addEventListener("click",function(e){e.stopPropagation();b.classList.toggle("open");});document.addEventListener("click",function(e){if(!b.contains(e.target))b.classList.remove("open");});var ds=b.querySelectorAll("details");for(var i=0;i<ds.length;i++){ds[i].addEventListener("toggle",function(){if(!this.open)return;for(var j=0;j<ds.length;j++){if(ds[j]!==this)ds[j].open=false;}});}})();</script>';
}
