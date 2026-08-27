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
    if (isset($_POST['sell_batch'])) {
        $pharmacy = trim($_POST['pharmacy'] ?? '');
        $qtys = $_POST['qty'] ?? [];
        if ($pharmacy === '') {
            $msg = 'يرجى إدخال اسم الصيدلية ❌';
        } else {
            $added = 0;
            foreach ($products as $p) {
                $id = (string)$p['id'];
                $sellQty = (int)($qtys[$id] ?? 0);
                if ($sellQty > 0) {
                    $sales[] = [
                        'product_id' => $p['id'],
                        'product' => $p['name'],
                        'pharmacy' => $pharmacy,
                        'qty' => $sellQty,
                        'price' => (float)$p['price'],
                        'total' => $sellQty * (float)$p['price'],
                        'date' => date('Y-m-d H:i')
                    ];
                    $added++;
                }
            }
            if ($added > 0) {
                saveJson($salesFile, $sales);
                header('Location: dashboard.php?msg=sold');
                exit;
            }
            $msg = 'يرجى إدخال كمية لمنتج واحد على الأقل ❌';
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

$pharmacyNames = [];
foreach ($sales as $s) {
    $n = trim((string)($s['pharmacy'] ?? ''));
    if ($n !== '') $pharmacyNames[$n] = true;
}
$pharmacyNames = array_keys($pharmacyNames);
sort($pharmacyNames, SORT_STRING);

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
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh;padding-bottom:96px}
.container{width:100%;max-width:1300px;margin:auto;padding:16px}
.header{position:sticky;top:0;z-index:850;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin:0 0 16px;padding:12px 0 14px;background:linear-gradient(180deg,rgba(2,6,23,.94),rgba(2,6,23,.78) 70%,transparent);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.logo{font-size:28px;font-weight:bold}
.nav{display:flex;gap:8px;flex-wrap:wrap}
.nav a{color:#f8fafc;text-decoration:none;padding:11px 16px;border-radius:999px;font-weight:700;display:inline-block;transition:transform .15s ease,background .15s ease;border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);box-shadow:0 8px 20px rgba(0,0,0,.12)}
.nav a:hover{transform:translateY(-2px)}
.nav a.active{box-shadow:0 0 0 2px rgba(255,255,255,.28),0 8px 20px rgba(0,0,0,.12);border-color:rgba(255,255,255,.7)}
.btn-nav-dash{background:rgba(255,200,160,.28)}
.btn-nav-products{background:rgba(190,242,100,.25)}
.btn-nav-sales{background:rgba(196,181,253,.28)}
.btn-nav-pharmacies{background:rgba(249,168,212,.28)}
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
.msg.err{background:#e11d48}
.card{background:rgba(255,255,255,.12);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:18px;margin-bottom:22px;scroll-margin-top:18px}
.sell-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
.sell-card{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:14px}
.sell-card img{width:100%;height:150px;object-fit:cover;border-radius:16px;margin-bottom:10px;background:rgba(255,255,255,.12)}
.row{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12);font-size:15px}
.row b{color:#facc15}
.progress{height:22px;border-radius:20px;background:rgba(255,255,255,.2);overflow:hidden;margin-top:15px;position:relative}
.progress-text{position:absolute;width:100%;text-align:center;line-height:22px;font-size:13px;font-weight:bold;color:#fff;text-shadow:1px 1px 3px rgba(0,0,0,0.8);z-index:2;left:0;top:0}
.bar{height:100%;background:linear-gradient(135deg,#a3e635,#db2777);transition:width 0.3s}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(2,6,23,.72);z-index:1000;align-items:flex-end;justify-content:center;padding:12px}
.modal-overlay.open{display:flex}
.modal{width:100%;max-width:520px;max-height:92vh;overflow:auto;background:linear-gradient(135deg,#1e293b,#0f172a);border:1px solid rgba(255,255,255,.2);border-radius:22px;padding:22px;margin-bottom:8px}
.modal h3{margin:0 0 8px;font-size:20px}
.modal label{display:block;margin:0 0 6px;color:#f9a8d4;font-size:14px;font-weight:700}
.suggest-box{position:relative;margin:0 0 16px}
.suggest-box input{width:100%;padding:12px;border:0;border-radius:12px;font-size:16px;text-align:center;font-family:inherit}
.suggest-list{display:none;position:absolute;top:calc(100% + 4px);right:0;left:0;background:#1e293b;border:1px solid rgba(255,255,255,.2);border-radius:12px;max-height:180px;overflow-y:auto;z-index:6;box-shadow:0 12px 24px rgba(0,0,0,.28)}
.suggest-list.open{display:block}
.suggest-item{padding:10px 12px;cursor:pointer;font-weight:700}
.suggest-item:hover{background:rgba(244,114,182,.28)}
.suggest-hint{font-size:12px;color:#cbd5e1;margin:6px 0 0}
.sell-list{max-height:42vh;overflow-y:auto;margin:0 0 14px;padding:2px}
.sell-item{display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 12px;margin-bottom:8px}
.sell-item .pname{flex:1;font-weight:700;text-align:right}
.sell-item input{width:88px;padding:10px;border:0;border-radius:10px;text-align:center;font-family:inherit;font-size:16px}
.modal-actions{display:flex;gap:10px}
.modal-actions button{flex:1;padding:12px;border:0;border-radius:12px;color:white;font-weight:bold;cursor:pointer;font-family:inherit}
.btn-cancel{background:rgba(255,255,255,.18)}
.btn-confirm{background:linear-gradient(180deg,#f472b6,#db2777)}
.fab-sell{position:fixed;right:18px;bottom:22px;z-index:920;width:64px;height:64px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:linear-gradient(180deg,#f472b6,#db2777);color:#fff;font-size:30px;font-weight:800;cursor:pointer;box-shadow:0 12px 28px rgba(219,39,119,.45);font-family:inherit;line-height:1}
.fab-sell:active{transform:scale(.96)}
.back-top{position:fixed;left:18px;bottom:22px;z-index:900;width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);color:#fff;font-size:24px;font-weight:bold;display:none;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 10px 24px rgba(0,0,0,.18)}
.back-top.show{display:flex}
@media(max-width:600px){
  .container{padding:10px}
  .logo{font-size:21px}
  .card{padding:13px}
  .sell-grid{grid-template-columns:1fr}
  .widgets{grid-template-columns:1fr 1fr;gap:10px}
  .widget{aspect-ratio:1;min-height:0;height:auto;padding:14px;border-radius:22px;justify-content:center}
  .widget h3{font-size:13px}
  .widget .sub{font-size:11px}
  .widget .num{font-size:17px;margin-top:8px}
  .widget .icon{font-size:28px;left:10px;bottom:8px}
}
</style>
</head>
<body>
<div class="container" id="top">
    <div class="header">
        <div class="logo">💊 Medical Sales</div>
        <div class="nav">
            <a class="btn-nav-dash active" href="dashboard.php">الداشبورد</a>
            <a class="btn-nav-products" href="products.php">إدارة المنتجات</a>
            <a class="btn-nav-sales" href="reports.php#section-sales">المبيعات</a>
            <a class="btn-nav-pharmacies" href="reports.php#section-pharmacies">الصيدليات</a>
        </div>
    </div>

    <?php if($msg): ?><div class="msg<?= strpos($msg, '❌') !== false ? ' err' : '' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

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
        <h2>متابعة المنتجات</h2>
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
            <div class="sell-card" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
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
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="sellModal" onclick="if(event.target===this) closeSellModal()">
    <div class="modal">
        <h3>تسجيل بيع</h3>
        <form method="post" id="sellForm" onsubmit="return validateSell()">
            <label for="pharmacyInput">اسم الصيدلية</label>
            <div class="suggest-box">
                <input type="text" name="pharmacy" id="pharmacyInput" placeholder="اكتب اسم الصيدلية" autocomplete="off" required>
                <div class="suggest-list" id="suggestList"></div>
                <div class="suggest-hint" id="suggestHint">اكتب للبحث، أو أدخل اسماً جديداً لإضافته.</div>
            </div>
            <label>الأدوية والكمية</label>
            <div class="sell-list">
                <?php foreach($products as $p): ?>
                <div class="sell-item">
                    <span class="pname"><?= htmlspecialchars($p['name']) ?></span>
                    <input type="number" name="qty[<?= htmlspecialchars($p['id']) ?>]" min="0" step="1" placeholder="0" inputmode="numeric">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeSellModal()">إلغاء</button>
                <button class="btn-confirm" name="sell_batch" value="1">تأكيد</button>
            </div>
        </form>
    </div>
</div>

<button type="button" class="fab-sell" onclick="openSellModal()" title="بيع">💊</button>
<a href="#top" class="back-top" id="backTop" title="العودة للأعلى">↑</a>

<script>
const pharmacies = <?= json_encode($pharmacyNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const pharmacyInput = document.getElementById('pharmacyInput');
const suggestList = document.getElementById('suggestList');
const suggestHint = document.getElementById('suggestHint');

document.getElementById('searchBox').addEventListener('input', function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll('.sell-card').forEach(c => {
        c.style.display = c.dataset.name.includes(q) ? 'block' : 'none';
    });
});

function openSellModal() {
    document.getElementById('sellModal').classList.add('open');
    pharmacyInput.value = '';
    suggestList.classList.remove('open');
    suggestList.innerHTML = '';
    document.querySelectorAll('#sellForm input[type="number"]').forEach(i => i.value = '');
    suggestHint.textContent = 'اكتب للبحث، أو أدخل اسماً جديداً لإضافته.';
    pharmacyInput.focus();
}
function closeSellModal() {
    document.getElementById('sellModal').classList.remove('open');
}
function validateSell() {
    const pharmacy = pharmacyInput.value.trim();
    if (!pharmacy) {
        alert('أدخل اسم الصيدلية');
        pharmacyInput.focus();
        return false;
    }
    let hasQty = false;
    document.querySelectorAll('#sellForm input[type="number"]').forEach(i => {
        if (parseInt(i.value, 10) > 0) hasQty = true;
    });
    if (!hasQty) {
        alert('أدخل كمية لمنتج واحد على الأقل');
        return false;
    }
    return true;
}
function renderSuggestions(q) {
    const query = (q || '').trim().toLowerCase();
    suggestList.innerHTML = '';
    if (!query) {
        suggestList.classList.remove('open');
        suggestHint.textContent = 'اكتب للبحث، أو أدخل اسماً جديداً لإضافته.';
        return;
    }
    const matches = pharmacies.filter(name => name.toLowerCase().includes(query));
    if (!matches.length) {
        suggestList.classList.remove('open');
        suggestHint.textContent = 'صيدلية جديدة — سيتم إضافتها عند التأكيد.';
        return;
    }
    matches.forEach(name => {
        const item = document.createElement('div');
        item.className = 'suggest-item';
        item.textContent = name;
        item.onmousedown = function(e) {
            e.preventDefault();
            pharmacyInput.value = name;
            suggestList.classList.remove('open');
            suggestHint.textContent = 'صيدلية موجودة.';
        };
        suggestList.appendChild(item);
    });
    suggestList.classList.add('open');
    const exact = matches.some(name => name.toLowerCase() === query);
    suggestHint.textContent = exact ? 'صيدلية موجودة.' : 'اختر من الاقتراحات، أو اترك الاسم جديداً ليُضاف.';
}
pharmacyInput.addEventListener('input', function() {
    renderSuggestions(this.value);
});
pharmacyInput.addEventListener('focus', function() {
    renderSuggestions(this.value);
});
pharmacyInput.addEventListener('blur', function() {
    setTimeout(() => suggestList.classList.remove('open'), 150);
});
const backTop = document.getElementById('backTop');
window.addEventListener('scroll', function() {
    if (window.scrollY > 280) backTop.classList.add('show');
    else backTop.classList.remove('show');
});
</script>
</body>
</html>
