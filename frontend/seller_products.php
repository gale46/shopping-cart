<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 0) != 1) {
    header('Location: /login.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

$ch = curl_init("http://api:8080/seller/products?seller_id=$seller_id");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$res      = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$products = ($http_code === 200) ? (json_decode($res, true)['products'] ?? []) : [];

$toast = $_SESSION['seller_toast'] ?? '';
unset($_SESSION['seller_toast']);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商品管理</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --bg:#F5F3EE; --surface:#fff; --border:#E0DDD6;
  --text:#1C1A17; --muted:#78746A;
  --accent:#1C1A17; --accent-fg:#F5F3EE;
  --danger:#B83232; --r:10px;
  --font:'DM Sans',sans-serif; --font-d:'Noto Serif TC',serif;
}
body { font-family:var(--font); background:var(--bg); color:var(--text); min-height:100vh; padding:2.5rem 1rem 5rem; }
.wrap { max-width:900px; margin:0 auto; }
.page-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.75rem; }
.page-head h1 { font-family:var(--font-d); font-size:1.8rem; font-weight:600; }
.btn-add { background:var(--accent); color:var(--accent-fg); padding:10px 20px; border-radius:var(--r); font-size:.88rem; font-weight:500; text-decoration:none; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.card-hd { padding:12px 20px; border-bottom:1px solid var(--border); font-size:.75rem; font-weight:500; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; }
.product-row { display:flex; align-items:center; gap:14px; padding:14px 20px; border-bottom:1px solid var(--border); }
.product-row:last-child { border-bottom:none; }
.p-thumb { width:48px; height:48px; border-radius:8px; border:1px solid var(--border); background:var(--bg); display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; overflow:hidden; }
.p-thumb img { width:100%; height:100%; object-fit:cover; }
.p-info { flex:1; min-width:0; }
.p-name  { font-size:.9rem; font-weight:500; }
.p-meta  { font-size:.78rem; color:var(--muted); margin-top:3px; }
.p-stock { font-size:.8rem; padding:2px 8px; border-radius:99px; font-weight:500; }
.stock-ok   { background:#E6F4EC; color:#1A7A4A; }
.stock-low  { background:#FEF3CD; color:#7A5800; }
.stock-zero { background:#FDE8E6; color:var(--danger); }
.p-actions { display:flex; gap:8px; }
.btn-sm { padding:6px 14px; border-radius:7px; font-size:.8rem; font-weight:500; font-family:var(--font); cursor:pointer; border:1px solid var(--border); background:var(--surface); color:var(--text); text-decoration:none; }
.btn-sm:hover { background:var(--bg); }
.btn-del { color:var(--danger); border-color:#F5C6C6; }
.btn-del:hover { background:#FDE8E6; }
.empty { padding:48px 20px; text-align:center; color:var(--muted); font-size:.9rem; }
.back { font-size:.83rem; color:var(--muted); margin-bottom:1rem; display:block; }
.back:hover { color:var(--text); }

.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(10px); background:var(--text); color:var(--accent-fg); padding:10px 22px; border-radius:99px; font-size:.87rem; opacity:0; pointer-events:none; transition:opacity .22s,transform .22s; white-space:nowrap; z-index:999; }
.toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="/seller_dashboard.php">← 返回後台</a>
  <div class="page-head">
    <h1>商品管理</h1>
    <a href="/seller_product_add.php" class="btn-add">＋ 新增商品</a>
  </div>

  <div class="card">
    <div class="card-hd">共 <?= count($products) ?> 件商品</div>
    <?php if (empty($products)): ?>
      <div class="empty">還沒有上架任何商品 &nbsp;·&nbsp; <a href="/seller_product_add.php">立即新增</a></div>
    <?php else: ?>
      <?php foreach ($products as $p):
        $stockClass = $p['stock'] == 0 ? 'stock-zero' : ($p['stock'] <= 5 ? 'stock-low' : 'stock-ok');
        $stockLabel = $p['stock'] == 0 ? '缺貨' : ('庫存 ' . $p['stock']);
        $thumb = $p['image_url']
          ? '<img src="http://localhost:3000/uploads/product/' . htmlspecialchars($p['image_url']) . '" alt="">'
          : '🛍';
      ?>
        <div class="product-row">
          <div class="p-thumb"><?= $thumb ?></div>
          <div class="p-info">
            <div class="p-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="p-meta">
              NT$<?= number_format($p['price']) ?>
              &nbsp;·&nbsp;
              <span class="p-stock <?= $stockClass ?>"><?= $stockLabel ?></span>
            </div>
          </div>
          <div class="p-actions">
            <a href="/seller_product_edit.php?product_id=<?= $p['id'] ?>" class="btn-sm">編輯</a>
            <button class="btn-sm btn-del" onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">下架</button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="toast" id="toast"><?= htmlspecialchars($toast) ?></div>

<script>
// 顯示 session toast
window.addEventListener('load', () => {
  const t = document.getElementById('toast');
  if (t.textContent.trim()) {
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  }
});

async function deleteProduct(productId, name) {
  if (!confirm(`確定要下架「${name}」嗎？`)) return;

  const res  = await fetch('http://api:8080/seller/product/delete', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({
      seller_id:  <?= $seller_id ?>,
      product_id: productId
    })
  });
  const data = await res.json();
  if (res.ok) {
    location.reload();
  } else {
    alert(data.error ?? '下架失敗');
  }
}
</script>
</body>
</html>
