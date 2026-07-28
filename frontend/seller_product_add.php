<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 0) != 1) {
    header('Location: /login.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $description = trim($_POST['description'] ?? '');
    $image_url   = trim($_POST['image_url']   ?? '');

    if (!$name || $price <= 0) {
        $error = '請填寫商品名稱與價格';
    } else {
        $payload = json_encode([
            'seller_id'   => $seller_id,
            'name'        => $name,
            'price'       => $price,
            'stock'       => $stock,
            'description' => $description,
            'image_url'   => $image_url,
        ]);

        $ch = curl_init('http://api:8080/seller/product/add');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $_SESSION['seller_toast'] = '商品新增成功';
            header('Location: /seller_products.php');
            exit;
        } else {
            $data  = json_decode($response, true);
            $error = $data['error'] ?? '新增失敗';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>新增商品</title>
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
.wrap { max-width:620px; margin:0 auto; }
.back { font-size:.83rem; color:var(--muted); display:block; margin-bottom:1rem; }
.page-head { margin-bottom:1.75rem; }
.page-head h1 { font-family:var(--font-d); font-size:1.8rem; font-weight:600; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.card-hd { padding:13px 20px; border-bottom:1px solid var(--border); font-size:.75rem; font-weight:500; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; }
.form-body { padding:20px; display:flex; flex-direction:column; gap:16px; }
.field { display:flex; flex-direction:column; gap:6px; }
.field label { font-size:.8rem; font-weight:500; color:var(--muted); }
.field label .req { color:var(--danger); margin-left:2px; }
.field input, .field textarea {
  width:100%; border:1px solid var(--border); border-radius:7px;
  background:var(--surface); color:var(--text);
  font-size:.92rem; font-family:var(--font); padding:0 12px;
  height:40px; outline:none; transition:border-color .15s;
}
.field textarea { height:100px; padding:10px 12px; resize:vertical; }
.field input:focus, .field textarea:focus { border-color:var(--text); }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.hint { font-size:.76rem; color:var(--muted); margin-top:2px; }
.error-bar { background:#FDE8E6; color:var(--danger); font-size:.84rem; padding:10px 14px; border-radius:7px; }
.btn-submit { width:100%; padding:14px; background:var(--accent); color:var(--accent-fg); border:none; border-radius:var(--r); font-size:.95rem; font-weight:500; font-family:var(--font); cursor:pointer; transition:opacity .15s; margin-top:4px; }
.btn-submit:hover { opacity:.82; }
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="/seller_products.php">← 返回商品列表</a>
  <div class="page-head"><h1>新增商品</h1></div>

  <div class="card">
    <div class="card-hd">商品資訊</div>
    <?php if ($error): ?>
      <div style="padding:0 20px;margin-top:16px"><div class="error-bar"><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <form method="POST" action="/seller_product_add.php">
      <div class="form-body">
        <div class="field">
          <label>商品名稱 <span class="req">*</span></label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="例：藍牙耳機 Pro" required>
        </div>

        <div class="field-row">
          <div class="field">
            <label>售價 (NT$) <span class="req">*</span></label>
            <input type="number" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" min="1" placeholder="0" required>
          </div>
          <div class="field">
            <label>庫存數量</label>
            <input type="number" name="stock" value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>" min="0" placeholder="0">
          </div>
        </div>

        <div class="field">
          <label>商品描述</label>
          <textarea name="description" placeholder="商品詳細說明..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="field">
          <label>圖片檔名</label>
          <input type="text" name="image_url" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>" placeholder="product.jpg">
          <p class="hint">請先將圖片上傳至伺服器，再填入檔名</p>
        </div>

        <button type="submit" class="btn-submit">新增商品</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
