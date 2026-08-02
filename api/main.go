package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/gin-contrib/cors"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/redis/go-redis/v9"
)

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type productRequest struct {
	UserID   int `json:"user_id"`
	Id       int `json:"product_id"`
	Quantity int `json:"quantity"`
}

type CheckoutRequest struct {
	UserID int `json:"user_id"`
	Items  []struct {
		ProductID int `json:"product_id"`
		Quantity  int `json:"quantity"`
	} `json:"items"`
}

type BecomeSellerRequest struct {
	UserID int    `json:"user_id"`
	Name   string `json:"name"`
	Email  string `json:"email"`
}

type AddProductRequest struct {
	SellerID    int     `json:"seller_id"`
	Name        string  `json:"name"`
	Price       float64 `json:"price"`
	Stock       int     `json:"stock"`
	Description string  `json:"description"`
	ImageURL    string  `json:"image_url"`
}

type EditProductRequest struct {
	SellerID    int     `json:"seller_id"`
	ProductID   int     `json:"product_id"`
	Name        string  `json:"name"`
	Price       float64 `json:"price"`
	Stock       int     `json:"stock"`
	Description string  `json:"description"`
	ImageURL    string  `json:"image_url"`
}

type ShipOrderRequest struct {
	SellerID int `json:"seller_id"`
	OrderID  int `json:"order_id"`
}

func main() {
	r := gin.Default()
	r.GET("/", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})

	// ── MySQL ─────────────────────────────────────────────────────
	const (
		User     = "root"
		Password = "9151999"
		Host     = "mysql-db"
		Port     = 3306
		DBName   = "ShoppingCart"
	)
	conn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?parseTime=true", User, Password, Host, Port, DBName)
	db, err := sql.Open("mysql", conn)
	if err != nil {
		panic("MySQL 開啟失敗: " + err.Error())
	}
	for i := 0; i < 10; i++ {
		err = db.Ping()
		if err == nil {
			break
		}
		fmt.Println("資料庫還沒準備好，5秒後重試...")
		time.Sleep(5 * time.Second)
	}
	if err != nil {
		panic("MySQL 連線逾時")
	}
	fmt.Println("MySQL 連線成功！")

	// ── Redis Cluster ─────────────────────────────────────────────
	rdb := redis.NewClusterClient(&redis.ClusterOptions{
		Addrs:          []string{"redis-1:6379", "redis-2:6379", "redis-3:6379"},
		ReadOnly:       true,
		RouteByLatency: true,
	})
	ctx := context.Background()
	for i := 0; i < 20; i++ {
		err = rdb.Ping(ctx).Err()
		if err == nil {
			fmt.Println("Redis Cluster 連線成功！")
			break
		}
		fmt.Printf("Redis Cluster 尚未就緒 (%d/20): %v\n", i+1, err)
		time.Sleep(3 * time.Second)
	}
	if err != nil {
		fmt.Println("[warn] Redis Cluster 連線逾時，降級使用 MySQL")
	}

	// ── 路由 ─────────────────────────────────────────────────────
	r.Use(cors.Default())
	login(ctx, rdb, r, db)
	getProduct(ctx, rdb, r, db)
	getCartProduct(ctx, rdb, r, db)
	RegisterCheckoutRoutes(ctx, rdb, r, db)
	registerSellerRoutes(ctx, rdb, r, db)

	r.Run(":8080")
}

// ══════════════════════════════════════════════════════════════════
// Login
// ══════════════════════════════════════════════════════════════════

func login(ctx context.Context, rdb *redis.ClusterClient, r *gin.Engine, db *sql.DB) {
	r.POST("/login", func(c *gin.Context) {
    var req LoginRequest
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(400, gin.H{"message": "請提供正確的登入資訊"})
        return
    }

    dbId, dbPassword, role, sellerID, sellerName := getUserInfo(ctx, rdb, req.Username, db)
	// if req.Password == dbPassword && dbId != 0 {
    if (validateLogin(req.Password, dbPassword, dbId)){
        c.JSON(200, gin.H{
            "message":     "登入成功",
            "id":          dbId,
            "username":    req.Username,
            "role":        role,
            "seller_id":   sellerID,
            "seller_name": sellerName, 
        })
    } else {
        c.JSON(200, gin.H{"message": "登入失敗"})
    }
	})
	r.GET("/login", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})
}
func getUserInfo(ctx context.Context, rdb *redis.ClusterClient, Username string, db *sql.DB) (dbId int, dbPassword string, role int, sellerID int, sellerName string) {
	key := "user:" + Username

	// 1. 查 Redis
	res, err := rdb.HGetAll(ctx, key).Result()
	if err == nil && len(res) > 0 {
		id, _  := strconv.Atoi(res["id"])
		r, _   := strconv.Atoi(res["role"])
		sid, _ := strconv.Atoi(res["seller_id"])
		fmt.Println("Redis 讀取 user 成功")
		// ← username 和 seller_name 也從 Redis 拿
		return id, res["password"], r, sid, res["seller_name"]
	}

	// 2. 查 MySQL
	row := db.QueryRow(`
		SELECT u.id, u.password, u.role,
		       COALESCE(us.seller_id, 0),
		       COALESCE(s.name, '')
		FROM users u
		LEFT JOIN user_seller us ON us.user_id = u.id
		LEFT JOIN seller s ON s.id = us.seller_id AND s.deleted_at IS NULL
		WHERE u.username = ? AND u.deleted_at IS NULL
	`, Username)

	err = row.Scan(&dbId, &dbPassword, &role, &sellerID, &sellerName)
	if err == sql.ErrNoRows {
		return 0, "", 0, 0, ""
	} else if err != nil {
		fmt.Println("資料庫查詢錯誤:", err)
		return 0, "", 0, 0, ""
	}

	// 3. 回填 Redis
	rdb.HSet(ctx, key, map[string]interface{}{
		"id":          dbId,
		"password":    dbPassword,
		"role":        role,
		"seller_id":   sellerID,
		"username":    Username,
		"seller_name": sellerName,
	})
	rdb.Expire(ctx, key, 30*time.Minute)

	return dbId, dbPassword, role, sellerID, sellerName
}
// ══════════════════════════════════════════════════════════════════
// Products
// ══════════════════════════════════════════════════════════════════

func getProduct(ctx context.Context, rdb *redis.ClusterClient, r *gin.Engine, db *sql.DB) {
	r.Static("/uploads", "/home/ubuntu/shopping-cart/api/uploads")
	imageBaseUrl := "http://localhost:3000/uploads/product/"

	r.GET("/products", func(c *gin.Context) {
		key := "products"
		res, err := rdb.Get(ctx, key).Result()
		if err == nil && len(res) > 0 {
			var products []map[string]interface{}
			if err := json.Unmarshal([]byte(res), &products); err == nil {
				c.JSON(200, products)
				return
			}
		}
		rows, err := db.Query("SELECT id, name, price, image_url FROM product WHERE deleted_at IS NULL AND stock > 0 LIMIT 10")
		if err != nil {
			c.JSON(500, gin.H{"error": err.Error()})
			return
		}
		defer rows.Close()
		var products []map[string]interface{}
		for rows.Next() {
			var name, imageUrl string
			var product_id, price int
			rows.Scan(&product_id, &name, &price, &imageUrl)
			products = append(products, gin.H{
				"product_id": product_id,
				"name":       name,
				"price":      price,
				"image_url":  fmt.Sprintf("%s%s", imageBaseUrl, imageUrl),
			})
		}
		jsonData, _ := json.Marshal(products)
		rdb.Set(ctx, key, jsonData, 30*time.Minute)
		c.JSON(200, products)
	})

	r.POST("/product_detail", func(c *gin.Context) {
		var req productRequest
		if err := c.ShouldBindJSON(&req); err != nil || req.Id == 0 {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}
		key := "product_id:" + strconv.Itoa(req.Id)
		res, err := rdb.Get(ctx, key).Result()
		if err == nil && len(res) > 0 {
			var productInfo map[string]interface{}
			if err := json.Unmarshal([]byte(res), &productInfo); err == nil {
				c.JSON(200, productInfo)
				return
			}
		}
		var name, description, image_url, seller_name, seller_email string
		var price, stock, seller_id int
		row := db.QueryRow("SELECT name, description, price, image_url, stock, seller_id FROM product WHERE id = ? AND deleted_at IS NULL", req.Id)
		if err := row.Scan(&name, &description, &price, &image_url, &stock, &seller_id); err != nil {
			c.JSON(500, gin.H{"error": "fetch product info"})
			return
		}
		db.QueryRow("SELECT name, COALESCE(email, '') FROM seller WHERE id = ?", seller_id).Scan(&seller_name, &seller_email)

		productInfo := gin.H{
			"name": name, "price": price, "stock": stock,
			"image_url":    fmt.Sprintf("%s%s", imageBaseUrl, image_url),
			"description":  description,
			"seller_name":  seller_name,
			"seller_email": seller_email,
		}
		jsonData, _ := json.Marshal(productInfo)
		rdb.Set(ctx, key, jsonData, 30*time.Minute)
		c.JSON(200, productInfo)
	})
}

// ══════════════════════════════════════════════════════════════════
// Cart
// ══════════════════════════════════════════════════════════════════

func getCartProduct(ctx context.Context, rdb *redis.ClusterClient, r *gin.Engine, db *sql.DB) {
	imageBaseUrl := "http://localhost:3000/uploads/product/"

	r.POST("/cart", func(c *gin.Context) {
		var req productRequest
		var productInfo []gin.H
		var user_id, product_id, quantity int

		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}

		if req.Id != 0 && req.Quantity != 0 {
			result, err := db.Exec("UPDATE cart_item SET quantity = quantity + ? WHERE product_id = ? AND user_id = ?", req.Quantity, req.Id, req.UserID)
			if err != nil {
				c.JSON(500, gin.H{"error": "failed to update cart_item"})
				return
			}
			rowsAffected, _ := result.RowsAffected()
			if rowsAffected == 0 {
				_, err := db.Exec("INSERT INTO cart_item (user_id, product_id, quantity) VALUES (?, ?, ?)", req.UserID, req.Id, req.Quantity)
				if err != nil {
					c.JSON(500, gin.H{"error": "新增至購物車失敗"})
					return
				}
			}
			rdb.Del(ctx, fmt.Sprintf("cart:%d", req.UserID))
		}

		cartKey := fmt.Sprintf("cart:%d", req.UserID)
		cartCache, err := rdb.Get(ctx, cartKey).Result()
		if err == nil && len(cartCache) > 0 {
			if err := json.Unmarshal([]byte(cartCache), &productInfo); err == nil {
				c.JSON(200, productInfo)
				return
			}
		}

		rows, err := db.Query("SELECT user_id, product_id, quantity FROM cart_item WHERE user_id = ?", req.UserID)
		if err != nil {
			c.JSON(500, gin.H{"error": "fetch cart_item info"})
			return
		}
		defer rows.Close()

		for rows.Next() {
			rows.Scan(&user_id, &product_id, &quantity)
			var name, imageUrl string
			var price int
			db.QueryRow("SELECT name, price, image_url FROM product WHERE id = ?", product_id).Scan(&name, &price, &imageUrl)
			productInfo = append(productInfo, gin.H{
				"product_id": product_id,
				"name":       name,
				"price":      price,
				"quantity":   quantity,
				"image_url":  fmt.Sprintf("%s%s", imageBaseUrl, imageUrl),
			})
		}

		if len(productInfo) > 0 {
			jsonData, _ := json.Marshal(productInfo)
			rdb.Set(ctx, cartKey, jsonData, 30*time.Minute)
		}
		c.JSON(200, productInfo)
	})

	r.POST("/cart_update", func(c *gin.Context) {
		var req productRequest
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}
		db.Exec("UPDATE cart_item SET quantity = ? WHERE product_id = ? AND user_id = ?", req.Quantity, req.Id, req.UserID)
		rdb.Del(ctx, fmt.Sprintf("cart:%d", req.UserID))
		c.JSON(200, gin.H{"message": "更新成功"})
	})
}

// ══════════════════════════════════════════════════════════════════
// Checkout
// ══════════════════════════════════════════════════════════════════

func RegisterCheckoutRoutes(ctx context.Context, rdb *redis.ClusterClient, r *gin.Engine, db *sql.DB) {
	r.POST("/checkout", func(c *gin.Context) {
		var req CheckoutRequest
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "格式錯誤"})
			return
		}
		tx, err := db.Begin()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "無法啟動事務"})
			return
		}
		defer func() {
			if r := recover(); r != nil {
				tx.Rollback()
			}
		}()

		res, err := tx.Exec("INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, ?)", req.UserID, 0, "pending")
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "建立訂單失敗"})
			return
		}
		orderID, _ := res.LastInsertId()

		var grandTotal float64
		var changedProductIDs []int

		for _, item := range req.Items {
			var price float64
			var stock int
			var name string
			err := tx.QueryRow("SELECT name, price, stock FROM product WHERE id = ? FOR UPDATE", item.ProductID).Scan(&name, &price, &stock)
			if err != nil {
				tx.Rollback()
				c.JSON(http.StatusNotFound, gin.H{"error": "找不到商品"})
				return
			}
			if !checkStock(stock, item.Quantity) {
				tx.Rollback()
				c.JSON(http.StatusBadRequest, gin.H{"error": name + " 庫存不足"})
				return
			}
			tx.Exec("UPDATE product SET stock = stock - ? WHERE id = ?", item.Quantity, item.ProductID)
			tx.Exec("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)", orderID, item.ProductID, item.Quantity, price)
			grandTotal += calculateTotal(price, item.Quantity)
			changedProductIDs = append(changedProductIDs, item.ProductID)
		}

		tx.Exec("UPDATE orders SET total_price = ? WHERE id = ?", grandTotal, orderID)
		if err := tx.Commit(); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "提交交易失敗"})
			return
		}

		go func() {
			bgCtx := context.Background()
			rdb.Del(bgCtx, fmt.Sprintf("cart:%d", req.UserID))
			db.Exec("DELETE FROM cart_item WHERE user_id = ?", req.UserID)
			for _, pid := range changedProductIDs {
				rdb.Del(bgCtx, fmt.Sprintf("product_id:%d", pid))
			}
			rdb.Del(bgCtx, "products")
		}()

		c.JSON(http.StatusOK, gin.H{"message": "結帳成功", "order_id": orderID})
	})
}

// ══════════════════════════════════════════════════════════════════
// Seller Routes
// ══════════════════════════════════════════════════════════════════

func registerSellerRoutes(ctx context.Context, rdb *redis.ClusterClient, r *gin.Engine, db *sql.DB) {

	// ── POST /seller/register：成為賣家 ───────────────────────────
	r.POST("/seller/register", func(c *gin.Context) {
		var req BecomeSellerRequest
		if err := c.ShouldBindJSON(&req); err != nil || req.UserID == 0 || req.Name == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "請填寫完整資料"})
			return
		}

		// 確認 user 存在且還不是 seller
		var role int
		err := db.QueryRow("SELECT role FROM users WHERE id = ?", req.UserID).Scan(&role)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "找不到使用者"})
			return
		}
		if role == 1 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "已經是賣家"})
			return
		}

		// 確認 seller name 不重複
		var exists int
		db.QueryRow("SELECT COUNT(*) FROM seller WHERE name = ?", req.Name).Scan(&exists)
		if exists > 0 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "店鋪名稱已被使用"})
			return
		}

		tx, err := db.Begin()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "無法啟動事務"})
			return
		}
		defer func() {
			if r := recover(); r != nil {
				tx.Rollback()
			}
		}()

		// INSERT seller
		res, err := tx.Exec(
			"INSERT INTO seller (name, email) VALUES (?, ?)",
			req.Name, req.Email,
		)
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "建立賣家失敗"})
			return
		}
		sellerID, _ := res.LastInsertId()

		// INSERT user_seller
		_, err = tx.Exec(
			"INSERT INTO user_seller (user_id, seller_id) VALUES (?, ?)",
			req.UserID, sellerID,
		)
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "綁定失敗"})
			return
		}

		// UPDATE users role = 1
		_, err = tx.Exec("UPDATE users SET role = 1 WHERE id = ?", req.UserID)
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "更新角色失敗"})
			return
		}

		if err := tx.Commit(); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "提交失敗"})
			return
		}

		// 清 Redis user cache，下次登入重新讀取 role & seller_id
		username := ""
		db.QueryRow("SELECT username FROM users WHERE id = ?", req.UserID).Scan(&username)
		if username != "" {
			rdb.Del(ctx, "user:"+username)
		}

		c.JSON(http.StatusOK, gin.H{
			"message":   "成為賣家成功",
			"seller_id": sellerID,
		})
	})

	// ── GET /seller/products?seller_id=1 ─────────────────────────
	r.GET("/seller/products", func(c *gin.Context) {
		sellerID, err := strconv.Atoi(c.Query("seller_id"))
		if err != nil || sellerID == 0 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "seller_id 錯誤"})
			return
		}
		rows, err := db.Query(`
			SELECT id, name, price, stock, COALESCE(description,''), COALESCE(image_url,'')
			FROM product
			WHERE seller_id = ? AND deleted_at IS NULL
			ORDER BY created_at DESC
		`, sellerID)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "查詢失敗"})
			return
		}
		defer rows.Close()

		var products []gin.H
		for rows.Next() {
			var id, stock int
			var name, desc, imageURL string
			var price float64
			rows.Scan(&id, &name, &price, &stock, &desc, &imageURL)
			products = append(products, gin.H{
				"id": id, "name": name, "price": price,
				"stock": stock, "description": desc, "image_url": imageURL,
			})
		}
		c.JSON(http.StatusOK, gin.H{"products": products})
	})

	// ── POST /seller/product/add ──────────────────────────────────
	r.POST("/seller/product/add", func(c *gin.Context) {
		var req AddProductRequest
		if err := c.ShouldBindJSON(&req); err != nil || req.SellerID == 0 || req.Name == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "請填寫完整商品資料"})
			return
		}

		// 確認 seller 存在
		var count int
		db.QueryRow("SELECT COUNT(*) FROM seller WHERE id = ? AND deleted_at IS NULL", req.SellerID).Scan(&count)
		if count == 0 {
			c.JSON(http.StatusForbidden, gin.H{"error": "無效的 seller"})
			return
		}

		_, err := db.Exec(`
			INSERT INTO product (name, price, stock, description, image_url, seller_id)
			VALUES (?, ?, ?, ?, ?, ?)
		`, req.Name, req.Price, req.Stock, req.Description, req.ImageURL, req.SellerID)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "新增商品失敗: " + err.Error()})
			return
		}

		// 清商品列表 cache
		rdb.Del(ctx, "products")
		c.JSON(http.StatusOK, gin.H{"message": "商品新增成功"})
	})

	// ── POST /seller/product/edit ─────────────────────────────────
	r.POST("/seller/product/edit", func(c *gin.Context) {
		var req EditProductRequest
		if err := c.ShouldBindJSON(&req); err != nil || req.ProductID == 0 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "格式錯誤"})
			return
		}

		// 確認這個商品屬於這個 seller
		var ownerID int
		db.QueryRow("SELECT seller_id FROM product WHERE id = ? AND deleted_at IS NULL", req.ProductID).Scan(&ownerID)
		if ownerID != req.SellerID {
			c.JSON(http.StatusForbidden, gin.H{"error": "無權限編輯此商品"})
			return
		}

		_, err := db.Exec(`
			UPDATE product
			SET name=?, price=?, stock=?, description=?, image_url=?
			WHERE id=?
		`, req.Name, req.Price, req.Stock, req.Description, req.ImageURL, req.ProductID)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "更新失敗"})
			return
		}

		// 清 cache
		rdb.Del(ctx, fmt.Sprintf("product_id:%d", req.ProductID))
		rdb.Del(ctx, "products")
		c.JSON(http.StatusOK, gin.H{"message": "商品更新成功"})
	})

	// ── POST /seller/product/delete ───────────────────────────────
	r.POST("/seller/product/delete", func(c *gin.Context) {
		var body struct {
			SellerID  int `json:"seller_id"`
			ProductID int `json:"product_id"`
		}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "格式錯誤"})
			return
		}

		var ownerID int
		db.QueryRow("SELECT seller_id FROM product WHERE id = ? AND deleted_at IS NULL", body.ProductID).Scan(&ownerID)
		if ownerID != body.SellerID {
			c.JSON(http.StatusForbidden, gin.H{"error": "無權限刪除此商品"})
			return
		}

		// 軟刪除
		db.Exec("UPDATE product SET deleted_at = NOW() WHERE id = ?", body.ProductID)
		rdb.Del(ctx, fmt.Sprintf("product_id:%d", body.ProductID))
		rdb.Del(ctx, "products")
		c.JSON(http.StatusOK, gin.H{"message": "商品已下架"})
	})

	// ── GET /seller/orders?seller_id=1 ───────────────────────────
	r.GET("/seller/orders", func(c *gin.Context) {
		sellerID, err := strconv.Atoi(c.Query("seller_id"))
		if err != nil || sellerID == 0 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "seller_id 錯誤"})
			return
		}

		// 撈出此 seller 商品的訂單（status = pending/paid）
		rows, err := db.Query(`
			SELECT DISTINCT o.id, o.user_id, o.total_price, o.status, o.created_at
			FROM orders o
			INNER JOIN order_items oi ON oi.order_id = o.id
			INNER JOIN product p ON p.id = oi.product_id
			WHERE p.seller_id = ?
			  AND o.status IN ('pending', 'paid')
			ORDER BY o.created_at DESC
		`, sellerID)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "查詢訂單失敗"})
			return
		}
		defer rows.Close()

		var orders []gin.H
		for rows.Next() {
			var id, userID int
			var total float64
			var status, createdAt string
			rows.Scan(&id, &userID, &total, &status, &createdAt)
			orders = append(orders, gin.H{
				"order_id":   id,
				"user_id":    userID,
				"total":      total,
				"status":     status,
				"created_at": createdAt,
			})
		}
		c.JSON(http.StatusOK, gin.H{"orders": orders})
	})

	// ── POST /seller/order/ship ───────────────────────────────────
	r.POST("/seller/order/ship", func(c *gin.Context) {
		var req ShipOrderRequest
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "格式錯誤"})
			return
		}

		// 確認訂單內有此 seller 的商品
		var count int
		db.QueryRow(`
			SELECT COUNT(*) FROM order_items oi
			INNER JOIN product p ON p.id = oi.product_id
			WHERE oi.order_id = ? AND p.seller_id = ?
		`, req.OrderID, req.SellerID).Scan(&count)

		if count == 0 {
			c.JSON(http.StatusForbidden, gin.H{"error": "無權限操作此訂單"})
			return
		}

		_, err := db.Exec(
			"UPDATE orders SET status = 'shipped' WHERE id = ? AND status != 'shipped'",
			req.OrderID,
		)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": "更新失敗"})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "已更新為已出貨"})
	})
}

func checkStock(stock int,  quantity int)bool{
	return stock >= quantity
}
func calculateTotal(price float64, quantity int) float64 {
    return price * float64(quantity)
}
func validateLogin(inputPassword string, dbPassword string, dbId int)bool{
	return inputPassword == dbPassword && dbId != 0
}