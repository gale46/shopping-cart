


<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "DEBUG: 開始執行<br>";
    var_dump($_POST); // 看看表單傳了什麼
    $addToCart = "http://api:8080/product_detail"; 
    //需定義type以免傳到go後錯誤
    $data = json_encode([
        "id" => (int)$_GET['id'] ?? 0
        // 加上user_id + quantity
        // |-----------------------------------------|
        // |-----------------------------------------|
        // go 加入cart_item sql
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
    // 修正庫存描述至不同頁面
    if (is_array($res) && !empty($res)) {
        // 使用 foreach 遍歷這個商品陣列
        foreach ($res as $item) {
        echo '<img src="' . $res['image_url'] . '"/>';
        echo "商品名稱：" . $res['name'] . "<br>";
        echo "價格：" . $res['price'] . "<br>";
        echo "庫存：" . $res['stock'] . "<br>";
        echo "描述：" . $res['description'] . "<br>";
        echo "賣家資訊：" . $res['seller_name'] . "<br>". $res['seller_email'] . "<br>";
        }
    } else {
        echo "解析失敗，回傳內容為：";
        var_dump($res);
    }
    

?>

