package main

import (
	"database/sql"
	"fmt"
	"time"

	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
)

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type productRequest struct {
	Id int `json:"id"`
	Quantity int `json:"quantity"`
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

	login(r, db)

	//讀取商品資訊
	getProduct(r, db)

	getCartProduct(r, db)

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
			var id, price int
			if err := rows.Scan(&id, &name, &price, &imageUrl); err != nil {
				c.JSON(500, gin.H{"error": "fetch product scan error"})
				return
			}

			// 拼接網址並存入陣列
			products = append(products, gin.H{
				"id":        id,
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





		// 加上判斷式
		// 判斷為加入購物車或是單純查看購物車
		//存入cart_item資訊
		// update 使用 user_id, product_id更新Quantity
		if (req.Id != 0 && req.Quantity != 0){
			rows, err := db.Query("SELECT * FROM cart_item WHERE user_id = ?", 1)
			if err != nil{
				c.JSON(500, gin.H{"error": "fetch cart_item info"})
				return
			}
			//因cart_item提出同一個user會有多個product_id
			for rows.Next() {
				rows.Scan(&user_id , &product_id , &quantity);
				//依照每個user的cart_item
				// 尋找加入購物車的product
				//todo
			// |-----------------------------------------|目前是update web回傳沒有加上原先的
			// |-----------------------------------------|
				_, err := db.Exec("UPDATE cart_item SET quantity = ? WHERE product_id = ? AND user_id = ?", quantity, product_id, user_id)
				if err != nil {
					c.JSON(500, gin.H{"error": "failed to update cart_item"})
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

		row_seller := db.QueryRow("SELECT name, email FROM seller WHERE id = ?", seller_id)
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

}
