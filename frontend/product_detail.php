<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 取得 product_id
$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id === 0) {
    die('缺少 product_id');
}

// 打 Go API
$ch = curl_init('http://api:8080/product_detail');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['product_id' => $product_id]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$res = json_decode($response, true);

if ($http_code !== 200 || isset($res['error'])) {
    die('商品讀取失敗：' . ($res['error'] ?? '未知錯誤'));
}
?>
<img src="<?= htmlspecialchars($res['image_url']) ?>"><br>
<p>商品名稱：<?= htmlspecialchars($res['name']) ?></p>
<p>價格：<?= $res['price'] ?></p>
<p>庫存：<?= $res['stock'] ?></p>
<p>描述：<?= htmlspecialchars($res['description']) ?></p>
<p>賣家：<?= htmlspecialchars($res['seller_name']) ?> / <?= htmlspecialchars($res['seller_email']) ?></p>