package main

import (
	"database/sql"
	"fmt"
	"time"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/gin-contrib/cors"
	"net/http"
)

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type productRequest struct {
	Id int `json:"product_id"`
	Quantity int `json:"quantity"`
}

// 定義模型
type Product struct {
	ID    uint    `gorm:"primaryKey"`
	Name  string  `json:"name"`
	Price float64 `json:"price"`
	Stock int     `json:"stock"`
}

type Order struct {
	ID         uint    `gorm:"primaryKey"`
	UserID     int     `json:"user_id"`
	TotalPrice float64 `json:"total_price"`
	Status     string  `json:"status"`
}

type OrderItem struct {
	ID              uint    `gorm:"primaryKey"`
	OrderID         uint    `json:"order_id"`
	ProductID       int     `json:"product_id"`
	Quantity        int     `json:"quantity"`
	PriceAtPurchase float64 `json:"price_at_purchase"`
}

// 接收前端 JSON 的結構
type CheckoutRequest struct {
	UserID int `json:"user_id"`
	Items  []struct {
		ProductID int `json:"product_id"`
		Quantity  int `json:"quantity"`
	} `json:"items"`
}
func main() {
	//初始化
	r := gin.Default()
	r.GET("/", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})
	const (
		User     = "root"
		Password = "9151999"
		Host     = "mysql-db" // container_name(server)
		Port     = 3306
		DBName   = "ShoppingCart" //MYSQL_DATABASE
	)

	conn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?parseTime=true", User, Password, Host, Port, DBName)

	db, err := sql.Open("mysql", conn)
	for i := 0; i < 10; i++ {
		err = db.Ping()
		if err == nil {
			break
		}
		fmt.Println("資料庫還沒準備好，5秒後重試...")
		time.Sleep(5 * time.Second)
	}

	fmt.Println("MySQL 連線成功！")
	r.Use(cors.Default())
	login(r, db)

	//讀取商品資訊
	getProduct(r, db)

	getCartProduct(r, db)
	RegisterCheckoutRoutes(r, db)
	r.Run(":8080")
}

// php to go
func login(r *gin.Engine, db *sql.DB) {
	//建立登入路由
	r.POST("/login", func(c *gin.Context) {
		var req LoginRequest
		//登入時使用LoginRequest結構
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"message": "請提供正確的登入資訊"})
			return
		}
		//對比撈出的username 和 pw
		dbId, dbPassword := getUserInfo(req.Username, db)

		if req.Password == dbPassword && dbId != 0 {
			c.JSON(200, gin.H{"message": "登入成功", "id": dbId})
		} else {
			c.JSON(200, gin.H{"message": "登入失敗"})
		}
	})
	r.GET("/login", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})
}

func getUserInfo(Username string, db *sql.DB) (dbId int, dbPassword string) {

	row := db.QueryRow("SELECT id, password FROM users WHERE username = ?", Username)
	err := row.Scan(&dbId, &dbPassword)

	// 處理錯誤
	if err == sql.ErrNoRows {
		fmt.Println("找不到該使用者")
		return 0, ""
	} else if err != nil {
		fmt.Println("資料庫查詢錯誤:", err)
		return 0, ""
	}

	return dbId, dbPassword //查詢後的id + pw

}

// go to php
func getProduct(r *gin.Engine, db *sql.DB) {
	// r.Static("網路路徑", "實體路徑")
	r.Static("/uploads", "/home/ubuntu/shopping-cart/api/uploads")

	r.GET("/products", func(c *gin.Context) {
		// 前 10 筆
		rows, err := db.Query("SELECT id, name, price, image_url FROM product LIMIT 10")
		if err != nil {
			c.JSON(500, gin.H{"error": err.Error()})
			return
		}
		defer rows.Close()

		var products []gin.H // 建立一個存放多個商品的陣列
		imageBaseUrl := "http://localhost:3000/uploads/product/"

		for rows.Next() {
			var name, imageUrl string
			var product_id, price int
			if err := rows.Scan(&product_id, &name, &price, &imageUrl); err != nil {
				c.JSON(500, gin.H{"error": "fetch product scan error"})
				return
			}

			// 拼接網址並存入陣列
			products = append(products, gin.H{
				"product_id":        product_id,
				"name":      name,
				"price":     price,
				"image_url": fmt.Sprintf("%s%s", imageBaseUrl, imageUrl),
			})
		}

		// 直接回傳整個陣列
		c.JSON(200, products)
	})
}

func getCartProduct(r *gin.Engine, db *sql.DB) {
	r.POST("/cart", func(c *gin.Context) {
		var req productRequest
		var productInfo []gin.H
		imageBaseUrl := "http://localhost:3000/uploads/product/"

		var user_id , product_id , quantity int
		
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}
		// 若收到的不等於0表示
		// 表user加入商品(req.Quantity, req.Id, 1) = (product_quantity, product_id, user_id)
		//todo
		if req.Id != 0 && req.Quantity != 0 {
			result, err := db.Exec("UPDATE cart_item SET quantity = quantity + ? WHERE product_id = ? AND user_id = ?", req.Quantity, req.Id, 1)
			if err != nil {
				c.JSON(500, gin.H{"error": "failed to update cart_item"})
				return
			}
			
			rowsAffected, _ := result.RowsAffected()
			
			if rowsAffected == 0 {
				_, err := db.Exec("INSERT INTO cart_item (user_id, product_id, quantity) VALUES (?, ?, ?)", 1, req.Id, req.Quantity)
				if err != nil {
					c.JSON(500, gin.H{"error": "新增至購物車失敗"})
					return
				}
			}
		}

		// 更新完Quantity or 單純查看，提出資料並傳給web
		// 預設user = 1
		rows, err := db.Query("SELECT user_id , product_id , quantity FROM cart_item WHERE user_id = ?", 1)
		if err != nil{
			c.JSON(500, gin.H{"error": "fetch cart_item info"})
			return
		}
		//因cart_item提出同一個user會有多個product_id
		// 需要先處理每個array
		for rows.Next() {
			rows.Scan(&user_id , &product_id , &quantity);
			//依照每個user的cart_item
			// 尋找加入購物車的product
			var name, imageUrl string
			var price int
			err := db.QueryRow("SELECT name, price, image_url FROM product WHERE id = ?", product_id).Scan(&name, &price, &imageUrl)
			if err != nil {
				c.JSON(500, gin.H{"error": "fetch product info"})
				return
			}

			productInfo = append(productInfo, gin.H{
				"product_id": product_id,
				"name":      name,
				"price":     price,
				"quantity":  quantity,
				"image_url": fmt.Sprintf("%s%s", imageBaseUrl, imageUrl),
			})
		}
		c.JSON(200, productInfo)

	})



	r.POST("/product_detail", func(c *gin.Context) {
		var req productRequest
		var productInfo gin.H
		var name, description, image_url, seller_name, seller_email string
		var price, stock, seller_id int
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}

		if req.Id == 0 {
			c.JSON(400, gin.H{"error": "request fail"})
			return
		}
		row := db.QueryRow("SELECT name, description, price, image_url, stock, seller_id FROM product WHERE id = ?", req.Id)
		if err := row.Scan(&name, &description, &price, &image_url, &stock, &seller_id); err != nil{
			c.JSON(500, gin.H{"error":"fetch product info"})
			return
		}
		if seller_id == 0{
			c.JSON(400, gin.H{"error": "seller_id fetch error"})
		}

		row_seller := db.QueryRow("SELECT name, COALESCE(email, '') FROM seller WHERE id = ?", seller_id)
		if err := row_seller.Scan(&seller_name, &seller_email);err != nil{
			c.JSON(500, gin.H{"error":"fetch seller info"})
			return
		}

		imageBaseUrl := "http://localhost:3000/uploads/product/"

		productInfo = gin.H{	
			"name":        name,
			"price":       price,
			"stock":       stock,
			"image_url": 	fmt.Sprintf("%s%s", imageBaseUrl, image_url),
			"description": description,
			"seller_name":  seller_name,
			"seller_email": seller_email,
		}
		c.JSON(200, productInfo)

	})
	r.POST("/cart_update",func(c *gin.Context){
		var req productRequest
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request body"})
			return
		}
		_, err := db.Exec("UPDATE cart_item SET quantity = ?  WHERE product_id = ? AND user_id = ?", req.Quantity, req.Id, 1)
		if err != nil {
			c.JSON(500, gin.H{"error": "failed to update cart_item"})
			return
		}

	})
}

// RegisterCheckoutRoutes 負責初始化路由與依賴注入
func RegisterCheckoutRoutes(r *gin.Engine, db *sql.DB) {

	// 定義路由，並將 db 傳入 Handler
	r.POST("/checkout", func(c *gin.Context) {
		CheckoutHandler(c, db)
	})
}
// CheckoutHandler 使用原生的 *sql.DB
func CheckoutHandler(c *gin.Context, db *sql.DB) {
	var req CheckoutRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "格式錯誤"})
		return
	}

	// 1. 開始事務 (原生回傳兩個值：Tx 和 error)
	tx, err := db.Begin()
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "無法啟動事務"})
		return
	}

	// 確保發生錯誤時回滾
	defer func() {
		if r := recover(); r != nil {
			tx.Rollback()
		}
	}()

	// 2. 建立訂單主檔並取得自動生成的 ID
	res, err := tx.Exec("INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, ?)", 
		req.UserID, 0, "pending")
	if err != nil {
		tx.Rollback()
		c.JSON(http.StatusInternalServerError, gin.H{"error": "建立訂單失敗"})
		return
	}
	orderID, _ := res.LastInsertId()

	var grandTotal float64 = 0

	// 3. 處理每一項商品 (扣庫存 + 寫入明細)
	for _, item := range req.Items {
		var price float64
		var stock int
		var name string

		// 查詢商品資訊 (加鎖 FOR UPDATE 防止併發衝突)
		err := tx.QueryRow("SELECT name, price, stock FROM product WHERE id = ? FOR UPDATE", 
			item.ProductID).Scan(&name, &price, &stock)
		
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusNotFound, gin.H{"error": "找不到商品"})
			return
		}

		// 檢查庫存
		if stock < item.Quantity {
			tx.Rollback()
			c.JSON(http.StatusBadRequest, gin.H{"error": name + " 庫存不足"})
			return
		}

		// 更新庫存
		_, err = tx.Exec("UPDATE product SET stock = stock - ? WHERE id = ?", 
			item.Quantity, item.ProductID)
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "更新庫存失敗"})
			return
		}

		// 寫入明細
		_, err = tx.Exec("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)", 
			orderID, item.ProductID, item.Quantity, price)
		if err != nil {
			tx.Rollback()
			c.JSON(http.StatusInternalServerError, gin.H{"error": "寫入明細失敗"})
			return
		}

		grandTotal += price * float64(item.Quantity)
	}

	// 4. 更新訂單總金額
	_, err = tx.Exec("UPDATE orders SET total_price = ? WHERE id = ?", grandTotal, orderID)
	if err != nil {
		tx.Rollback()
		c.JSON(http.StatusInternalServerError, gin.H{"error": "更新總價失敗"})
		return
	}

	// 5. 提交事務
	if err := tx.Commit(); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": "提交交易失敗"})
		return
	}

	c.JSON(http.StatusOK, gin.H{"message": "結帳成功", "order_id": orderID})
}