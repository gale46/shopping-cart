<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 0) != 1) {
    header('Location: /login.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

$ch = curl_init("http://api:8080/seller/orders?seller_id=$seller_id");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$res      = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$orders = ($http_code === 200) ? (json_decode($res, true)['orders'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>待出貨訂單</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --bg:#F5F3EE; --surface:#fff; --border:#E0DDD6;
  --text:#1C1A17; --muted:#78746A;
  --accent:#1C1A17; --accent-fg:#F5F3EE;
  --danger:#B83232; --success:#1A7A4A;
  --r:10px; --font:'DM Sans',sans-serif; --font-d:'Noto Serif TC',serif;
}
body { font-family:var(--font); background:var(--bg); color:var(--text); min-height:100vh; padding:2.5rem 1rem 5rem; }
.wrap { max-width:900px; margin:0 auto; }
.back { font-size:.83rem; color:var(--muted); display:block; margin-bottom:1rem; }
.page-head { margin-bottom:1.75rem; }
.page-head h1 { font-family:var(--font-d); font-size:1.8rem; font-weight:600; }
.page-head p  { font-size:.83rem; color:var(--muted); margin-top:4px; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.card-hd { padding:12px 20px; border-bottom:1px solid var(--border); font-size:.75rem; font-weight:500; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; }
.order-row { display:flex; align-items:center; gap:16px; padding:16px 20px; border-bottom:1px solid var(--border); }
.order-row:last-child { border-bottom:none; }
.order-info { flex:1; }
.order-no   { font-size:.9rem; font-weight:500; }
.order-meta { font-size:.78rem; color:var(--muted); margin-top:3px; }
.order-total { font-size:.95rem; font-weight:600; font-family:var(--font-d); min-width:100px; text-align:right; }
.badge { display:inline-block; font-size:.7rem; padding:3px 8px; border-radius:99px; font-weight:500; }
.b-pending { background:#FEF3CD; color:#7A5800; }
.b-paid    { background:#DBEAFE; color:#1E40AF; }
.b-shipped { background:#E6F4EC; color:var(--success); }
.btn-ship {
  padding:8px 18px; background:var(--accent); color:var(--accent-fg);
  border:none; border-radius:7px; font-size:.82rem; font-weight:500;
  font-family:var(--font); cursor:pointer; transition:opacity .15s; white-space:nowrap;
}
.btn-ship:hover { opacity:.82; }
.btn-ship:disabled { opacity:.35; cursor:not-allowed; }
.empty { padding:48px 20px; text-align:center; color:var(--muted); font-size:.9rem; }

.toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(10px); background:var(--text); color:var(--accent-fg); padding:10px 22px; border-radius:99px; font-size:.87rem; opacity:0; pointer-events:none; transition:opacity .22s,transform .22s; white-space:nowrap; z-index:999; }
.toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="/seller_dashboard.php">← 返回後台</a>
  <div class="page-head">
    <h1>待出貨訂單</h1>
    <p>共 <?= count($orders) ?> 筆待處理</p>
  </div>

  <div class="card">
    <div class="card-hd">訂單列表</div>
    <?php if (empty($orders)): ?>
      <div class="empty">目前沒有待出貨訂單 🎉</div>
    <?php else: ?>
      <?php foreach ($orders as $o):
        $badgeClass = match($o['status']) {
          'paid'    => 'b-paid',
          'shipped' => 'b-shipped',
          default   => 'b-pending',
        };
        $badgeLabel = match($o['status']) {
          'paid'    => '已付款',
          'shipped' => '已出貨',
          default   => '待付款',
        };
      ?>
        <div class="order-row" id="order-row-<?= $o['order_id'] ?>">
          <div class="order-info">
            <div class="order-no">
              訂單 #<?= $o['order_id'] ?>
              <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
            </div>
            <div class="order-meta">
              買家 UID：<?= $o['user_id'] ?>
              &nbsp;·&nbsp;
              <?= date('Y/m/d H:i', strtotime($o['created_at'])) ?>
            </div>
          </div>
          <div class="order-total">NT$<?= number_format($o['total']) ?></div>
          <?php if ($o['status'] !== 'shipped'): ?>
            <button class="btn-ship"
                    id="ship-btn-<?= $o['order_id'] ?>"
                    onclick="shipOrder(<?= $o['order_id'] ?>)">
              確認出貨
            </button>
          <?php else: ?>
            <button class="btn-ship" disabled>已出貨</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const SELLER_ID = <?= $seller_id ?>;

async function shipOrder(orderId) {
  const btn = document.getElementById('ship-btn-' + orderId);
  btn.disabled = true;
  btn.textContent = '處理中…';

  const res  = await fetch('http://api:8080/seller/order/ship', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ seller_id: SELLER_ID, order_id: orderId })
  });
  const data = await res.json();

  if (res.ok) {
    btn.textContent = '已出貨';
    // 更新 badge
    const row   = document.getElementById('order-row-' + orderId);
    const badge = row.querySelector('.badge');
    badge.className = 'badge b-shipped';
    badge.textContent = '已出貨';
    showToast('訂單 #' + orderId + ' 已標記為出貨');
  } else {
    btn.disabled = false;
    btn.textContent = '確認出貨';
    showToast(data.error ?? '操作失敗');
  }
}

function showToast(msg, ms = 2800) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), ms);
}
</script>
</body>
</html>
