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
if (!isset($_SESSION['user_id'])) {
    //報錯或跳轉
    die(json_encode(["error" => "未登入"]));
}

$user_id = (int)$_SESSION['user_id'];

$data = json_encode([
    "user_id"    => $user_id,
    "product_id" => isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0,
    "quantity" => isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0
]);

$addToCartUrl = "http://api:8080/cart"; 
$ch = curl_init($addToCartUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// 將 Go 傳回來的 JSON 轉成 PHP 陣列
$res = json_decode($response, true);
// echo "<pre>"; 
// var_dump($res); // 查看參數
// echo "</pre>"; 
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>加入購物車結果</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success-title { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
        .error-title { color: #dc3545; }
        .product-card { display: flex; align-items: center; gap: 20px; margin-top: 20px; }
        .product-card img { width: 120px; height: auto; border-radius: 5px; border: 1px solid #ddd; }
        .product-info b { color: #555; }
        .btn-back { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <?php if (is_array($res) && !empty($res)): ?>
        <h2 class="success-title">✅ 加入購物車成功！</h2>
        
        <?php 

        foreach ($res as $item): 
        ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if (!empty($item['image_url'])): ?>
                        <a herf = 'product_detail.php'>
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="商品圖片">
                        </a>
                    <?php else: ?>
                        <div style="width:120px;height:120px;background:#eee;display:flex;align-items:center;justify-content:center;">無圖片</div>
                    <?php endif; ?>
                </div>
                
                <div class="product-info">
                    <p><b>商品名稱：</b><?= htmlspecialchars($item['name']) ?></p>
                    <p>
                        單價：$<span id="price_<?= $item['product_id'] ?>" data-raw-price="<?= $item['price'] ?>">
                            <?= number_format($item['price']) ?>
                        </span>
                    </p>
                    <p>
                        <b>購買數量：</b>
                        <!--使用qty_+id作為input的id -->
                        <input type="number" 
                        
                            id="qty_<?= $item['product_id'] ?>" 
                            value="<?= (int)$item['quantity'] ?>" 
                            min="0"
                            oninput="if(this.value<0)this.value=0"
                            onchange="updateCart(<?= $user_id ?>, <?= $item['product_id'] ?>)">
                            <!-- onchange->改變事件; onclick->點擊事件 -->
                            <!-- 利用JS fetch sql data -->
                    </p>
                    <p>
                        <h5 id="subtotal_<?= $item['product_id'] ?>">
                            小計：$<?= number_format($item['price'] * $item['quantity']) ?>
                        </h5>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>    
        
    <?php else: ?>
        <h2 class="error-title">解析失敗或購物車為空</h2>
        <p>後端回傳原始內容：</p>
        <pre style="background: #eee; padding: 10px;"><?php var_dump($res); ?></pre>
    <?php endif; ?>
    <h5 updateSubtotal(productId, price)></h5>
    <hr>
    <a href="index.php" class="btn-back">返回商品列表</a>
    <!-- todo -->
    <form method="POST" action="/checkout.php">
      <button
        type="submit"
        class="btn-checkout"
        <?= empty($item) ? 'disabled' : '' ?>
        >

        前往結帳
      </button>
    </form>
</div>
<script>
    async function updateCart(userId, productId) {
        // 透過 ID 抓取「目前」輸入框裡的最新數值
        const qtyInput = document.getElementById('qty_' + productId);
        const newQuantity = parseInt(qtyInput.value);

        // 測試列印
        console.log("商品 ID:", productId, "新數量:", newQuantity);
        updatePrice(productId);
        // await 採用async在fetch的同時，可以處理別的畫面
        try{
            const response = await fetch('http://localhost:8080/cart_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    product_id: productId,
                    quantity: newQuantity
                })
            });

        }catch(error){
            console.error("更新失敗:", error);
        }

    }; 
    function updatePrice(productId) {
        // 1. 拿取純數字 (不帶 $ 和 逗號)
        const price = parseFloat(document.getElementById('price_' + productId).dataset.rawPrice);

        // 2. 拿取數量
        const qty = parseInt(document.getElementById('qty_' + productId).value) || 0;

        // 3. 計算
        const total = price * qty;

        // 4. 更新顯示 (這時候再把它格式化回人看的樣子)
        document.getElementById('subtotal_' + productId).innerText = "小計：$" + total.toLocaleString();
    }
</script>
</body>
</html>