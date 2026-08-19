<?php
$dataFile = __DIR__ . '/data.json';
$salesFile = __DIR__ . '/sales.json';
$uploadPath = 'uploads/products/';

if (!file_exists($dataFile)) file_put_contents($dataFile, '[]');
if (!file_exists($salesFile)) file_put_contents($salesFile, '[]');

$products = json_decode(file_get_contents($dataFile), true) ?: [];
$sales = json_decode(file_get_contents($salesFile), true) ?: [];
$msg = '';

function saveJson($file, $data){ file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sell'])) {
        $id = $_POST['id'] ?? '';
        $sellQty = (int)($_POST['sell_qty'] ?? 0);
        $pharmacy = trim($_POST['pharmacy'] ?? '');
        foreach ($products as $p) {
            if ((string)$p['id'] === (string)$id) {
                if ($sellQty > 0 && $pharmacy !== '') {
                    $sales[] = [
                        'product_id' => $p['id'],
                        'product' => $p['name'],
                        'pharmacy' => $pharmacy,
                        'qty' => $sellQty,
                        'price' => (float)$p['price'],
                        'total' => $sellQty * (float)$p['price'],
                        'date' => date('Y-m-d H:i')
                    ];
                    saveJson($salesFile, $sales);
                    header('Location: dashboard.php?msg=sold');
                    exit;
                } else {
                    $msg = 'يرجى إدخال اسم الصيدلية والكمية بشكل صحيح ❌';
                }
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $map = ['sold' => 'تم تسجيل البيع بنجاح ✅'];
    $msg = $map[$_GET['msg']] ?? '';
}

$productReport = [];
foreach ($sales as $s) {
    $name = $s['product'];
    if (!isset($productReport[$name])) $productReport[$name] = ['qty' => 0, 'total' => 0];
    $productReport[$name]['qty'] += (int)$s['qty'];
    $productReport[$name]['total'] += (float)$s['total'];
}

$totalSales = array_sum(array_column($sales, 'total'));
$totalTarget = 0;
foreach ($products as $p) {
    $totalTarget += ((int)($p['target_qty'] ?? 0)) * (float)$p['price'];
}
$remainingToGlobalTarget = max(0, $totalTarget - $totalSales);
$globalTargetPercent = $totalTarget > 0 ? min(100, round(($totalSales / $totalTarget) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>داشبورد المبيعات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:1300px;margin:auto;padding:16px}
.header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.logo{font-size:28px;font-weight:bold}
.nav{display:flex;gap:8px;flex-wrap:wrap}
.nav a{color:#f8fafc;text-decoration:none;padding:11px 16px;border-radius:999px;font-weight:700;display:inline-block;transition:transform .15s ease,background .15s ease;border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);box-shadow:0 8px 20px rgba(0,0,0,.12)}
.nav a:hover{transform:translateY(-2px)}
.btn-nav-sell{background:rgba(255,200,160,.28)}
.btn-nav-reports{background:rgba(196,181,253,.28)}
.btn-nav-pharmacies{background:rgba(249,168,212,.28)}
.btn-nav-products{background:rgba(190,242,100,.25)}
.search{width:100%;padding:14px;border:0;border-radius:16px;margin:0 0 18px;font-size:16px;outline:none;font-family:inherit}
.widgets{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:14px}
.widget{position:relative;overflow:hidden;border-radius:28px;color:#f8fafc;min-height:122px;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between;border:1px solid rgba(255,255,255,.34);backdrop-filter:blur(22px) saturate(160%);-webkit-backdrop-filter:blur(22px) saturate(160%);box-shadow:0 12px 30px rgba(0,0,0,.14),inset 0 1px 0 rgba(255,255,255,.45)}
.widget::before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 20% 15%,rgba(255,255,255,.42),transparent 42%),radial-gradient(circle at 85% 80%,rgba(255,255,255,.12),transparent 40%);pointer-events:none}
.widget h3{margin:0;font-size:15px;font-weight:700;position:relative;z-index:1}
.widget .sub{font-size:12px;opacity:.82;margin-top:3px;position:relative;z-index:1}
.widget .num{font-size:24px;font-weight:800;margin-top:10px;position:relative;z-index:1;letter-spacing:-.02em}
.widget .icon{position:absolute;left:14px;bottom:10px;font-size:40px;opacity:.22;line-height:1;z-index:0}
.c-coral{background:linear-gradient(145deg,rgba(255,186,160,.38),rgba(255,255,255,.12))}
.c-lime{background:linear-gradient(145deg,rgba(196,230,168,.36),rgba(255,255,255,.12))}
.c-pink{background:linear-gradient(145deg,rgba(255,190,220,.36),rgba(255,255,255,.12))}
.c-amber{background:linear-gradient(145deg,rgba(255,224,160,.36),rgba(255,255,255,.12))}
.msg{background:#22c55e;padding:14px;border-radius:16px;margin-bottom:18px;font-weight:bold}
.card{background:rgba(255,255,255,.12);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:18px;margin-bottom:22px;scroll-margin-top:18px}
.sell-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
.sell-card{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:14px}
.sell-card img{width:100%;height:150px;object-fit:cover;border-radius:16px;margin-bottom:10px;background:rgba(255,255,255,.12)}
.row{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12);font-size:15px}
.row b{color:#facc15}
.progress{height:22px;border-radius:20px;background:rgba(255,255,255,.2);overflow:hidden;margin-top:15px;position:relative}
.progress-text{position:absolute;width:100%;text-align:center;line-height:22px;font-size:13px;font-weight:bold;color:#fff;text-shadow:1px 1px 3px rgba(0,0,0,0.8);z-index:2;left:0;top:0}
.bar{height:100%;background:linear-gradient(135deg,#a3e635,#db2777);transition:width 0.3s}
.btn-sell{width:100%;padding:12px;border:0;border-radius:12px;color:white;font-weight:bold;cursor:pointer;background:linear-gradient(180deg,#f472b6,#db2777);font-family:inherit;margin-top:15px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(2,6,23,.72);z-index:1000;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal{width:100%;max-width:420px;background:linear-gradient(135deg,#1e293b,#0f172a);border:1px solid rgba(255,255,255,.2);border-radius:22px;padding:22px}
.modal h3{margin:0 0 8px;font-size:20px}
.modal .product-name{color:#fcd34d;margin:0 0 16px;font-weight:bold}
.modal label{display:block;margin:0 0 6px;color:#f9a8d4;font-size:14px}
.modal input{width:100%;padding:12px;border:0;border-radius:12px;margin:0 0 14px;font-size:16px;text-align:center;font-family:inherit}
.modal-actions{display:flex;gap:10px}
.modal-actions button{flex:1;padding:12px;border:0;border-radius:12px;color:white;font-weight:bold;cursor:pointer;font-family:inherit}
.btn-cancel{background:rgba(255,255,255,.18)}
.btn-confirm{background:linear-gradient(180deg,#f472b6,#db2777)}
.back-top{position:fixed;left:18px;bottom:22px;z-index:900;width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);color:#fff;font-size:24px;font-weight:bold;display:none;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 10px 24px rgba(0,0,0,.18)}
.back-top.show{display:flex}
@media(max-width:600px){.container{padding:10px}.logo{font-size:21px}.card{padding:13px}.sell-grid{grid-template-columns:1fr}.widget .num{font-size:20px}}
</style>
</head>
<body>
<div class="container" id="top">
    <div class="header">
        <div class="logo">💊 Medical Sales</div>
        <div class="nav">
            <a class="btn-nav-sell" href="#section-sell">البيع</a>
            <a class="btn-nav-reports" href="reports.php">التقارير</a>
            <a class="btn-nav-pharmacies" href="reports.php#section-pharmacies">الصيدليات</a>
            <a class="btn-nav-products" href="products.php">إدارة المنتجات</a>
        </div>
    </div>

    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <input class="search" id="searchBox" placeholder="🔍 ابحث عن المنتج...">

    <div class="widgets">
        <div class="widget c-coral">
            <div><h3>إجمالي المبيعات</h3><div class="sub">كل المبيعات</div></div>
            <div class="num"><?= number_format($totalSales) ?> د.ع</div>
            <div class="icon">💰</div>
        </div>
        <div class="widget c-lime">
            <div><h3>التاركت الكلي</h3><div class="sub">الهدف المطلوب</div></div>
            <div class="num"><?= number_format($totalTarget) ?> د.ع</div>
            <div class="icon">🎯</div>
        </div>
        <div class="widget c-pink">
            <div><h3>نسبة التاركت</h3><div class="sub">نسبة الإنجاز</div></div>
            <div class="num"><?= $globalTargetPercent ?>%</div>
            <div class="icon">📈</div>
        </div>
        <div class="widget c-amber">
            <div><h3>الباقي للتاركت</h3><div class="sub">المتبقي</div></div>
            <div class="num"><?= number_format($remainingToGlobalTarget) ?> د.ع</div>
            <div class="icon">⏳</div>
        </div>
    </div>

    <div class="card" id="section-sell">
        <h2>البيع المباشر والمتابعة</h2>
        <div class="sell-grid">
        <?php foreach($products as $p):
            $targetQty = (int)($p['target_qty'] ?? 0);
            $targetValue = $targetQty * (float)$p['price'];
            $soldPieces = isset($productReport[$p['name']]) ? $productReport[$p['name']]['qty'] : 0;
            $soldValue = isset($productReport[$p['name']]) ? $productReport[$p['name']]['total'] : 0;
            $percent = $targetQty > 0 ? min(100, round(($soldPieces / $targetQty) * 100)) : 0;
            $remainingPiecesToTarget = max(0, $targetQty - $soldPieces);
            $remainingPriceToTarget = max(0, $targetValue - $soldValue);
        ?>
            <div class="sell-card" data-name="<?= htmlspecialchars(mb_strtolower($p['name'])) ?>">
                <?php if(!empty($p['image'])): ?>
                    <img src="<?= $uploadPath . htmlspecialchars($p['image']) ?>">
                <?php else: ?>
                    <img alt="">
                <?php endif; ?>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <div class="row"><span>السعر</span><b><?= number_format($p['price']) ?> د.ع</b></div>
                <div class="row"><span>التاركت المطلوب</span><b><?= number_format($targetQty) ?> قطعة</b></div>
                <div class="row"><span>القطع المباعة</span><b><?= number_format($soldPieces) ?> قطعة</b></div>
                <div class="row"><span>القطع الباقية للتاركت</span><b><?= number_format($remainingPiecesToTarget) ?> قطعة</b></div>
                <div class="row"><span>السعر الباقي للتاركت</span><b><?= number_format($remainingPriceToTarget) ?> د.ع</b></div>
                <div class="progress">
                    <div class="progress-text"><?= $percent ?>%</div>
                    <div class="bar" style="width:<?= $percent ?>%"></div>
                </div>
                <button type="button" class="btn-sell"
                    onclick="openSellModal('<?= htmlspecialchars($p['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">بيع</button>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="sellModal" onclick="if(event.target===this) closeSellModal()">
    <div class="modal">
        <h3>تسجيل بيع</h3>
        <p class="product-name" id="sellProductName"></p>
        <form method="post">
            <input type="hidden" name="id" id="sellProductId">
            <label for="pharmacyInput">اسم الصيدلية</label>
            <input type="text" name="pharmacy" id="pharmacyInput" placeholder="أدخل اسم الصيدلية" required>
            <label for="sellQtyInput">العدد</label>
            <input type="number" name="sell_qty" id="sellQtyInput" min="1" placeholder="أدخل الكمية" required>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeSellModal()">إلغاء</button>
                <button class="btn-confirm" name="sell">تأكيد البيع</button>
            </div>
        </form>
    </div>
</div>

<a href="#top" class="back-top" id="backTop" title="العودة للأعلى">↑</a>

<script>
document.getElementById('searchBox').addEventListener('input', function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll('.sell-card').forEach(c => {
        c.style.display = c.dataset.name.includes(q) ? 'block' : 'none';
    });
});
function openSellModal(id, name) {
    document.getElementById('sellProductId').value = id;
    document.getElementById('sellProductName').textContent = name;
    document.getElementById('pharmacyInput').value = '';
    document.getElementById('sellQtyInput').value = '';
    document.getElementById('sellModal').classList.add('open');
    document.getElementById('pharmacyInput').focus();
}
function closeSellModal() {
    document.getElementById('sellModal').classList.remove('open');
}
const backTop = document.getElementById('backTop');
window.addEventListener('scroll', function() {
    if (window.scrollY > 280) backTop.classList.add('show');
    else backTop.classList.remove('show');
});
</script>
</body>
</html>
