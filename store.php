<?php
require_once __DIR__ . '/auth.php';
if (empty($GLOBALS['APP_SKIP_LOGIN'])) {
    app_require_login();
}

$paths = tenant_paths();
$dataFile = $paths['data'];
$salesFile = $paths['sales'];
$metaFile = $paths['meta'];
$uploadPath = $paths['upload_url'];
$uploadFs = $paths['upload_fs'];

if (!file_exists($dataFile)) saveJson($dataFile, array());
if (!file_exists($salesFile)) saveJson($salesFile, array());
if (!file_exists($metaFile)) {
    saveJson($metaFile, array(
        'current_period' => 1,
        'cycles' => array(array('id' => 1, 'started_at' => date('Y-m-d H:i'), 'ended_at' => null))
    ));
}

function loadSales() {
    global $salesFile;
    return load_json_file($salesFile, array());
}

function loadProducts() {
    global $dataFile;
    return load_json_file($dataFile, array());
}

function loadMeta() {
    global $metaFile;
    $default = array(
        'current_period' => 1,
        'cycles' => array(array('id' => 1, 'started_at' => date('Y-m-d H:i'), 'ended_at' => null))
    );
    $meta = load_json_file($metaFile, $default);
    if (!isset($meta['current_period']) || (int)$meta['current_period'] < 1) {
        saveJson($metaFile, $default);
        return $default;
    }
    $meta['current_period'] = (int)$meta['current_period'];
    if (empty($meta['cycles']) || !is_array($meta['cycles'])) {
        $meta['cycles'] = array(array('id' => $meta['current_period'], 'started_at' => date('Y-m-d H:i'), 'ended_at' => null));
    }
    return $meta;
}

function saveMeta($meta) {
    global $metaFile;
    return saveJson($metaFile, $meta);
}

function currentPeriod() {
    return (int) loadMeta()['current_period'];
}

function salePeriod($sale) {
    return isset($sale['period']) ? (int)$sale['period'] : 1;
}

function isCurrentSale($sale, $period) {
    if (!empty($sale['closed'])) return false;
    return salePeriod($sale) === (int)$period;
}

function salesInPeriod($sales, $period) {
    $out = array();
    foreach ($sales as $s) {
        if (isCurrentSale($s, $period)) $out[] = $s;
    }
    return $out;
}

function salesBeforePeriod($sales, $period) {
    $out = array();
    foreach ($sales as $s) {
        if (!isCurrentSale($s, $period)) $out[] = $s;
    }
    return $out;
}

function startNewCycle() {
    global $salesFile;
    $sales = loadSales();
    $meta = loadMeta();
    $now = date('Y-m-d H:i');
    $old = (int)$meta['current_period'];

    foreach ($sales as &$s) {
        if (!isset($s['period']) || (int)$s['period'] < 1) {
            $s['period'] = $old;
        }
        $s['closed'] = 1;
    }
    unset($s);

    if (!saveJson($salesFile, array_values($sales))) {
        return false;
    }

    if (empty($meta['cycles']) || !is_array($meta['cycles'])) {
        $meta['cycles'] = array();
    }
    foreach ($meta['cycles'] as &$cycle) {
        if ((int)$cycle['id'] === $old && empty($cycle['ended_at'])) {
            $cycle['ended_at'] = $now;
        }
    }
    unset($cycle);
    $new = $old + 1;
    $meta['current_period'] = $new;
    $meta['cycles'][] = array('id' => $new, 'started_at' => $now, 'ended_at' => null);
    saveMeta($meta);
    return true;
}

function buildPharmacyReport($sales) {
    $report = array();
    foreach ($sales as $s) {
        $pharmacyName = trim((string)(isset($s['pharmacy']) ? $s['pharmacy'] : ''));
        if ($pharmacyName === '') $pharmacyName = 'غير محدد';
        $productName = isset($s['product']) ? $s['product'] : '—';
        if (!isset($report[$pharmacyName])) {
            $report[$pharmacyName] = array('products' => array(), 'qty' => 0, 'total' => 0);
        }
        if (!isset($report[$pharmacyName]['products'][$productName])) {
            $report[$pharmacyName]['products'][$productName] = array('qty' => 0, 'total' => 0);
        }
        $qty = (int)$s['qty'];
        $total = (float)$s['total'];
        $report[$pharmacyName]['products'][$productName]['qty'] += $qty;
        $report[$pharmacyName]['products'][$productName]['total'] += $total;
        $report[$pharmacyName]['qty'] += $qty;
        $report[$pharmacyName]['total'] += $total;
    }
    return $report;
}

function package_limits() {
    $user = current_user();
    $pkg = $user && !empty($user['package_id']) ? package_by_id($user['package_id']) : null;
    if (!$pkg || (!empty($user['role']) && $user['role'] === 'admin')) {
        return array('max_products' => 0, 'max_sales' => 0);
    }
    return array(
        'max_products' => isset($pkg['max_products']) ? (int)$pkg['max_products'] : 0,
        'max_sales' => isset($pkg['max_sales']) ? (int)$pkg['max_sales'] : 0
    );
}
