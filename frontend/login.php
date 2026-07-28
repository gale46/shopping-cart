<?php
// 改用單機 redis-session，不走 Cluster
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');

session_start();
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
    //將response存入cache讓下個頁面可以讀到
    $res = json_decode($response, true);
    if($res['message'] === "登入成功"){
        
        $_SESSION['user_id'] = $res['id'];
        $_SESSION['username'] = $res['username'];
        $_SESSION['role'] = $res['role'];
        header("Location:index.php");
    }
    echo "<div style='font-family: Arial, sans-serif; font-size: 20px; font-weight: bold;'>
        $response
      </div>";
}


?>


<!DOCTYPE html>
    <html>
    <head>  
        <title>登入</title>
    </head>
    <style>
        /* 直接引用你提供的 Style 變數與基礎設定 */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #F5F3EE;
            --surface: #FFFFFF;
            --border: #E0DDD6;
            --text: #1C1A17;
            --muted: #78746A;
            --accent: #1C1A17;
            --accent-fg: #F5F3EE;
            --danger: #B83232;
            --success: #1A7A4A;
            --r: 10px;
            --font: 'DM Sans', sans-serif;
            --font-d: 'Noto Serif TC', serif;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        /* 登入容器 */
        .login-wrap {
            width: 100%;
            max-width: 400px;
            animation: fadeUp 0.22s ease both;
        }

        .page-head {
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .page-head h1 { 
            font-family: var(--font-d); 
            font-size: 1.8rem; 
            font-weight: 600; 
            letter-spacing: -0.02em; 
        }

        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 2rem;
            overflow: hidden;
        }

        /* 表單元素美化 */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: var(--font);
            font-size: 0.95rem;
            color: var(--text);
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* 按鈕美化 */
        input[type="submit"] {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: var(--accent-fg);
            border: none;
            border-radius: var(--r);
            font-size: 0.95rem;
            font-weight: 500;
            font-family: var(--font);
            cursor: pointer;
            transition: opacity 0.15s;
            margin-top: 0.5rem;
        }

        input[type="submit"]:hover {
            opacity: 0.85;
        }

        /* 錯誤訊息風格 (引用 .b-out 色彩) */
        .error-msg {
            background: #FDE8E6;
            color: var(--danger);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.83rem;
            margin-bottom: 1.25rem;
            text-align: center;
            border: 1px solid rgba(184, 50, 50, 0.1);
        }

        /* 底部導航 */
        .login-nav {
            margin-top: 1.5rem;
            text-align: center;
        }

        .login-nav ul {
            list-style: none;
        }

        .login-nav a {
            font-size: 0.83rem;
            color: var(--muted);
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color 0.2s;
        }

        .login-nav a:hover {
            color: var(--text);
            text-decoration: underline;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(7px); }
            to   { opacity: 1; transform: translateY(0); }
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