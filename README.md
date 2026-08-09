
# 購物車系統

模擬真實電商的購物流程，包含買家購物、賣家管理後台，並使用 Redis Cluster 減少資料庫負荷。

---

## 技術架構

| 層級 | 技術 |
|---|---|
| 前端 | PHP 8.2 |
| 後端 API | Go 1.23 + Gin |
| 資料庫 | MySQL 8.0 |
| 快取 | Redis Cluster（6 節點：3 Master + 3 Slave）|
| Session | Redis 單機 |
| 容器化 | Docker + Docker Compose |

---

## 流程圖
<img width="945" height="1334" alt="購物車" src="https://github.com/user-attachments/assets/e1081a6b-d20b-4513-ad55-465f0342fe3c" />


---




## 功能列表

**買家**
- 瀏覽商品列表
- 查看商品詳情
- 加入購物車 / 調整數量
- 填寫收件資訊結帳
- 查看訂單記錄

**賣家**
- 申請成為賣家
- 新增 / 編輯 / 下架商品
- 查看待出貨訂單
- 確認出貨

---

## 系統架構

```
瀏覽器
  ↓
PHP Frontend（port 3000）
  ↓ cURL
Go API（port 8080）
  ├── Redis Cluster（購物車 / 商品 cache）
  └── MySQL（資料持久化）
```

**Cache 策略**
```
讀：先查 Redis → miss 才查 MySQL → 結果寫回 Redis
寫：先寫 MySQL → 成功後刪 Redis cache
```

---

## API 文件

### 使用者

| 方法 | 路由 | 說明 |
|---|---|---|
| POST | `/login` | 登入 |
| POST | `/register` | 註冊 |

### 商品

| 方法 | 路由 | 說明 |
|---|---|---|
| GET | `/products` | 取得商品列表 |
| POST | `/product_detail` | 取得商品詳情 |

### 購物車

| 方法 | 路由 | 說明 |
|---|---|---|
| POST | `/cart` | 查看 / 新增購物車商品 |
| POST | `/cart_update` | 更新購物車數量 |
| GET | `/api/cart/count` | 取得購物車數量 |

### 結帳

| 方法 | 路由 | 說明 |
|---|---|---|
| POST | `/checkout` | 結帳（建立訂單 + 扣庫存）|

### 賣家

| 方法 | 路由 | 說明 |
|---|---|---|
| POST | `/seller/register` | 申請成為賣家 |
| GET | `/seller/products` | 取得賣家商品列表 |
| POST | `/seller/product/add` | 新增商品 |
| POST | `/seller/product/edit` | 編輯商品 |
| POST | `/seller/product/delete` | 下架商品 |
| GET | `/seller/orders` | 取得待出貨訂單 |
| POST | `/seller/order/ship` | 確認出貨 |

---

## 啟動方式

### 環境需求
- Docker
- Docker Compose

### 步驟

```bash
# 1. Clone 專案
git clone https://github.com/gale46/shopping-cart
cd shopping-cart

# 2. 設定環境變數
cp .env.example .env
# 編輯 .env 填入你的設定

# 3. 啟動所有服務
docker compose up --build

# 4. 確認 Redis Cluster 初始化成功
docker logs redis-init
# 看到 [OK] All 16384 slots covered. 代表成功

# 5. 開啟瀏覽器
# http://localhost:3000
```

### 環境變數說明（.env）

```env
DB_USER=root
DB_PASS=           # MySQL 密碼
DB_HOST=mysql-db
DB_PORT=3306
DB_NAME=ShoppingCart
REDIS_ADDR=redis-1:6379
```

---

## 單元測試

```bash
cd api
go test -v ./...
```

測試項目：
- `TestCalculateTotal` — 訂單金額計算
- `TestCheckStock` — 庫存檢查
- `TestValidateLogin` — 登入驗證

---

## Demo
https://drive.google.com/file/d/1pSUSjXFnbw9ng5vZ1JePymRPDcPvElh7/view?usp=drive_link
