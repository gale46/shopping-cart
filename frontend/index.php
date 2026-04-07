<?php
$json = file_get_contents("http://api:8080/products");
$products = json_decode($json, true);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        :root {
  --bg: #F5F3EE;
  --surface: #FFFFFF;
  --border: #E0DDD6;
  --text: #1C1A17;
  --muted: #78746A;
  --accent: #1C1A17;
  --accent-fg: #F5F3EE;
  --r: 10px;
  --font: 'DM Sans', sans-serif;
  --font-d: 'Noto Serif TC', serif;
}

/* 商品容器：使用 Grid 佈局 */
.product-container {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 24px;
  padding: 20px;
}

/* 單個商品卡片 */
.product-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  padding: 16px;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-item:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.05);
}

/* 標題與價格 */
.product-item h3 {
  font-family: var(--font-d);
  font-size: 1.15rem;
  margin-bottom: 8px;
  color: var(--text);
}

.product-item p {
  font-size: 0.9rem;
  color: var(--muted);
  margin-bottom: 12px;
}

/* 圖片盒子固定比例 */
.image-box {
  width: 100%;
  aspect-ratio: 1 / 1; /* 正方形 */
  background: #fcfcfc;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #f0f0f0;
}

.image-box img {
  width: 100%;
  height: 100%;
  object-fit: cover; /* 確保圖片不變形且填滿 */
  transition: opacity 0.2s;
}

.image-box img:hover {
  opacity: 0.9;
}

/* 加入購物車區域 */
.order-action {
  margin-top: auto; /* 推到底部 */
  display: flex;
  align-items: center;
  gap: 8px;
  border-top: 1px solid var(--border);
  padding-top: 16px;
}

.order-action label {
  font-size: 0.75rem;
  color: var(--muted);
}

.order-action input[type="number"] {
  width: 50px;
  padding: 6px;
  border: 1px solid var(--border);
  border-radius: 4px;
  font-family: var(--font);
  text-align: center;
}

.order-action button {
  flex: 1;
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;
}

.order-action button:hover {
  opacity: 0.85;
}

/* 底部連結按鈕 */
.nav-links {
  grid-column: 1 / -1;
  text-align: center;
  margin-top: 30px;
  display: flex;
  justify-content: center;
  gap: 20px;
}

.nav-links a {
  text-decoration: none;
  color: var(--muted);
  font-size: 0.9rem;
  border-bottom: 1px solid transparent;
  transition: all 0.2s;
}

.nav-links a:hover {
  color: var(--text);
  border-bottom: 1px solid var(--text);
}

.no-image {
  font-size: 0.8rem;
  color: #ccc;
}
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
        <a href="order.php" >我的訂單</a>
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