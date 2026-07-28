<?php
// ── Session（必須在最頂端，前面不能有任何輸出）───────────────────
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// ── 設定 ─────────────────────────────────────────────────────────
define('DB_HOST',    'mysql-db');
define('DB_NAME',    'ShoppingCart');
define('DB_USER',    'root');
define('DB_PASS',    '9151999');
define('DB_PORT',    '3306');
define('GO_API_URL', 'http://api:8080/checkout');  // ← 補上這個
define('IMAGE_BASE_URL', 'http://localhost:3000/uploads/product/');

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

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

// ── 讀購物車 ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT ci.quantity,
           p.id AS product_id, p.name AS product_name,
           p.price, p.stock, p.image_url
    FROM cart_item ci
    INNER JOIN product p ON p.id = ci.product_id
    WHERE ci.user_id = ? AND p.deleted_at IS NULL
    ORDER BY ci.id ASC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    $_SESSION['checkout_error'] = '購物車是空的';
    header('Location: /cart.php');
    exit;
}

$grand_total = 0;
foreach ($cart_items as $item) {
    $grand_total += $item['price'] * $item['quantity'];
}

// ── POST：送出訂單 ────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $zip     = trim($_POST['zip']     ?? '');
    $city    = trim($_POST['city']    ?? '');
    $address = trim($_POST['address'] ?? '');
    $note    = trim($_POST['note']    ?? '');

    if (!$name || !$phone || !$city || !$address) {
        $error = '請填寫收件人姓名、電話、縣市及地址';
    } else {
        // 再讀一次購物車（以 DB 為準）
        $stmt->execute([$user_id]);
        $fresh_items = $stmt->fetchAll();

        if (empty($fresh_items)) {
            $_SESSION['checkout_error'] = '購物車是空的';
            header('Location: /cart.php');
            exit;
        }

        // 組 Go API payload
        $payload = json_encode([
            'user_id' => $user_id,
            'items'   => array_map(fn($i) => [
                'product_id' => (int)$i['product_id'],
                'quantity'   => (int)$i['quantity'],
            ], $fresh_items),
            'shipping' => compact('name', 'phone', 'zip', 'city', 'address', 'note'),
        ]);

        // cURL → Go API
        $ch = curl_init(GO_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $error = '無法連線到結帳服務，請稍後再試';
        } elseif ($http_code !== 200) {
            $data  = json_decode($response, true);
            $error = $data['error'] ?? '結帳失敗，請稍後再試';
        } else {
            // ── 結帳成功：清空購物車 ──────────────────────────────
            $pdo->prepare("DELETE FROM cart_item WHERE user_id = ?")
                ->execute([$user_id]);

            $data     = json_decode($response, true);
            $order_id = (int)($data['order_id'] ?? 0);

            // ── 把收件資訊存進 Session，讓完成頁顯示 ─────────────
            $_SESSION['last_order'] = [
                'order_id' => $order_id,
                'name'     => $name,
                'phone'    => $phone,
                'zip'      => $zip,
                'city'     => $city,
                'address'  => $address,
                'note'     => $note,
                'total'    => $grand_total,
                'items'    => array_map(fn($i) => [
                    'name'      => $i['product_name'],
                    'price'     => $i['price'],
                    'quantity'  => $i['quantity'],
                    'image_url' => $i['image_url'],
                ], $fresh_items),
            ];

            header('Location: /order_complete.php');
            exit;
        }
    }
}

$form = [
    'name'    => $_POST['name']    ?? '',
    'phone'   => $_POST['phone']   ?? '',
    'zip'     => $_POST['zip']     ?? '',
    'city'    => $_POST['city']    ?? '',
    'address' => $_POST['address'] ?? '',
    'note'    => $_POST['note']    ?? '',
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>結帳</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #F5F3EE; --surface: #fff; --border: #E0DDD6;
  --text: #1C1A17; --muted: #78746A;
  --accent: #1C1A17; --accent-fg: #F5F3EE;
  --danger: #B83232; --success: #1A7A4A;
  --r: 10px; --font: 'DM Sans',sans-serif; --font-d: 'Noto Serif TC',serif;
}
body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; padding: 2.5rem 1rem 5rem; }
.back { max-width: 960px; margin: 0 auto .75rem; font-size: .83rem; }
.back a { color: var(--muted); text-underline-offset: 3px; }
.page-head { max-width: 960px; margin: 0 auto 1.75rem; }
.page-head h1 { font-family: var(--font-d); font-size: 1.8rem; font-weight: 600; letter-spacing: -.02em; }
.page-head p  { font-size: .83rem; color: var(--muted); margin-top: 4px; }
.steps { display: flex; align-items: center; max-width: 960px; margin: 0 auto 2rem; }
.step { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--muted); }
.step.active { color: var(--text); font-weight: 500; }
.step-num { width: 24px; height: 24px; border-radius: 50%; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 600; }
.step.active .step-num { background: var(--text); border-color: var(--text); color: var(--accent-fg); }
.step-line { flex: 1; height: 1px; background: var(--border); margin: 0 12px; min-width: 32px; }
.layout { max-width: 960px; margin: 0 auto; display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
@media(max-width:720px){ .layout{ grid-template-columns:1fr; } }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); overflow: hidden; }
.card-hd { padding: 13px 20px; border-bottom: 1px solid var(--border); font-size: .75rem; font-weight: 500; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; }
.form-body { padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: .8rem; font-weight: 500; color: var(--muted); }
.field label .req { color: var(--danger); margin-left: 2px; }
.field input, .field select, .field textarea {
  width: 100%; border: 1px solid var(--border); border-radius: 7px;
  background: var(--surface); color: var(--text);
  font-size: .92rem; font-family: var(--font); padding: 0 12px;
  height: 40px; outline: none; transition: border-color .15s;
}
.field textarea { height: 80px; padding: 10px 12px; resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--text); }
.field-row { display: grid; grid-template-columns: 100px 1fr; gap: 10px; }
.error-bar { background: #FDE8E6; color: var(--danger); font-size: .85rem; padding: 11px 16px; border-radius: var(--r); margin: 0 20px 4px; }
.item-row { display: flex; align-items: center; gap: 12px; padding: 13px 20px; border-bottom: 1px solid var(--border); }
.item-row:last-child { border-bottom: none; }
.item-thumb { width: 44px; height: 44px; border-radius: 7px; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; overflow: hidden; }
.item-thumb img { width: 100%; height: 100%; object-fit: cover; }
.item-info { flex: 1; min-width: 0; }
.item-name { font-size: .87rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-sub  { font-size: .76rem; color: var(--muted); margin-top: 2px; }
.item-price { font-size: .87rem; font-weight: 500; white-space: nowrap; }
.summary-body { padding: 14px 20px; }
.s-row { display: flex; justify-content: space-between; font-size: .86rem; color: var(--muted); padding: 5px 0; }
.s-row.total { border-top: 1px solid var(--border); margin-top: 10px; padding-top: 12px; font-size: 1.05rem; font-weight: 600; color: var(--text); font-family: var(--font-d); }
.right-col { display: flex; flex-direction: column; gap: 14px; }
.btn-submit { width: 100%; padding: 14px; background: var(--accent); color: var(--accent-fg); border: none; border-radius: var(--r); font-size: .95rem; font-weight: 500; font-family: var(--font); cursor: pointer; transition: opacity .15s; }
.btn-submit:hover { opacity: .82; }
.spin { display: inline-block; width: 13px; height: 13px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: rot .7s linear infinite; vertical-align: middle; margin-right: 6px; }
@keyframes rot { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div class="back"><a href="/cart.php">← 返回購物車</a></div>
<div class="page-head">
  <h1>結帳</h1>
  <p>填寫收件資訊後送出訂單</p>
</div>

<div class="steps">
  <div class="step"><span class="step-num">✓</span> 購物車</div>
  <div class="step-line"></div>
  <div class="step active"><span class="step-num">2</span> 填寫資料</div>
  <div class="step-line"></div>
  <div class="step"><span class="step-num">3</span> 完成訂單</div>
</div>

<div class="layout">
  <div class="card">
    <div class="card-hd">收件資訊</div>
    <?php if ($error): ?>
      <div class="error-bar"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/checkout.php" id="checkout-form">
      <div class="form-body">
        <div class="field">
          <label>收件人姓名 <span class="req">*</span></label>
          <input type="text" name="name" value="<?= htmlspecialchars($form['name']) ?>" placeholder="王小明" required>
        </div>
        <div class="field">
          <label>聯絡電話 <span class="req">*</span></label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($form['phone']) ?>" placeholder="0912-345-678" required>
        </div>
        <div class="field">
          <label>縣市 / 地址 <span class="req">*</span></label>
          <div class="field-row">
            <select name="city" required>
              <option value="">縣市</option>
              <?php
              $cities = ['台北市','新北市','桃園市','台中市','台南市','高雄市',
                         '基隆市','新竹市','新竹縣','苗栗縣','彰化縣','南投縣',
                         '雲林縣','嘉義市','嘉義縣','屏東縣','宜蘭縣','花蓮縣',
                         '台東縣','澎湖縣','金門縣','連江縣'];
              foreach ($cities as $city):
                $sel = ($form['city'] === $city) ? 'selected' : '';
              ?>
                <option value="<?= $city ?>" <?= $sel ?>><?= $city ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="address" value="<?= htmlspecialchars($form['address']) ?>" placeholder="中正路 100 號 3 樓" required>
          </div>
        </div>
        <div class="field">
          <label>郵遞區號</label>
          <input type="text" name="zip" value="<?= htmlspecialchars($form['zip']) ?>" placeholder="100" maxlength="6" style="max-width:120px;">
        </div>
        <div class="field">
          <label>訂單備註</label>
          <textarea name="note" placeholder="例如：請勿按門鈴，放門口即可"><?= htmlspecialchars($form['note']) ?></textarea>
        </div>
      </div>
    </form>
  </div>

  <div class="right-col">
    <div class="card">
      <div class="card-hd">訂單明細</div>
      <?php foreach ($cart_items as $item):
        $thumb = $item['image_url']
          ? '<img src="' . htmlspecialchars(IMAGE_BASE_URL . $item['image_url']) . '" alt="">'
          : '🛍';
      ?>
        <div class="item-row">
          <div class="item-thumb"><?= $thumb ?></div>
          <div class="item-info">
            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
            <div class="item-sub">NT$<?= number_format($item['price']) ?> × <?= $item['quantity'] ?></div>
          </div>
          <div class="item-price">NT$<?= number_format($item['price'] * $item['quantity']) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="summary-body">
        <div class="s-row"><span>件數</span><span><?= array_sum(array_column($cart_items, 'quantity')) ?> 件</span></div>
        <div class="s-row total"><span>總計</span><span>NT$<?= number_format($grand_total) ?></span></div>
      </div>
    </div>
    <button type="submit" form="checkout-form" class="btn-submit" id="submit-btn">確認送出訂單</button>
  </div>
</div>

<script>
document.getElementById('checkout-form').addEventListener('submit', function() {
  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spin"></span>處理中…';
});
</script>
</body>
</html>