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
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="商品圖片">
                    <?php else: ?>
                        <p class="no-image">目前沒有圖片</p>
                    <?php endif; ?>
                </div>

                <div class="order-action">
                    <label>數量：</label>
                    <form action="cart.php" method="POST">
                        <input type="number" name="quantity" value="1">
                        <input type="hidden" name="id" value="<?= ($item['id']) ?>">
                        <button type="submit">加入購物車</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php else: ?>
        <div class="empty-msg">目前沒有任何商品。</div>
    <?php endif; ?>
</div>

</body>
</html>