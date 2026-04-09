<?php
ini_set('session.save_handler', 'rediscluster');
$path = 'seed[]=redis-1:6379&seed[]=redis-2:6379&seed[]=redis-3:6379&timeout=1&read_timeout=1';
ini_set('session.save_path', $path);

session_start();

if (isset($_SESSION['user_id'])) {
    echo "登入的使用者 ID 是：" . $_SESSION['user_id'];
} else {
    echo "尚未登入";
}
/**
 * order.php
 * 顯示當前登入使用者的訂單列表與明細
 *
 * 資料表：
 *   orders      (id, user_id, total_price, status, created_at)
 *   order_items (id, order_id, product_id, quantity, price_at_purchase)
 *   product     (id, name, image_url)
 */

// ── 資料庫設定 ────────────────────────────────────────────────────
define('DB_HOST', 'mysql-db');
define('DB_NAME', 'ShoppingCart');
define('DB_USER', 'root');
define('DB_PASS', '9151999');
define('DB_PORT', '3306');
define('IMAGE_BASE_URL', 'http://localhost:3000/uploads/product/');


$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ── PDO 連線 ──────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('資料庫連線失敗：' . htmlspecialchars($e->getMessage()));
}

// ── 若 URL 帶 ?id=N，只顯示單筆訂單 ─────────────────────────────
$single_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── 讀取訂單列表 ──────────────────────────────────────────────────
$where = $single_id
    ? 'WHERE o.user_id = ? AND o.id = ?'
    : 'WHERE o.user_id = ?';

$params = $single_id ? [$user_id, $single_id] : [$user_id];

$stmt = $pdo->prepare("
    SELECT id, total_price, status, created_at
    FROM orders o
    $where
    ORDER BY created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ── 讀取每筆訂單的明細（batch 查詢，避免 N+1）────────────────────
$order_ids = array_column($orders, 'id');
$items_map = []; // order_id => [items]

if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT
            oi.order_id,
            oi.quantity,
            oi.price_at_purchase,
            p.id        AS product_id,
            p.name      AS product_name,
            p.image_url
        FROM order_items oi
        LEFT JOIN product p ON p.id = oi.product_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.id ASC
    ");
    $stmt->execute($order_ids);
    foreach ($stmt->fetchAll() as $row) {
        $items_map[$row['order_id']][] = $row;
    }
}

// ── 狀態對應 ─────────────────────────────────────────────────────
function statusLabel(string $status): array {
    return match ($status) {
        'pending'  => ['待付款', 'b-warn'],
        'paid'     => ['已付款', 'b-blue'],
        'shipped'  => ['已出貨', 'b-ok'],
        'cancelled'=> ['已取消', 'b-out'],
        default    => [$status,  'b-muted'],
    };
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>我的訂單</title>
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
  padding: 2.5rem 1rem 5rem;
}

/* ── 頁首 ── */
.page-head {
  max-width: 780px; margin: 0 auto 1.75rem;
  display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
}
.page-head h1 { font-family: var(--font-d); font-size: 1.8rem; font-weight: 600; letter-spacing: -0.02em; }
.page-head a  { font-size: 0.83rem; color: var(--muted); text-underline-offset: 3px; }

.wrap { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }

/* ── 訂單卡片 ── */
.order-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  animation: fadeUp 0.22s ease both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(7px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── 卡片 header ── */
.order-head {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  user-select: none;
}
.order-head:hover { background: #FAFAF8; }

.order-no   { font-size: 0.82rem; font-weight: 500; color: var(--muted); }
.order-date { font-size: 0.8rem; color: var(--muted); }
.order-total {
  margin-left: auto;
  font-size: 1rem; font-weight: 600;
  font-family: var(--font-d);
}
.chevron {
  font-size: 0.75rem; color: var(--muted);
  transition: transform 0.2s;
  flex-shrink: 0;
}
.order-card.open .chevron { transform: rotate(180deg); }

/* ── badge ── */
.badge {
  display: inline-block; font-size: 0.7rem;
  padding: 3px 8px; border-radius: 99px; font-weight: 500;
}
.b-warn  { background: #FEF3CD; color: #7A5800; }
.b-blue  { background: #DBEAFE; color: #1E40AF; }
.b-ok    { background: #E6F4EC; color: var(--success); }
.b-out   { background: #FDE8E6; color: var(--danger); }
.b-muted { background: #F0EDE8; color: var(--muted); }

/* ── 明細（可收合）── */
.order-body {
  display: none;
  padding: 0 20px 16px;
}
.order-card.open .order-body { display: block; }

.item-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}
.item-row:last-child { border-bottom: none; }

.item-thumb {
  width: 44px; height: 44px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; flex-shrink: 0; overflow: hidden;
}
.item-thumb img { width: 100%; height: 100%; object-fit: cover; }

.item-info { flex: 1; min-width: 0; }
.item-name { font-size: 0.88rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-meta { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

.item-price { font-size: 0.88rem; font-weight: 500; text-align: right; white-space: nowrap; }

/* ── 空狀態 ── */
.empty {
  text-align: center; padding: 60px 20px;
  color: var(--muted); font-size: 0.9rem;
}
.empty a { color: var(--text); font-weight: 500; text-underline-offset: 3px; }

/* ── 單筆訂單返回列 ── */
.back-bar {
  max-width: 780px; margin: 0 auto 1rem;
  font-size: 0.83rem;
}
.back-bar a { color: var(--muted); text-underline-offset: 3px; }
</style>
</head>
<body>

<?php if ($single_id): ?>
  <div class="back-bar"><a href="/order.php">← 所有訂單</a></div>
<?php endif; ?>

<div class="page-head">
  <h1><?= $single_id ? '訂單詳情' : '我的訂單' ?></h1>
  <a href="/index.php">← 繼續購物</a>
</div>

<div class="wrap">
  <?php if (empty($orders)): ?>
    <div class="order-card">
      <div class="empty">
        <?= $single_id ? '找不到此訂單' : '還沒有任何訂單' ?>
        &nbsp;·&nbsp; <a href="/checkout.php">去結帳</a>
      </div>
    </div>

  <?php else: ?>
    <?php foreach ($orders as $idx => $order):
      $oid   = (int)$order['id'];
      $items = $items_map[$oid] ?? [];
      [$label, $cls] = statusLabel($order['status']);
      // 預設展開第一筆（或單筆模式全展開）
      $open  = ($idx === 0 || $single_id) ? ' open' : '';
    ?>
      <div class="order-card<?= $open ?>"
           id="order-<?= $oid ?>"
           style="animation-delay:<?= $idx * 0.06 ?>s">

        <!-- ── 卡片標題（點擊收合）── -->
        <div class="order-head" onclick="toggleOrder(<?= $oid ?>)">
          <div>
            <div class="order-no">訂單 #<?= $oid ?></div>
            <div class="order-date">
              <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
            </div>
          </div>
          <span class="badge <?= $cls ?>"><?= $label ?></span>
          <div class="order-total">
            NT$<?= number_format($order['total_price'], 0) ?>
          </div>
          <span class="chevron">▼</span>
        </div>

        <!-- ── 商品明細 ── -->
        <div class="order-body">
          <?php if (empty($items)): ?>
            <p style="font-size:0.83rem;color:var(--muted);padding:14px 0;">無商品明細</p>
          <?php else: ?>
            <?php foreach ($items as $item):
              $subtotal = $item['price_at_purchase'] * $item['quantity'];
              $raw_url = $item['image_url'];
                $full_url = "";
                
                if (!empty($raw_url)) {
                    // 如果 image_url 本身就是 http 開頭，就不補 prefix，否則加上 IMAGE_BASE_URL
                    $full_url = (strpos($raw_url, 'http') === 0) 
                                ? $raw_url 
                                : IMAGE_BASE_URL . ltrim($raw_url, '/');
                }

                $thumb = !empty($full_url)
                    ? '<img src="' . htmlspecialchars($full_url) . '" alt="">'
                    : '🛍';
            ?>
              <div class="item-row">
                <div class="item-thumb"><?= $thumb ?></div>
                <div class="item-info">
                  <div class="item-name"><?= htmlspecialchars($item['product_name'] ?? '商品已下架') ?></div>
                  <div class="item-meta">
                    NT$<?= number_format($item['price_at_purchase'], 0) ?> × <?= $item['quantity'] ?> 件
                  </div>
                </div>
                <div class="item-price">NT$<?= number_format($subtotal, 0) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div><!-- .order-card -->
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function toggleOrder(id) {
  document.getElementById('order-' + id).classList.toggle('open');
}
</script>
</body>
</html>