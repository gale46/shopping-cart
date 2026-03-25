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
	//登入時使用LoginRequest結構

	r.GET("/", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})

	//建立登入路由
	r.POST("/login", func(c *gin.Context) {
		var req LoginRequest
		c.ShouldBindJSON(&req)
		//對比撈出的username 和 pw
		dbId, dbPassword := getUserInfo(req.Username)

		if err := c.ShouldBindJSON(&req); err != nil && req.Password == dbPassword {
			c.JSON(200, gin.H{"message": "登入成功", "id": dbId})
		} else {
			c.JSON(200, gin.H{"message": "登入失敗"})
		}
	})
	r.GET("/login", func(c *gin.Context) {
		c.String(200, "Hello from shopping-cart API")
	})

	r.Run(":8080")
}
func getUserInfo(Username string) (dbId int, dbPpassword string) {
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

	//由username找id + pw
	// |----------------|
	// |----------------|
	var dbPassword string
	row := db.QueryRow("SELECT id, password FROM users WHERE username = ?", Username)
	err = row.Scan(&dbId, &dbPassword)

	// 3. 處理錯誤（這步最重要）
	if err == sql.ErrNoRows {
		fmt.Println("找不到該使用者")
		return 0, ""
	} else if err != nil {
		fmt.Println("資料庫查詢錯誤:", err)
		return 0, ""
	}

	return dbId, dbPassword //查詢後的id + pw

}
