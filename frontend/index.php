<?php
$json = file_get_contents("http://api:8080/products");
$products = json_decode($json, true);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        .product-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .product-item { border: 1px solid #ddd; padding: 15px; border-radius: 8px; width: 220px; }
        .product-item img { width: 100%; height: auto; border-radius: 4px; }
    </style>
</head>
<body>


<div class="product-container">
    <?php if ($products): ?>
        <?php foreach ($products as $item): ?>
            <div class="product-item">
                <h3><?= htmlspecialchars($item['name']) ?></h3>
                <p>價格：$<?= htmlspecialchars($item['price']) ?></p>

                <div class="image-box">
                    <?php if (!empty($item['image_url'])): ?>
                        <a href="product_detail.php?product_id=<?php echo $item['product_id']; ?>">
                            <img src="<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>" style="width:200px;">
                        </a>
                    <?php else: ?>
                        <p class="no-image">目前沒有圖片</p>
                    <?php endif; ?>
                </div>
                <!-- todo -->
                 <!-- 使用form會造成重複提交，改用JS onclick -->
                <div class="order-action">
                    <label>數量：</label>
                    
                        <input 
                            type="number" 
                            id="qty_<?= $item['product_id'] ?>" 
                            value="1" 
                            min="0"
                            oninput="if(this.value<0)this.value=0"
                            >
                        <button onclick="addToCart(<?= $item['product_id']?>)">加入購物車</button>
                    
                </div>
            </div>
        <?php endforeach; ?>
        <a href="cart.php" >購物車</a>
    <?php else: ?>
        <div class="empty-msg">目前沒有任何商品。</div>
    <?php endif; ?>
</div>
<script>
    async function addToCart(productId) {
    const qtyInput = document.getElementById('qty_' + productId);
    
    // 確保抓得到元素
    if (!qtyInput) {
        console.error("找不到輸入框 qty_" + productId);
        return;
    }

    const Quantity = parseInt(qtyInput.value);

    try {
        const response = await fetch('cart.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
            body: new URLSearchParams({
                'product_id': productId,
                'quantity': Quantity
            })
        });

        if (response.ok) {
            // 成功後轉跳到購物車頁面看結果
            window.location.href = "cart.php";
        } else {
            alert("加入失敗，伺服器錯誤");
        }
    } catch (error) {
        console.error("Fetch 錯誤:", error);
    }
}
</script>
</body>
</html>