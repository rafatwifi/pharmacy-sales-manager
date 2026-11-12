<?php
require_once __DIR__ . '/store.php';

$products = loadProducts();
$sales = loadSales();
$msg = '';
$period = currentPeriod();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_sale'])) {
        $saleIndex = (int)($_POST['sale_index'] ?? -1);
        $newQty = (int)($_POST['new_qty'] ?? 0);
        $newPharmacy = trim($_POST['new_pharmacy'] ?? '');
        if (isset($sales[$saleIndex]) && $newQty > 0 && $newPharmacy !== '') {
            $sales[$saleIndex]['qty'] = $newQty;
            $sales[$saleIndex]['pharmacy'] = $newPharmacy;
            $sales[$saleIndex]['total'] = $newQty * (float)$sales[$saleIndex]['price'];
            $sales[$saleIndex]['date'] = date('Y-m-d H:i');
            saveJson($salesFile, $sales);
            header('Location: ' . url('sales') . '?msg=updated_sale#section-sales');
            exit;
        }
    }
    if (isset($_POST['delete_sale'])) {
        $saleIndex = (int)($_POST['sale_index'] ?? -1);
        if (isset($sales[$saleIndex])) {
            unset($sales[$saleIndex]);
            saveJson($salesFile, $sales);
            header('Location: ' . url('sales') . '?msg=deleted_sale#section-sales');
            exit;
        }
    }
    if (isset($_POST['reset_sales'])) {
        $ok = startNewCycle();
        header('Location: ' . url('sales') . '?msg=' . ($ok ? 'reset' : 'reset_fail'));
        exit;
    }
}

if (isset($_GET['msg'])) {
    $map = [
        'reset' => 'تم تصفير تقرير المنتجات والداشبورد. سجل البيع السابق محفوظ ✅',
        'reset_fail' => 'ما تكدر يتصفر. تأكد صلاحية الكتابة لملف sales.json ❌',
        'updated_sale' => 'تم تعديل عملية البيع بنجاح ✅',
        'deleted_sale' => 'تم حذف عملية البيع بنجاح ✅'
    ];
    $msg = $map[$_GET['msg']] ?? '';
}

function buildProductReport($rows) {
    $report = [];
    foreach ($rows as $s) {
        $name = $s['product'] ?? '—';
        if (!isset($report[$name])) $report[$name] = ['qty' => 0, 'total' => 0];
        $report[$name]['qty'] += (int)$s['qty'];
        $report[$name]['total'] += (float)$s['total'];
    }
    return $report;
}

$currentSales = salesInPeriod($sales, $period);
$oldSales = salesBeforePeriod($sales, $period);
$currentReport = buildProductReport($currentSales);
$oldReport = buildProductReport($oldSales);

foreach ($products as $p) {
    $name = $p['name'] ?? '';
    if ($name !== '' && !isset($currentReport[$name])) {
        $currentReport[$name] = ['qty' => 0, 'total' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>المبيعات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:1300px;margin:auto;padding:16px}
.header{position:sticky;top:8px;z-index:850;display:flex;flex-direction:column;align-items:stretch}
.logo{font-size:18px;font-weight:800}
<?= app_nav_styles() ?>
.nav{display:flex;flex-wrap:nowrap;gap:4px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:28px;padding:6px}
.nav a{flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e2e8f0;text-decoration:none;padding:11px 8px;border-radius:22px;font-weight:800;font-size:14px;border:0;background:transparent}
.nav a:hover{background:rgba(255,255,255,.08);color:#fff}
.nav a.active{background:rgba(255,255,255,.22);color:#fff;box-shadow:0 8px 18px rgba(0,0,0,.18)}
.btn-nav-dash.active{background:linear-gradient(180deg,rgba(255,200,160,.55),rgba(251,146,60,.38))}
.btn-nav-products.active{background:linear-gradient(180deg,rgba(190,242,100,.50),rgba(132,204,22,.32))}
.btn-nav-sales.active{background:linear-gradient(180deg,rgba(196,181,253,.55),rgba(139,92,246,.34))}
.btn-nav-pharmacies.active{background:linear-gradient(180deg,rgba(249,168,212,.55),rgba(236,72,153,.34))}
.msg{background:#22c55e;padding:14px;border-radius:16px;margin-bottom:18px;font-weight:bold}
.msg.err{background:#e11d48}
.card{background:rgba(255,255,255,.12);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:18px;margin-bottom:22px;scroll-margin-top:88px}
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
.sales-wrap{overflow-x:auto}
.sales-table{min-width:920px}
.sales-table th,.sales-table td{white-space:nowrap}
.sale-input{width:75px;padding:8px;border:0;border-radius:10px;text-align:center;font-family:inherit}
.sale-pharmacy{width:130px;padding:8px;border:0;border-radius:10px;text-align:center;font-family:inherit}
.btn-edit{background:linear-gradient(180deg,#fcd34d,#f59e0b);color:white;border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:bold;font-family:inherit}
.btn-delete{background:linear-gradient(180deg,#fda4af,#e11d48);color:white;border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:bold;font-family:inherit}
.edit-form{display:flex;flex-wrap:nowrap;gap:6px;justify-content:center;align-items:center}
.btn-reset{width:100%;padding:12px;border:0;border-radius:12px;color:white;font-weight:bold;cursor:pointer;background:linear-gradient(180deg,#ff8a65,#ff3d00);font-size:17px;font-family:inherit}
.back-top{position:fixed;left:18px;bottom:22px;z-index:900;width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);color:#fff;font-size:24px;font-weight:bold;display:none;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 10px 24px rgba(0,0,0,.18)}
.back-top.show{display:flex}
@media(max-width:600px){.container{padding:10px}.logo{font-size:21px}.nav a{font-size:11px;padding:9px 4px}.card{padding:13px}.report-table th,.report-table td{font-size:12px;padding:10px 4px}.sales-table{min-width:920px}}
</style>
</head>
<body>
<div class="container" id="top">
    <div class="header">
        <?php app_nav('sales'); ?>
    </div>

    <?php if($msg): ?><div class="msg<?= strpos($msg, '❌') !== false ? ' err' : '' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card" id="section-report">
        <h2>تقرير المبيعات حسب المنتج</h2>
        <p style="color:#f9a8d4;margin:0 0 12px;font-size:14px;font-weight:700;">هذا الجدول للدورة الحالية ويتصفر مع زر التصفير. السجل التفصيلي يبقى تحت.</p>
        <h3 style="margin:0 0 10px;color:#a3e635;font-size:16px;">المبيعات الحالية</h3>
        <div class="table-wrap report-wrap">
            <table class="report-table">
                <tr><th>المنتج</th><th>إجمالي القطع المباعة</th><th>إجمالي البيع</th></tr>
                <?php foreach($currentReport as $name=>$r): ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= number_format($r['qty']) ?> قطعة</td>
                    <td><?= number_format($r['total']) ?> د.ع</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php if (!empty($oldReport)): ?>
        <h3 style="margin:18px 0 10px;color:#f9a8d4;font-size:16px;">المبيعات السابقة</h3>
        <div class="table-wrap report-wrap">
            <table class="report-table">
                <tr><th>المنتج</th><th>إجمالي القطع المباعة</th><th>إجمالي البيع</th></tr>
                <?php foreach($oldReport as $name=>$r): ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= number_format($r['qty']) ?> قطعة</td>
                    <td><?= number_format($r['total']) ?> د.ع</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" id="section-sales">
        <h2>سجل عمليات البيع</h2>
        <div class="table-wrap sales-wrap">
            <table class="sales-table">
                <tr>
                    <th>المنتج</th>
                    <th>اسم الصيدلية</th>
                    <th>الكمية المباعة</th>
                    <th>سعر القطعة</th>
                    <th>الإجمالي</th>
                    <th>التاريخ</th>
                    <th>الدورة</th>
                    <th>تعديل</th>
                    <th>حذف</th>
                </tr>
                <?php foreach(array_reverse($sales, true) as $index => $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['product']) ?></td>
                    <td><?= htmlspecialchars(trim((string)($s['pharmacy'] ?? '')) !== '' ? $s['pharmacy'] : 'غير محدد') ?></td>
                    <td><?= $s['qty'] ?></td>
                    <td><?= number_format($s['price']) ?> د.ع</td>
                    <td><?= number_format($s['total']) ?> د.ع</td>
                    <td><?= $s['date'] ?></td>
                    <td><?= isCurrentSale($s, $period) ? 'حالية' : 'سابقة' ?></td>
                    <td>
                        <form method="post" class="edit-form">
                            <input type="hidden" name="sale_index" value="<?= $index ?>">
                            <input class="sale-pharmacy" type="text" name="new_pharmacy" value="<?= htmlspecialchars($s['pharmacy'] ?? '') ?>" placeholder="اسم الصيدلية" required>
                            <input class="sale-input" type="number" name="new_qty" min="1" value="<?= $s['qty'] ?>" required>
                            <button class="btn-edit" name="update_sale">تعديل</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('متأكد تريد تحذف عملية البيع؟');">
                            <input type="hidden" name="sale_index" value="<?= $index ?>">
                            <button class="btn-delete" name="delete_sale">حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <form method="post" onsubmit="return confirm('راح يتصفر الداشبورد وتبدأ دورة مبيعات جديدة. عمليات البيع السابقة تبقى في السجل والصيدليات.');">
            <input type="hidden" name="reset_sales" value="1">
            <button class="btn-reset" type="submit">تصفير الداشبورد (بدون حذف السجل)</button>
        </form>
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
