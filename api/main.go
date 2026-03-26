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

func main() {
	//初始化
	r := gin.Default()
	r.GET("/", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})
	const (
		User     = "root"
		Password = ""
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

	r.Run(":8080")
}

func login(r *gin.Engine, db *sql.DB) {
	//建立登入路由
	r.POST("/login", func(c *gin.Context) {
		var req LoginRequest
		//登入時使用LoginRequest結構
		c.ShouldBindJSON(&req)
		//對比撈出的username 和 pw
		dbId, dbPassword := getUserInfo(req.Username, db)

		if err := c.ShouldBindJSON(&req); err != nil && req.Password == dbPassword {
			c.JSON(200, gin.H{"message": "登入成功", "id": dbId})
		} else {
			c.JSON(200, gin.H{"message": "登入失敗"})
		}
	})
	r.GET("/login", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})
}

func getUserInfo(Username string, db *sql.DB) (dbId int, dbPpassword string) {

	var dbPassword string
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
			rows.Scan(&id, &name, &price, &imageUrl)

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
