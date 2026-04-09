package main

import (
	"context"
	"fmt"
	"github.com/redis/go-redis/v9"
)

// 定義一個全局變數，這樣其他函數也能用
var rdb *redis.ClusterClient // 注意：你之前是用 Cluster，記得維持一致
var ctx = context.Background()

func main() {
	// 1. 初始化 Redis Cluster (修正 Addr 並放入函數內)
	rdb = redis.NewClusterClient(&redis.ClusterOptions{
		Addrs: []string{
			"redis-1:6379", 
			"redis-2:6379", 
			"redis-3:6379",
		},
	})

	// 2. 測試連線
	err := rdb.Ping(ctx).Err()
	if err != nil {
		fmt.Println("Redis 連線失敗:", err)
	} else {
		fmt.Println("Redis Cluster 連線成功！")
	}
}