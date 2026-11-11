<?php
require_once __DIR__ . '/store.php';

$sales = json_decode(file_get_contents($salesFile), true) ?: [];
$period = currentPeriod();
$currentReport = buildPharmacyReport(salesInPeriod($sales, $period));
$oldReport = buildPharmacyReport(salesBeforePeriod($sales, $period));

$pharmacyNames = array_unique(array_merge(array_keys($currentReport), array_keys($oldReport)));
usort($pharmacyNames, function ($a, $b) use ($currentReport, $oldReport) {
    $aNow = $currentReport[$a]['total'] ?? 0;
    $bNow = $currentReport[$b]['total'] ?? 0;
    if ($aNow !== $bNow) return $bNow <=> $aNow;
    $aOld = $oldReport[$a]['total'] ?? 0;
    $bOld = $oldReport[$b]['total'] ?? 0;
    return $bOld <=> $aOld;
});

function renderPharmacyProducts($info) {
    if (empty($info) || empty($info['products'])) {
        echo '<p style="color:#cbd5e1;margin:0;font-size:13px;">لا توجد مبيعات في هذا القسم.</p>';
        return;
    }
    echo '<div class="pharmacy-meta"><span>الكمية: ' . number_format($info['qty']) . ' قطعة</span><span>الإجمالي: ' . number_format($info['total']) . ' د.ع</span></div>';
    echo '<div class="table-wrap report-wrap"><table class="report-table"><tr><th>المنتج</th><th>الكمية</th><th>القيمة</th></tr>';
    foreach ($info['products'] as $productName => $pr) {
        echo '<tr><td>' . htmlspecialchars($productName) . '</td><td>' . number_format($pr['qty']) . ' قطعة</td><td>' . number_format($pr['total']) . ' د.ع</td></tr>';
    }
    echo '</table></div>';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الصيدليات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:1300px;margin:auto;padding:16px}
.header{position:sticky;top:0;z-index:850;display:flex;flex-direction:column;align-items:stretch;gap:10px;margin:0 0 16px;padding:10px 0 12px;background:linear-gradient(180deg,rgba(2,6,23,.96),rgba(2,6,23,.84) 78%,transparent);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.logo{font-size:26px;font-weight:bold}
.nav{display:flex;flex-wrap:nowrap;gap:4px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:5px}
.nav a{flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e2e8f0;text-decoration:none;padding:11px 8px;border-radius:14px;font-weight:800;font-size:14px;border:0;background:transparent}
.nav a:hover{background:rgba(255,255,255,.08);color:#fff}
.nav a.active{background:rgba(255,255,255,.22);color:#fff;box-shadow:0 8px 18px rgba(0,0,0,.18)}
.btn-nav-dash.active{background:linear-gradient(180deg,rgba(255,200,160,.55),rgba(251,146,60,.38))}
.btn-nav-products.active{background:linear-gradient(180deg,rgba(190,242,100,.50),rgba(132,204,22,.32))}
.btn-nav-sales.active{background:linear-gradient(180deg,rgba(196,181,253,.55),rgba(139,92,246,.34))}
.btn-nav-pharmacies.active{background:linear-gradient(180deg,rgba(249,168,212,.55),rgba(236,72,153,.34))}
.card{background:rgba(255,255,255,.12);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:18px;margin-bottom:22px}
.table-wrap{width:100%;padding-bottom:8px}
table{width:100%;border-collapse:separate;border-spacing:0 10px}
th{text-align:center;color:#bfdbfe;padding:10px}
td{background:rgba(255,255,255,.12);padding:12px;text-align:center}
tr td:first-child{border-radius:0 14px 14px 0}
tr td:last-child{border-radius:14px 0 0 14px}
.report-wrap{overflow-x:visible}
.report-table{table-layout:fixed;min-width:0}
.report-table th,.report-table td{white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;font-size:14px;padding:12px 6px}
.report-table th:nth-child(1),.report-table td:nth-child(1){width:40%}
.report-table th:nth-child(2),.report-table td:nth-child(2){width:30%}
.report-table th:nth-child(3),.report-table td:nth-child(3){width:30%}
.pharmacy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
.pharmacy-block{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:14px}
.pharmacy-block h3{margin:0 0 10px;color:#fcd34d;font-size:18px}
.pharmacy-meta{display:flex;justify-content:space-between;gap:8px;margin-bottom:10px;color:#f9a8d4;font-size:13px;font-weight:bold}
.period-box{margin-top:12px;padding:10px;border-radius:14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05)}
.period-box h4{margin:0 0 8px;font-size:14px}
.period-new h4{color:#a3e635}
.period-old h4{color:#f9a8d4}
.back-top{position:fixed;left:18px;bottom:22px;z-index:900;width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);color:#fff;font-size:24px;font-weight:bold;display:none;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 10px 24px rgba(0,0,0,.18)}
.back-top.show{display:flex}
@media(max-width:600px){.container{padding:10px}.logo{font-size:21px}.nav a{font-size:11px;padding:9px 4px}.card{padding:13px}.report-table th,.report-table td{font-size:12px;padding:10px 4px}}
</style>
</head>
<body>
<div class="container" id="top">
    <div class="header">
        <div class="logo">🏪 الصيدليات</div>
        <div class="nav">
            <a class="btn-nav-dash" href="dashboard.php">الداشبورد</a>
            <a class="btn-nav-products" href="products.php">المنتجات</a>
            <a class="btn-nav-sales" href="reports.php">المبيعات</a>
            <a class="btn-nav-pharmacies active" href="pharmacies.php">الصيدليات</a>
        </div>
    </div>

    <div class="card">
        <h2>الصيدليات</h2>
        <p style="color:#f9a8d4;margin:0 0 12px;font-size:14px;font-weight:700;">كل صيدلية تعرض المبيعات الحالية بعد التصفير، والمبيعات السابقة منفصلة.</p>
        <?php if (empty($pharmacyNames)): ?>
            <p style="color:#f9a8d4;margin:0;">لا توجد مبيعات بعد.</p>
        <?php else: ?>
        <div class="pharmacy-grid">
            <?php foreach ($pharmacyNames as $pharmacyName): ?>
            <div class="pharmacy-block">
                <h3><?= htmlspecialchars($pharmacyName) ?></h3>
                <div class="period-box period-new">
                    <h4>المبيعات الحالية</h4>
                    <?php renderPharmacyProducts($currentReport[$pharmacyName] ?? []); ?>
                </div>
                <div class="period-box period-old">
                    <h4>المبيعات السابقة</h4>
                    <?php renderPharmacyProducts($oldReport[$pharmacyName] ?? []); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<a href="#top" class="back-top" id="backTop" title="العودة للأعلى">↑</a>
<script>
const backTop = document.getElementById('backTop');
window.addEventListener('scroll', function() {
    if (window.scrollY > 280) backTop.classList.add('show');
    else backTop.classList.remove('show');
});
</script>
</body>
</html>
