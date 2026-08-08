<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 0) != 1) {
    header('Location: /login.php');
    exit;
}


$seller_id = $_SESSION['seller_id'];
$username  = $_SESSION['username'] ?? 'Seller';

// 撈統計數字
$ch = curl_init("http://api:8080/seller/products?seller_id=$seller_id");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$res      = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$products     = ($http_code === 200) ? (json_decode($res, true)['products'] ?? []) : [];
$product_count = count($products);

$ch2 = curl_init("http://api:8080/seller/orders?seller_id=$seller_id");
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$res2      = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$orders      = ($http_code2 === 200) ? (json_decode($res2, true)['orders'] ?? []) : [];
$order_count  = count($orders);
$total_revenue = array_sum(array_column($orders, 'total'));
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>賣家後台</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --bg:#F5F3EE; --surface:#fff; --border:#E0DDD6;
  --text:#1C1A17; --muted:#78746A;
  --accent:#1C1A17; --accent-fg:#F5F3EE;
  --success:#1A7A4A; --success-bg:#E6F4EC;
  --r:10px; --font:'DM Sans',sans-serif; --font-d:'Noto Serif TC',serif;
}
body { font-family:var(--font); background:var(--bg); color:var(--text); min-height:100vh; padding:2.5rem 1rem 5rem; }
.wrap { max-width:900px; margin:0 auto; }

/* ── 頁首 ── */
.page-head { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:2rem; }
.page-head h1 { font-family:var(--font-d); font-size:1.8rem; font-weight:600; }
.page-head p  { font-size:.83rem; color:var(--muted); margin-top:4px; }
.logout { font-size:.82rem; color:var(--muted); text-decoration:none; }
.logout:hover { color:var(--text); }

/* ── 統計卡片 ── */
.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:2rem; }
@media(max-width:580px){ .stats{ grid-template-columns:1fr; } }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); padding:20px 22px; }
.stat-label { font-size:.75rem; color:var(--muted); letter-spacing:.06em; text-transform:uppercase; }
.stat-value { font-family:var(--font-d); font-size:1.8rem; font-weight:600; margin-top:6px; }
.stat-value.green { color:var(--success); }

/* ── 功能卡片 ── */
.menu-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
@media(max-width:500px){ .menu-grid{ grid-template-columns:1fr; } }
.menu-card {
  background:var(--surface); border:1px solid var(--border); border-radius:var(--r);
  padding:24px; text-decoration:none; color:var(--text);
  display:flex; flex-direction:column; gap:8px;
  transition:box-shadow .15s;
}
.menu-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
.menu-icon { font-size:1.8rem; }
.menu-title { font-size:1rem; font-weight:600; font-family:var(--font-d); }
.menu-desc  { font-size:.83rem; color:var(--muted); }
.menu-arrow { margin-top:auto; font-size:.82rem; color:var(--muted); }

.section-title { font-family:var(--font-d); font-size:1.1rem; font-weight:600; margin:2rem 0 1rem; }
</style>
</head>
<body>
<div class="wrap">

  <div class="page-head">
    <div>
      <h1>賣家後台</h1>
      <p>歡迎回來，<?= htmlspecialchars($username) ?></p>
    </div>
    <a href="/index.php" class="logout">← 前往買家首頁</a>
  </div>

  <!-- 統計 -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-label">上架商品</div>
      <div class="stat-value"><?= $product_count ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">待出貨訂單</div>
      <div class="stat-value"><?= $order_count ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">待出貨金額</div>
      <div class="stat-value green">NT$<?= number_format($total_revenue) ?></div>
    </div>
  </div>

  <!-- 功能連結 -->
  <div class="section-title">功能選單</div>
  <div class="menu-grid">

    <a class="menu-card" href="/seller_products.php">
      <div class="menu-icon">📦</div>
      <div class="menu-title">商品管理</div>
      <div class="menu-desc">查看、編輯、下架你的商品</div>
      <div class="menu-arrow">前往 →</div>
    </a>

    <a class="menu-card" href="/seller_product_add.php">
      <div class="menu-icon">➕</div>
      <div class="menu-title">新增商品</div>
      <div class="menu-desc">上架新商品到你的店鋪</div>
      <div class="menu-arrow">前往 →</div>
    </a>

    <a class="menu-card" href="/seller_orders.php">
      <div class="menu-icon">🚚</div>
      <div class="menu-title">待出貨訂單</div>
      <div class="menu-desc">查看並確認出貨狀態</div>
      <div class="menu-arrow">前往 →</div>
    </a>

    <!-- <a class="menu-card" href="/seller_products.php?edit=1">
      <div class="menu-icon">✏️</div>
      <div class="menu-title">編輯商品</div>
      <div class="menu-desc">修改商品資訊、價格與庫存</div>
      <div class="menu-arrow">前往 →</div>
    </a> -->

  </div>
</div>
</body>
</html>
