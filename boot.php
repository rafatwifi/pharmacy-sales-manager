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
    return '.header{border-radius:0 0 32px 32px}'
        . '.userbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:0 0 10px}'
        . '.nav{border-radius:28px !important}'
        . '.nav a{border-radius:22px !important}'
        . '.search{border-radius:999px !important}'
        . '.card,.widget,.sell-card,.pharmacy-block{border-radius:28px !important}'
        . '.prof{position:relative;z-index:960}'
        . '.prof-btn{width:48px;height:48px;border:0;padding:0;border-radius:50%;overflow:hidden;cursor:pointer;background:linear-gradient(135deg,#38bdf8,#8b5cf6);box-shadow:0 8px 20px rgba(15,23,42,.28);display:grid;place-items:center}'
        . '.prof-btn img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}'
        . '.prof-btn span{color:#fff;font-weight:800;font-size:18px}'
        . '.prof-panel{display:none;position:absolute;top:calc(100% + 10px);left:0;width:min(340px,calc(100vw - 24px));background:linear-gradient(180deg,#111827,#0b1220);border:1px solid rgba(255,255,255,.12);border-radius:28px;padding:16px;box-shadow:0 24px 50px rgba(0,0,0,.4);z-index:970}'
        . '.prof.open .prof-panel{display:block}'
        . '.prof-head{display:flex;align-items:center;gap:12px;margin:0 0 14px}'
        . '.prof-photo{position:relative;width:72px;height:72px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#38bdf8,#8b5cf6);flex:0 0 auto}'
        . '.prof-photo img,.prof-photo .ph{width:100%;height:100%;object-fit:cover;display:grid;place-items:center;font-size:28px;font-weight:800}'
        . '.prof-photo label{position:absolute;inset:auto 0 0 0;background:rgba(2,6,23,.72);color:#fff;font-size:11px;font-weight:800;text-align:center;padding:4px 0;cursor:pointer}'
        . '.prof-head b{display:block;font-size:16px}'
        . '.prof-head small{color:#94a3b8;font-weight:700}'
        . '.prof-panel input{width:100%;padding:11px 12px;border:0;border-radius:16px;margin:0 0 8px;font-family:inherit;text-align:center}'
        . '.prof-panel label.lbl{display:block;color:#cbd5e1;font-size:12px;font-weight:800;margin:0 0 4px}'
        . '.prof-panel button,.prof-out{width:100%;border:0;border-radius:16px;padding:11px;font-weight:800;font-family:inherit;cursor:pointer;color:#fff}'
        . '.prof-save{background:linear-gradient(180deg,#38bdf8,#2563eb);margin:0 0 10px}'
        . '.prof-out{display:block;text-align:center;text-decoration:none;background:linear-gradient(180deg,#fb7185,#e11d48);margin-top:6px}'
        . '.flashbar{background:rgba(16,185,129,.18);color:#bbf7d0;border-radius:18px;padding:10px 12px;margin:0 0 12px;font-weight:800;text-align:center}';
}

function app_nav($active) {
    $user = current_user();
    $name = $user ? $user['name'] : '';
    $phone = ($user && !empty($user['phone'])) ? $user['phone'] : '';
    $isAdmin = is_admin_user($user);
    $avatar = user_avatar_url($user);
    $initial = htmlspecialchars(user_initial($user), ENT_QUOTES, 'UTF-8');
    $flash = take_flash();
    echo '<div class="userbar"><div class="logo">💊 Medical Sales</div>';
    echo '<div class="prof" id="profBox">';
    echo '<button type="button" class="prof-btn" id="profBtn" title="الملف الشخصي">';
    if ($avatar !== '') {
        echo '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="">';
    } else {
        echo '<span>' . $initial . '</span>';
    }
    echo '</button>';
    echo '<div class="prof-panel">';
    echo '<div class="prof-head">';
    echo '<form method="post" action="' . htmlspecialchars(url('profile'), ENT_QUOTES, 'UTF-8') . '" enctype="multipart/form-data" class="prof-photo">';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="photo">';
    echo '<input type="hidden" name="back" value="' . htmlspecialchars($active, ENT_QUOTES, 'UTF-8') . '">';
    if ($avatar !== '') echo '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="">';
    else echo '<div class="ph">' . $initial . '</div>';
    echo '<label>تغيير الصورة<input type="file" name="avatar" accept="image/*" onchange="this.form.submit()" style="display:none"></label>';
    echo '</form>';
    echo '<div><b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b><small>@' . htmlspecialchars($user ? $user['username'] : '', ENT_QUOTES, 'UTF-8') . '</small></div>';
    echo '</div>';
    if ($isAdmin) echo '<a class="prof-save" style="display:block;text-align:center;text-decoration:none;margin-bottom:10px;background:linear-gradient(180deg,#fbbf24,#d97706)" href="' . htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') . '">لوحة المدير</a>';
    echo '<form method="post" action="' . htmlspecialchars(url('profile'), ENT_QUOTES, 'UTF-8') . '">';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="info">';
    echo '<input type="hidden" name="back" value="' . htmlspecialchars($active, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label class="lbl">الاسم</label><input name="name" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" required>';
    echo '<label class="lbl">الهاتف</label><input name="phone" value="' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '" placeholder="رقم الهاتف">';
    echo '<button class="prof-save" type="submit">حفظ المعلومات</button>';
    echo '</form>';
    echo '<form method="post" action="' . htmlspecialchars(url('profile'), ENT_QUOTES, 'UTF-8') . '">';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="pass">';
    echo '<input type="hidden" name="back" value="' . htmlspecialchars($active, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label class="lbl">كلمة مرور جديدة</label><input type="password" name="new_pass" required>';
    echo '<label class="lbl">تأكيد كلمة المرور</label><input type="password" name="new_pass2" required>';
    echo '<button class="prof-save" type="submit">تعديل كلمة المرور</button>';
    echo '</form>';
    echo '<a class="prof-out" href="' . htmlspecialchars(url('logout'), ENT_QUOTES, 'UTF-8') . '">تسجيل خروج</a>';
    echo '</div></div></div>';
    if ($flash !== '') echo '<div class="flashbar">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</div>';
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
    echo '<script>(function(){var b=document.getElementById("profBox");var t=document.getElementById("profBtn");if(!b||!t)return;t.addEventListener("click",function(e){e.stopPropagation();b.classList.toggle("open");});document.addEventListener("click",function(e){if(!b.contains(e.target))b.classList.remove("open");});})();</script>';
}
