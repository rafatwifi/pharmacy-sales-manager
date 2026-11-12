<?php
require_once __DIR__ . '/store.php';

$products = loadProducts();
$msg = '';

function saveProducts($data) {
    global $dataFile;
    saveJson($dataFile, array_values($data));
}

function handleUpload($file) {
    global $uploadFs;
    if (!empty($file['tmp_name']) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allow = array('jpg','jpeg','png','gif','webp');
        if (!in_array($ext, $allow, true)) return '';
        $newName = uniqid('prod_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadFs . $newName);
        return $newName;
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $limits = package_limits();
        if ($limits['max_products'] > 0 && count($products) >= $limits['max_products']) {
            $msg = 'وصلت حد المنتجات في باقتك ❌';
        } else {
            $image = handleUpload(isset($_FILES['image']) ? $_FILES['image'] : array());
            $products[] = array(
                'id' => uniqid(),
                'name' => trim($_POST['name']),
                'price' => (float)$_POST['price'],
                'target_qty' => (int)$_POST['target_qty'],
                'image' => $image
            );
            saveProducts($products);
            header('Location: ' . url('products') . '?msg=added');
            exit;
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $p['name'] = trim($_POST['name']);
                $p['price'] = (float)$_POST['price'];
                $p['target_qty'] = (int)$_POST['target_qty'];
                if (!empty($_FILES['image']['name'])) {
                    if ($p['image'] && file_exists($uploadFs . $p['image'])) {
                        unlink($uploadFs . $p['image']);
                    }
                    $p['image'] = handleUpload($_FILES['image']);
                }
                break;
            }
        }
        unset($p);
        saveProducts($products);
        header('Location: ' . url('products') . '?msg=edited');
        exit;
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        foreach ($products as $k => $p) {
            if ($p['id'] === $id) {
                if ($p['image'] && file_exists($uploadFs . $p['image'])) {
                    unlink($uploadFs . $p['image']);
                }
                unset($products[$k]);
                break;
            }
        }
        saveProducts($products);
        header('Location: ' . url('products') . '?msg=deleted');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $map = ['added' => 'تم إضافة المنتج بنجاح ✅', 'edited' => 'تم تعديل المنتج بنجاح ✅', 'deleted' => 'تم حذف المنتج بنجاح ✅'];
    $msg = $map[$_GET['msg']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة المنتجات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Cairo',Tahoma,sans-serif;background:radial-gradient(circle at top,#0ea5e9,#0f172a 45%,#020617);color:white;min-height:100vh}
.container{width:100%;max-width:1300px;margin:auto;padding:16px}
.header{position:sticky;top:0;z-index:850;display:flex;flex-direction:column;align-items:stretch;gap:10px;margin:0 0 16px;padding:10px 0 12px;background:linear-gradient(180deg,rgba(2,6,23,.96),rgba(2,6,23,.84) 78%,transparent);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.logo{font-size:26px;font-weight:bold}
<?= app_nav_styles() ?>
.nav{display:flex;flex-wrap:nowrap;gap:4px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:5px}
.nav a{flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e2e8f0;text-decoration:none;padding:11px 8px;border-radius:14px;font-weight:800;font-size:14px;border:0;background:transparent}
.nav a:hover{background:rgba(255,255,255,.08);color:#fff}
.nav a.active{background:rgba(255,255,255,.22);color:#fff;box-shadow:0 8px 18px rgba(0,0,0,.18)}
.btn-nav-dash.active{background:linear-gradient(180deg,rgba(255,200,160,.55),rgba(251,146,60,.38))}
.btn-nav-products.active{background:linear-gradient(180deg,rgba(190,242,100,.50),rgba(132,204,22,.32))}
.btn-nav-sales.active{background:linear-gradient(180deg,rgba(196,181,253,.55),rgba(139,92,246,.34))}
.btn-nav-pharmacies.active{background:linear-gradient(180deg,rgba(249,168,212,.55),rgba(236,72,153,.34))}
.msg{background:#22c55e;padding:14px;border-radius:16px;margin-bottom:18px;font-weight:bold}
.card{background:rgba(255,255,255,.12);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:18px;margin-bottom:22px}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px}
input{width:100%;padding:14px;border:0;border-radius:14px;font-size:15px;outline:none;background:rgba(255,255,255,.9);font-family:inherit}
.btn{background:linear-gradient(180deg,#f472b6,#db2777);color:white;border:0;border-radius:14px;padding:14px;font-weight:bold;cursor:pointer;font-size:16px;width:100%;font-family:inherit}
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;min-width:700px;border-collapse:separate;border-spacing:0 10px}
th{text-align:center;color:#bfdbfe;padding:10px;white-space:nowrap}
td{background:rgba(255,255,255,.12);padding:12px;text-align:center;vertical-align:middle}
tr td:first-child{border-radius:0 14px 14px 0}
tr td:last-child{border-radius:14px 0 0 14px}
.prod-img{width:60px;height:60px;object-fit:cover;border-radius:10px}
.btn-edit{background:linear-gradient(180deg,#fcd34d,#f59e0b);color:white;border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:bold;margin-bottom:5px;width:100%;font-family:inherit}
.btn-delete{background:linear-gradient(180deg,#fda4af,#e11d48);color:white;border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:bold;width:100%;font-family:inherit}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);align-items:center;justify-content:center;z-index:1000;padding:15px}
.modal-content{background:#0f172a;padding:20px;border-radius:20px;width:100%;max-width:400px;border:1px solid rgba(255,255,255,.2)}
.modal-content h3{margin-top:0}
.close{float:left;cursor:pointer;font-size:24px;color:#e11d48}
@media(max-width:600px){.logo{font-size:21px}.nav a{font-size:11px;padding:9px 4px}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <?php app_nav('products'); ?>
    </div>

    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <h2>إضافة منتج جديد</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="form-grid">
                <input type="text" name="name" placeholder="اسم المنتج" required>
                <input type="number" name="price" placeholder="السعر (د.ع)" required>
                <input type="number" name="target_qty" placeholder="التاركت المطلوب (عدد القطع)" required>
                <input type="file" name="image" accept="image/*">
            </div>
            <button class="btn" name="add">إضافة المنتج</button>
        </form>
    </div>

    <div class="card">
        <h2>قائمة المنتجات</h2>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>الصورة</th>
                    <th>الاسم</th>
                    <th>السعر</th>
                    <th>تاركت القطع</th>
                    <th>الإجراءات</th>
                </tr>
                <?php foreach(array_reverse($products) as $p): ?>
                <tr>
                    <td>
                        <?php if($p['image']): ?>
                            <img class="prod-img" src="<?= $uploadPath . htmlspecialchars($p['image']) ?>" alt="">
                        <?php else: ?>
                            <div class="prod-img" style="background:#334155;display:inline-block;"></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= number_format($p['price']) ?> د.ع</td>
                    <td><?= number_format($p['target_qty']) ?> قطعة</td>
                    <td style="width:120px;">
                        <button class="btn-edit" onclick='openEdit(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
                        <form method="post" onsubmit="return confirm('متأكد من حذف المنتج؟');" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn-delete" name="delete">حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEdit()">&times;</span>
        <h3>تعديل المنتج</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid" style="grid-template-columns:1fr;">
                <input type="text" name="name" id="edit_name" placeholder="اسم المنتج" required>
                <input type="number" name="price" id="edit_price" placeholder="السعر" required>
                <input type="number" name="target_qty" id="edit_target" placeholder="التاركت (عدد القطع)" required>
                <label style="font-size:14px;color:#cbd5e1;">تغيير الصورة (اختياري)</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <button class="btn" name="edit" style="background:linear-gradient(180deg,#fcd34d,#f59e0b);">حفظ التعديلات</button>
        </form>
    </div>
</div>

<script>
function openEdit(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_name').value = p.name;
    document.getElementById('edit_price').value = p.price;
    document.getElementById('edit_target').value = p.target_qty || 0;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) closeEdit();
}
</script>
</body>
</html>
