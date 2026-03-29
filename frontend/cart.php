


<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "DEBUG: 開始執行<br>";
    var_dump($_POST); // 看看表單傳了什麼
    $addToCart = "http://api:8080/cart"; 
    //需定義type以免傳到go後錯誤
    $data = json_encode([
        "id" => (int)$_POST['id'] ?? 0,
        "quantity" => (int)$_POST['quantity'] ?? 0

    ]);
    $ch = curl_init($addToCart);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo "這是 Go 傳回來的原始文字：[" . $response . "]";
    // 1. 先把文字變成 PHP 陣列
   $res = json_decode($response, true);
    // |-----------------------------------------|
    // |-----------------------------------------|
    // 加入由cart_item提出的quantity
    // 只要 $res 不是 null 且有 name 這個 key，就代表成功了
    if (is_array($res) && !empty($res)) {
        // 使用 foreach 遍歷這個商品陣列
        foreach ($res as $item) {
            echo "<h3>加入購物車成功！</h3>";
            echo '<img src="' . $item['image_url'] . '"/>';
            echo "商品名稱：" . $item['name'] . "<br>";
            echo "價格：" . $item['price'] . "<br>";
            echo "數量：" . $item['quantity'] . "<br>";

        }
    } else {
        echo "解析失敗，回傳內容為：";
        var_dump($res);
    }
    

?>