<?php
// ── Session ───────────────────────────────────────────────────────
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// 沒有訂單資料就踢回首頁
if (empty($_SESSION['last_order'])) {
    header('Location: /index.php');
    exit;
}

$order    = $_SESSION['last_order'];
$order_id = $order['order_id'];

// 用完就清掉，避免重新整理重複顯示
unset($_SESSION['last_order']);

// ── 設定 ─────────────────────────────────────────────────────────
define('DB_HOST',        'mysql-db');
define('DB_NAME',        'ShoppingCart');
define('DB_USER',        'root');
define('DB_PASS',        '9151999');
define('DB_PORT',        '3306');
define('IMAGE_BASE_URL', 'http://localhost:3000/uploads/product/');

// ── PDO 連線 ──────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    exit('資料庫連線失敗');
}

// ── 推薦商品：取本次購買商品的同類，排除已購買的，隨機取 4 筆 ──
$bought_ids = array_column($order['items'], 'product_id');
if (empty($bought_ids)) {
    // 若 session 沒存 product_id，直接撈熱門商品
    $rec_stmt = $pdo->query("
        SELECT id, name, price, image_url
        FROM product
        WHERE deleted_at IS NULL
        ORDER BY RAND()
        LIMIT 4
    ");
} else {
    $placeholders = implode(',', array_fill(0, count($bought_ids), '?'));
    $rec_stmt = $pdo->prepare("
        SELECT id, name, price, image_url
        FROM product
        WHERE deleted_at IS NULL
          AND id NOT IN ($placeholders)
        ORDER BY RAND()
        LIMIT 4
    ");
    $rec_stmt->execute($bought_ids);
}
$recommendations = $rec_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>訂單完成</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #F5F3EE; --surface: #fff; --border: #E0DDD6;
  --text: #1C1A17; --muted: #78746A;
  --accent: #1C1A17; --accent-fg: #F5F3EE;
  --success: #1A7A4A; --success-bg: #E6F4EC;
  --r: 10px; --font: 'DM Sans',sans-serif; --font-d: 'Noto Serif TC',serif;
}
body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; padding: 2.5rem 1rem 5rem; }

.wrap { max-width: 760px; margin: 0 auto; }

/* ── 步驟列 ── */
.steps { display: flex; align-items: center; margin-bottom: 2.5rem; }
.step { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--muted); }
.step.done { color: var(--success); font-weight: 500; }
.step-num { width: 24px; height: 24px; border-radius: 50%; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 600; }
.step.done .step-num { background: var(--success); border-color: var(--success); color: #fff; }
.step-line { flex: 1; height: 1px; background: var(--border); margin: 0 12px; min-width: 32px; }
.step-line.done { background: var(--success); }

/* ── 成功橫幅 ── */
.success-banner {
  background: var(--success-bg);
  border: 1px solid #B7DFC7;
  border-radius: var(--r);
  padding: 24px 28px;
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 24px;
}
.success-icon { font-size: 2rem; flex-shrink: 0; }
.success-banner h1 { font-family: var(--font-d); font-size: 1.4rem; font-weight: 600; color: var(--success); }
.success-banner p  { font-size: .85rem; color: var(--success); margin-top: 4px; }

/* ── 卡片 ── */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); overflow: hidden; margin-bottom: 20px; }
.card-hd { padding: 13px 20px; border-bottom: 1px solid var(--border); font-size: .75rem; font-weight: 500; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; }
.card-body { padding: 18px 20px; }

/* ── 訂單資訊 grid ── */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; }
@media(max-width:500px){ .info-grid{ grid-template-columns: 1fr; } }
.info-item label { font-size: .75rem; color: var(--muted); display: block; margin-bottom: 3px; }
.info-item span  { font-size: .92rem; font-weight: 500; }

/* ── 商品列 ── */
.item-row { display: flex; align-items: center; gap: 12px; padding: 13px 20px; border-bottom: 1px solid var(--border); }
.item-row:last-child { border-bottom: none; }
.item-thumb { width: 48px; height: 48px; border-radius: 7px; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; overflow: hidden; }
.item-thumb img { width: 100%; height: 100%; object-fit: cover; }
.item-info { flex: 1; min-width: 0; }
.item-name { font-size: .9rem; font-weight: 500; }
.item-sub  { font-size: .78rem; color: var(--muted); margin-top: 2px; }
.item-price { font-size: .9rem; font-weight: 500; white-space: nowrap; }

/* ── 總計列 ── */
.total-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--border); }
.total-row span:first-child { font-size: .9rem; color: var(--muted); }
.total-row span:last-child  { font-size: 1.1rem; font-weight: 600; font-family: var(--font-d); }

/* ── 收件地址 ── */
.addr-block { font-size: .9rem; line-height: 1.8; }
.addr-label { font-size: .75rem; color: var(--muted); display: block; margin-bottom: 4px; }

/* ── 推薦商品 ── */
.section-title { font-family: var(--font-d); font-size: 1.1rem; font-weight: 600; margin: 32px 0 14px; }
.rec-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media(max-width:600px){ .rec-grid{ grid-template-columns: repeat(2, 1fr); } }
.rec-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--r);
  overflow: hidden; cursor: pointer; text-decoration: none; color: inherit;
  transition: box-shadow .15s;
  display: block;
}
.rec-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.rec-thumb { width: 100%; aspect-ratio: 1; background: var(--bg); display: flex; align-items: center; justify-content: center; font-size: 2rem; overflow: hidden; }
.rec-thumb img { width: 100%; height: 100%; object-fit: cover; }
.rec-info { padding: 10px 12px; }
.rec-name  { font-size: .85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rec-price { font-size: .82rem; color: var(--muted); margin-top: 3px; }

/* ── 底部按鈕 ── */
.btn-row { display: flex; gap: 12px; margin-top: 28px; }
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; border-radius: var(--r); font-size: .92rem; font-weight: 500; font-family: var(--font); cursor: pointer; text-decoration: none; transition: opacity .15s; border: none; }
.btn-primary { background: var(--accent); color: var(--accent-fg); }
.btn-secondary { background: var(--surface); color: var(--text); border: 1px solid var(--border); }
.btn:hover { opacity: .82; }
</style>
</head>
<body>
<div class="wrap">

  <!-- 步驟列 -->
  <div class="steps">
    <div class="step done"><span class="step-num">✓</span> 購物車</div>
    <div class="step-line done"></div>
    <div class="step done"><span class="step-num">✓</span> 填寫資料</div>
    <div class="step-line done"></div>
    <div class="step done"><span class="step-num">✓</span> 完成訂單</div>
  </div>

  <!-- 成功橫幅 -->
  <div class="success-banner">
    <div class="success-icon">🎉</div>
    <div>
      <h1>訂單送出成功！</h1>
      <p>訂單編號 #<?= $order_id ?>，我們將盡快為您出貨</p>
    </div>
  </div>

  <!-- 訂單資訊 -->
  <div class="card">
    <div class="card-hd">訂單資訊</div>
    <div class="card-body">
      <div class="info-grid">
        <div class="info-item">
          <label>訂單編號</label>
          <span>#<?= $order_id ?></span>
        </div>
        <div class="info-item">
          <label>訂單金額</label>
          <span>NT$<?= number_format($order['total']) ?></span>
        </div>
        <div class="info-item">
          <label>訂單狀態</label>
          <span style="color:var(--success)">待出貨</span>
        </div>
        <div class="info-item">
          <label>建立時間</label>
          <span><?= date('Y/m/d H:i') ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- 收件資訊 -->
  <div class="card">
    <div class="card-hd">收件資訊</div>
    <div class="card-body">
      <div class="info-grid">
        <div class="info-item">
          <label>收件人</label>
          <span><?= htmlspecialchars($order['name']) ?></span>
        </div>
        <div class="info-item">
          <label>聯絡電話</label>
          <span><?= htmlspecialchars($order['phone']) ?></span>
        </div>
        <div class="info-item" style="grid-column: 1 / -1">
          <label>收件地址</label>
          <span>
            <?php if ($order['zip']): ?>(<?= htmlspecialchars($order['zip']) ?>)<?php endif; ?>
            <?= htmlspecialchars($order['city']) ?>
            <?= htmlspecialchars($order['address']) ?>
          </span>
        </div>
        <?php if ($order['note']): ?>
        <div class="info-item" style="grid-column: 1 / -1">
          <label>備註</label>
          <span><?= htmlspecialchars($order['note']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 商品明細 -->
  <div class="card">
    <div class="card-hd">商品明細</div>
    <?php foreach ($order['items'] as $item):
      $thumb = $item['image_url']
        ? '<img src="' . htmlspecialchars(IMAGE_BASE_URL . $item['image_url']) . '" alt="">'
        : '🛍';
    ?>
      <div class="item-row">
        <div class="item-thumb"><?= $thumb ?></div>
        <div class="item-info">
          <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="item-sub">NT$<?= number_format($item['price']) ?> × <?= $item['quantity'] ?> 件</div>
        </div>
        <div class="item-price">NT$<?= number_format($item['price'] * $item['quantity']) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="total-row">
      <span>訂單總計</span>
      <span>NT$<?= number_format($order['total']) ?></span>
    </div>
  </div>

  <!-- 推薦商品 -->
  <?php if (!empty($recommendations)): ?>
  <div class="section-title">你可能也會喜歡</div>
  <div class="rec-grid">
    <?php foreach ($recommendations as $rec):
      $rec_thumb = $rec['image_url']
        ? '<img src="' . htmlspecialchars(IMAGE_BASE_URL . $rec['image_url']) . '" alt="">'
        : '🛍';
    ?>
      <a class="rec-card" href="/product_detail.php?product_id=<?= $rec['id'] ?>">
        <div class="rec-thumb"><?= $rec_thumb ?></div>
        <div class="rec-info">
          <div class="rec-name"><?= htmlspecialchars($rec['name']) ?></div>
          <div class="rec-price">NT$<?= number_format($rec['price']) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 底部按鈕 -->
  <div class="btn-row">
    <a href="/index.php" class="btn btn-primary">繼續購物</a>
    <a href="/order.php" class="btn btn-secondary">查看所有訂單</a>
  </div>

</div>
</body>
</html>