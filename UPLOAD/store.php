<?php
$dataFile = __DIR__ . '/data.json';
$salesFile = __DIR__ . '/sales.json';
$metaFile = __DIR__ . '/meta.json';

if (!file_exists($dataFile)) file_put_contents($dataFile, '[]');
if (!file_exists($salesFile)) file_put_contents($salesFile, '[]');

function saveJson($file, $data) {
    $payload = is_array($data) && array_keys($data) === range(0, count($data) - 1)
        ? array_values($data)
        : $data;
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function loadMeta() {
    global $metaFile;
    $default = [
        'current_period' => 1,
        'cycles' => [
            ['id' => 1, 'started_at' => date('Y-m-d H:i'), 'ended_at' => null]
        ]
    ];
    if (!file_exists($metaFile)) {
        saveJson($metaFile, $default);
        return $default;
    }
    $meta = json_decode(file_get_contents($metaFile), true);
    if (!is_array($meta) || empty($meta['current_period'])) {
        saveJson($metaFile, $default);
        return $default;
    }
    if (empty($meta['cycles']) || !is_array($meta['cycles'])) {
        $meta['cycles'] = [['id' => (int)$meta['current_period'], 'started_at' => date('Y-m-d H:i'), 'ended_at' => null]];
    }
    return $meta;
}

function saveMeta($meta) {
    global $metaFile;
    saveJson($metaFile, $meta);
}

function currentPeriod() {
    return (int) loadMeta()['current_period'];
}

function salePeriod($sale) {
    return isset($sale['period']) ? (int)$sale['period'] : 1;
}

function salesInPeriod($sales, $period) {
    $out = [];
    foreach ($sales as $s) {
        if (salePeriod($s) === (int)$period) $out[] = $s;
    }
    return $out;
}

function salesBeforePeriod($sales, $period) {
    $out = [];
    foreach ($sales as $s) {
        if (salePeriod($s) < (int)$period) $out[] = $s;
    }
    return $out;
}

function startNewCycle() {
    $meta = loadMeta();
    $now = date('Y-m-d H:i');
    $old = (int)$meta['current_period'];
    foreach ($meta['cycles'] as &$cycle) {
        if ((int)$cycle['id'] === $old && empty($cycle['ended_at'])) {
            $cycle['ended_at'] = $now;
        }
    }
    unset($cycle);
    $new = $old + 1;
    $meta['current_period'] = $new;
    $meta['cycles'][] = ['id' => $new, 'started_at' => $now, 'ended_at' => null];
    saveMeta($meta);
    return $new;
}

function buildPharmacyReport($sales) {
    $report = [];
    foreach ($sales as $s) {
        $pharmacyName = trim((string)($s['pharmacy'] ?? ''));
        if ($pharmacyName === '') $pharmacyName = 'غير محدد';
        $productName = $s['product'] ?? '—';
        if (!isset($report[$pharmacyName])) {
            $report[$pharmacyName] = ['products' => [], 'qty' => 0, 'total' => 0];
        }
        if (!isset($report[$pharmacyName]['products'][$productName])) {
            $report[$pharmacyName]['products'][$productName] = ['qty' => 0, 'total' => 0];
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
