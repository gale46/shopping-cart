<?php
/**
 * checkout.php
 * 接收來自 cart.php 的 POST
 * → 從 DB 重新讀取購物車（以 Session user_id 為準，防止竄改）
 * → 呼叫 Go API POST /api/checkout
 * → 成功：清空 cart_item + redirect order.php?id=N
 * → 失敗：redirect 回 cart.php 並帶錯誤訊息
 */

// ── 資料庫設定 ────────────────────────────────────────────────────
define('DB_HOST', 'mysql-db');
define('DB_NAME', 'ShoppingCart');
define('DB_USER', 'root');
define('DB_PASS', '9151999');
define('DB_PORT', '3306');

// ── Go API 位址 ───────────────────────────────────────────────────
define('GO_API_URL', 'http://api:8080/checkout');

session_start();

// ── 只接受 POST，且必須已登入 ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cart.php');
    exit;
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── 錯誤：存進 Session 再跳回 cart.php ───────────────────────────
// function redirectError(string $msg): never {
//     $_SESSION['cart_error'] = $msg;
//     header('Location: /cart.php');
//     exit;
// }
function redirectError(string $msg): never {
    // 暫時註解跳轉，改用 die 看錯誤
    die("除錯訊息： " . $msg); 
    
    // $_SESSION['cart_error'] = $msg;
    // header('Location: /cart.php');
    // exit;
}

// ── PDO 連線 ──────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    redirectError('資料庫連線失敗');
}

// ── 從 DB 重新讀取購物車（不信任前端傳來的資料）─────────────────
$stmt = $pdo->prepare("
    SELECT
        ci.product_id,
        ci.quantity,
        p.stock,
        p.name
    FROM cart_item ci
    INNER JOIN product p ON p.id = ci.product_id
    WHERE ci.user_id = ?
      AND p.deleted_at IS NULL
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    redirectError('購物車是空的');
}

// ── 提早檢查庫存（Go API 也會鎖定檢查，這裡先給友善錯誤訊息）────
foreach ($cart_items as $item) {
    if ((int)$item['stock'] < (int)$item['quantity']) {
        redirectError($item['name'] . ' 庫存不足，請調整數量');
    }
}

// ── 組 Go API payload ────────────────────────────────────────────
$payload = json_encode([
    'user_id' => $user_id,
    'items'   => array_map(fn($i) => [
        'product_id' => (int)$i['product_id'],
        'quantity'   => (int)$i['quantity'],
    ], $cart_items),
]);

// ── 用 cURL 呼叫 Go API ───────────────────────────────────────────
$ch = curl_init(GO_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    redirectError('無法連線到結帳服務，請稍後再試');
}

$data = json_decode($response, true);

if ($http_code !== 200) {
    redirectError($data['error'] ?? '結帳失敗，請稍後再試');
}

// ── 結帳成功：清空購物車 ─────────────────────────────────────────
$pdo->prepare("DELETE FROM cart_item WHERE user_id = ?")
    ->execute([$user_id]);

// ── redirect 到訂單詳情頁 ────────────────────────────────────────
$order_id = (int)($data['order_id'] ?? 0);
header('Location: /order.php' . ($order_id ? '?id=' . $order_id : ''));
exit;