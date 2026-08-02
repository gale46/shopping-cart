package main

import "testing"

func TestCalculateTotal(t *testing.T) {
	if (calculateTotal(10, 20) != 200){
		t.Error("應該是200")
	}
	if (calculateTotal(0, 20) != 0){
		t.Error("應該是0")
	}
	if (calculateTotal(10.5, 20) != 210){
		t.Error("應該是210")
	}
}

func TestCheckStock(t *testing.T) {
	// 庫存足夠
	if !checkStock(10, 3) {
		t.Error("庫存 10 購買 3，應該要成功")
	}

	// 庫存不足
	if checkStock(2, 5) {
		t.Error("庫存 2 購買 5，應該要失敗")
	}

	// 庫存剛好
	if !checkStock(5, 5) {
		t.Error("庫存 5 購買 5，應該要成功")
	}

	// 庫存為零
	if checkStock(0, 1) {
		t.Error("庫存 0 購買 1，應該要失敗")
	}
}


func TestValidateLogin(t *testing.T){
	if(!validateLogin("1234", "1234", 1)){
		t.Error("密碼正確應該登入成功")
	}
	if !validateLogin("123456", "123456", 1) {
		t.Error("密碼正確應該登入成功")
	}

	// 錯誤密碼
	if validateLogin("wrong", "123456", 1) {
		t.Error("密碼錯誤應該登入失敗")
	}

	// user 不存在（id = 0）
	if validateLogin("123456", "123456", 0) {
		t.Error("user id 為 0 應該登入失敗")
	}
}