<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {


    $go_api_url = "http://api:8080/login"; 
    
    // PHP 陣列轉為 JSON 字串
    $data = json_encode([
        "username" => $_POST['username'] ?? '',
        "password" => $_POST['password'] ?? ''
    ]);

    // 初始化 cURL 工具，準備建立通訊連線
    $ch = curl_init($go_api_url);

    // 重要設定：告訴 cURL 我們要用 POST 方法發送資料
    curl_setopt($ch, CURLOPT_POST, true);

    // 將 JSON 字串塞入請求主體 (Body)
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // 設定 Header，告訴Go的資料是 JSON 
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // 要求 cURL 將結果回傳到變數
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Go 的 c.JSON 回傳的內容存進 $response
    $response = curl_exec($ch);

    curl_close($ch);
    
    echo "<script>alert('" . addslashes($response) . "');</script>";
}


?>


<!DOCTYPE html>
    <html>
    <head>
        <title>登入</title>
    </head>
    <style>
        /* 全局樣式 */
        body {
            font-family: Arial, sans-serif;
            background-color: #1c1d26; /* 深灰背景色 */
            color: #ffffff; /* 白色文字 */
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 標題 */
        h1, h2, h3 {
            color: #e44c65; /* 紅色點綴 */
        }

        /* 表單樣式 */
        form {
            background-color: #272833; /* 深灰背景 */
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            width: 300px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        form label {
            font-size: 1rem;
            margin-bottom: 5px;
        }

        form input[type="text"], 
        form input[type="password"] {
            padding: 10px;
            border: none;
            border-radius: 5px;
            width: calc(100% - 20px);
        }

        form input[type="submit"] {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background-color: #e44c65;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        form input[type="submit"]:hover {
            background-color: #c4374f;
        }

        /* 錯誤提示文字 */
        form p {
            color: #e44c65;
            font-size: 0.9rem;
            margin: 0;
        }

        nav {
            margin-top: 20px;
            background-color: #272833;
            padding: 10px 0;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        nav ul li {
            display: inline-block;
        }

        nav ul li a {
            color: #ffffff;
            text-decoration: none;
            font-size: 1rem;
            padding: 5px 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            transition: background-color 0.3s, border-color 0.3s;
        }

        nav ul li a:hover {
            background-color: #e44c65;
            border-color: #e44c65;
        }

       
        @media (max-width: 480px) {
            form {
                width: 90%;
            }

            nav ul li a {
                font-size: 0.8rem;
            }
        }

    </style>
    <body>
        <form method="POST" action="">
            <label for="username">用戶名:</label>
            <input type="text" id="username" name="username" required> <!-- 用戶名輸入框 -->
            <br>
            <label for="password">密碼:</label>
            <input type="password" id="password" name="password" required> <!-- 密碼輸入框 -->
            <br>
            <input type="submit" value="登入"> <!-- 登入按鈕 -->
        </form>
        <?php if (isset($error)) echo "<p>$error</p>"; ?> <!-- 顯示錯誤信息（如果有） -->
        <nav id="nav">
                <ul>
                    <li><a href="reg.php">註冊</a></li>
                </ul>
        </nav>
    </body>
    </html>