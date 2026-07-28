<?php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-session:6379');
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// 已經是 seller 直接跳到 dashboard
if (($_SESSION['role'] ?? 0) == 1) {
    header('Location: /seller_dashboard.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name) {
        $error = '請填寫店鋪名稱';
    } else {
        $payload = json_encode([
            'user_id' => $user_id,
            'name'    => $name,
            'email'   => $email,
        ]);

        $ch = curl_init('http://api:8080/seller/register');
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

        $data = json_decode($response, true);

        if ($http_code === 200) {
            // 更新 Session
            $_SESSION['role']      = 1;
            $_SESSION['seller_id'] = $data['seller_id'];
            header('Location: /seller_dashboard.php');
            exit;
        } else {
            $error = $data['error'] ?? '申請失敗，請稍後再試';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>成為賣家</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:#F5F3EE; --surface:#fff; --border:#E0DDD6;
  --text:#1C1A17; --muted:#78746A;
  --accent:#1C1A17; --accent-fg:#F5F3EE;
  --danger:#B83232; --success:#1A7A4A;
  --r:10px; --font:'DM Sans',sans-serif; --font-d:'Noto Serif TC',serif;
}
body { font-family:var(--font); background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); width:100%; max-width:460px; overflow:hidden; }
.card-head { padding:28px 28px 0; }
.card-head h1 { font-family:var(--font-d); font-size:1.55rem; font-weight:600; }
.card-head p  { font-size:.83rem; color:var(--muted); margin-top:6px; }
.card-body { padding:24px 28px 28px; display:flex; flex-direction:column; gap:16px; }
.field label { font-size:.8rem; font-weight:500; color:var(--muted); display:block; margin-bottom:5px; }
.field label .req { color:var(--danger); margin-left:2px; }
.field input { width:100%; height:40px; border:1px solid var(--border); border-radius:7px; background:var(--surface); color:var(--text); font-size:.92rem; font-family:var(--font); padding:0 12px; outline:none; transition:border-color .15s; }
.field input:focus { border-color:var(--text); }
.hint { font-size:.78rem; color:var(--muted); margin-top:4px; }
.error-bar { background:#FDE8E6; color:var(--danger); font-size:.84rem; padding:10px 14px; border-radius:7px; }
.btn { width:100%; height:42px; background:var(--accent); color:var(--accent-fg); border:none; border-radius:7px; font-size:.95rem; font-weight:500; font-family:var(--font); cursor:pointer; transition:opacity .15s; }
.btn:hover { opacity:.82; }
.divider { border:none; border-top:1px solid var(--border); }
.back { font-size:.82rem; color:var(--muted); text-align:center; }
.back a { color:var(--text); font-weight:500; }
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <h1>成為賣家</h1>
    <p>填寫店鋪基本資料，審核後即可開始販售商品</p>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <div class="error-bar"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/become_seller.php">
      <div class="field" style="margin-bottom:14px">
        <label>店鋪名稱 <span class="req">*</span></label>
        <input type="text" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               placeholder="我的小舖" required>
        <p class="hint">店鋪名稱不可重複，建立後無法修改</p>
      </div>
      <div class="field" style="margin-bottom:20px">
        <label>聯絡 Email</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="seller@example.com">
      </div>
      <button type="submit" class="btn">申請成為賣家</button>
    </form>

    <hr class="divider">
    <p class="back"><a href="/index.php">← 返回首頁</a></p>
  </div>
</div>
</body>
</html>
