<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

$user_id  = $_SESSION['user_id']  ?? null;
$username = $_SESSION['username'] ?? null;
$role     = $_SESSION['role']     ?? 0;

$json     = file_get_contents('http://api:8080/products');
$products = json_decode($json, true) ?? [];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>購物首頁</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:        #F5F3EE;
  --surface:   #FFFFFF;
  --border:    #E0DDD6;
  --text:      #1C1A17;
  --muted:     #78746A;
  --accent:    #1C1A17;
  --accent-fg: #F5F3EE;
  --danger:    #B83232;
  --success:   #1A7A4A;
  --r:         10px;
  --font:      'DM Sans', sans-serif;
  --font-d:    'Noto Serif TC', serif;
}
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ── Navbar ── */
.navbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 2rem;
  height: 58px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
}
.nav-logo {
  font-family: var(--font-d);
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
}
.nav-right {
  display: flex;
  align-items: center;
  gap: 16px;
}
.nav-link {
  font-size: .85rem;
  color: var(--muted);
  text-decoration: none;
  transition: color .15s;
}
.nav-link:hover { color: var(--text); }

.nav-btn {
  font-size: .83rem;
  font-weight: 500;
  padding: 7px 16px;
  border-radius: 7px;
  text-decoration: none;
  transition: opacity .15s;
}
.nav-btn-outline {
  border: 1px solid var(--border);
  color: var(--text);
  background: var(--surface);
}
.nav-btn-solid {
  background: var(--accent);
  color: var(--accent-fg);
  border: 1px solid var(--accent);
}
.nav-btn:hover { opacity: .8; }

.nav-user {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: .85rem;
  color: var(--muted);
}
.avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: .78rem; font-weight: 600; color: var(--text);
}

/* ── Seller Banner（只有 role=0 且已登入才顯示）── */
.seller-banner {
  background: linear-gradient(135deg, #1C1A17 0%, #3A3530 100%);
  color: #F5F3EE;
  padding: 18px 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.seller-banner-text h2 { font-family: var(--font-d); font-size: 1rem; font-weight: 600; }
.seller-banner-text p  { font-size: .82rem; opacity: .7; margin-top: 3px; }
.seller-banner-btn {
  white-space: nowrap;
  padding: 9px 20px;
  background: #F5F3EE;
  color: #1C1A17;
  border-radius: 7px;
  font-size: .85rem;
  font-weight: 500;
  text-decoration: none;
  flex-shrink: 0;
  transition: opacity .15s;
}
.seller-banner-btn:hover { opacity: .85; }

/* ── Seller 後台按鈕（role=1）── */
.seller-topbar {
  background: var(--accent);
  color: var(--accent-fg);
  padding: 10px 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: .85rem;
}
.seller-topbar a {
  color: var(--accent-fg);
  font-weight: 500;
  text-decoration: none;
  padding: 5px 14px;
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 6px;
  transition: background .15s;
}
.seller-topbar a:hover { background: rgba(255,255,255,.12); }

/* ── 頁首 ── */
.page-head {
  max-width: 1100px;
  margin: 2rem auto 1.25rem;
  padding: 0 1.5rem;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}
.page-head h1 { font-family: var(--font-d); font-size: 1.6rem; font-weight: 600; }
.page-head p  { font-size: .83rem; color: var(--muted); }

/* ── 商品 Grid ── */
.product-grid {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 1.5rem 4rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}

/* ── 商品卡片 ── */
.product-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform .2s, box-shadow .2s;
}
.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,.07);
}

.card-image {
  width: 100%;
  aspect-ratio: 1;
  background: var(--bg);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.card-image a { display: block; width: 100%; height: 100%; }
.card-image img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: opacity .2s;
}
.card-image img:hover { opacity: .9; }
.card-image .no-img { font-size: .8rem; color: var(--border); }

.card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
.card-name  { font-size: .92rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-price { font-size: .85rem; color: var(--muted); }

.card-action {
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 8px;
}
.qty-label { font-size: .75rem; color: var(--muted); white-space: nowrap; }
.qty-input {
  width: 52px;
  height: 32px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-family: var(--font);
  font-size: .88rem;
  text-align: center;
  background: var(--surface);
  color: var(--text);
  outline: none;
}
.qty-input:focus { border-color: var(--text); }
.btn-cart {
  flex: 1;
  height: 32px;
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  border-radius: 6px;
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font);
  cursor: pointer;
  transition: opacity .15s;
}
.btn-cart:hover { opacity: .82; }

/* ── 空狀態 ── */
.empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
  font-size: .9rem;
}

/* ── Toast ── */
.toast {
  position: fixed; bottom: 28px; left: 50%;
  transform: translateX(-50%) translateY(10px);
  background: var(--text); color: var(--accent-fg);
  padding: 10px 22px; border-radius: 99px;
  font-size: .87rem; opacity: 0; pointer-events: none;
  transition: opacity .22s, transform .22s;
  white-space: nowrap; z-index: 999;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
  <a href="/index.php" class="nav-logo">🛍 ShopCart</a>
  <div class="nav-right">
    <?php if ($user_id): ?>
      <a href="/cart.php" class="nav-link">購物車</a>
      <a href="/order.php" class="nav-link">我的訂單</a>
      <div class="nav-user">
        <div class="avatar"><?= mb_substr($username ?? 'U', 0, 1) ?></div>
        <?= htmlspecialchars($username) ?>
      </div>
      <a href="/logout.php" class="nav-btn nav-btn-outline">登出</a>
    <?php else: ?>
      <a href="/login.php" class="nav-btn nav-btn-outline">登入</a>
      <a href="/register.php" class="nav-btn nav-btn-solid">註冊</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── 賣家後台 Banner（已是 seller）── -->
<?php if ($user_id && $role == 1): ?>
<div class="seller-topbar">
  <span>👋 你好，<?= htmlspecialchars($username) ?>！你是賣家身份</span>
  <a href="/seller_dashboard.php">進入賣家後台 →</a>
</div>

<!-- ── 成為賣家 Banner（已登入但不是 seller）── -->
<?php elseif ($user_id && $role == 0): ?>
<div class="seller-banner">
  <div class="seller-banner-text">
    <h2>想在這裡販售商品？</h2>
    <p>申請成為賣家，開始上架你的商品</p>
  </div>
  <a href="/become_seller.php" class="seller-banner-btn">成為賣家 →</a>
</div>
<?php endif; ?>

<!-- ── 頁首 ── -->
<div class="page-head">
  <h1>所有商品</h1>
  <p>共 <?= count($products) ?> 件商品</p>
</div>

<!-- ── 商品 Grid ── -->
<div class="product-grid">
  <?php if (empty($products)): ?>
    <div class="empty">目前沒有任何商品</div>
  <?php else: ?>
    <?php foreach ($products as $item):
      $pid = $item['product_id'];
    ?>
      <div class="product-card">
        <div class="card-image">
          <?php if (!empty($item['image_url'])): ?>
            <a href="/product_detail.php?product_id=<?= $pid ?>">
              <img src="<?= htmlspecialchars($item['image_url']) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>">
            </a>
          <?php else: ?>
            <span class="no-img">無圖片</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="card-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="card-price">NT$<?= number_format($item['price']) ?></div>
        </div>
        <div class="card-action">
          <span class="qty-label">數量</span>
          <input class="qty-input" type="number"
                 id="qty_<?= $pid ?>"
                 value="1" min="1"
                 oninput="if(this.value<1)this.value=1">
          <button class="btn-cart" onclick="addToCart(<?= $pid ?>)">加入購物車</button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
async function addToCart(productId) {
  <?php if (!$user_id): ?>
    alert('請先登入才能加入購物車');
    window.location.href = '/login.php';
    return;
  <?php endif; ?>
  const qtyInput = document.getElementById('qty_' + productId);
  const quantity = parseInt(qtyInput.value) || 1;

  try {
    const response = await fetch('cart.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    new URLSearchParams({ product_id: productId, quantity: quantity })
    });

    if (response.ok) {
      showToast('已加入購物車 ✓');
    } else {
      showToast('加入失敗，請稍後再試');
    }
  } catch (err) {
    showToast('連線錯誤');
  }
}

function showToast(msg, ms = 2500) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), ms);
}
</script>
</body>
</html>