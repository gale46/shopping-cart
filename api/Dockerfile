#指定環境
FROM golang:1.25-alpine

#安裝必要的編譯工具 
RUN apk add --no-cache gcc musl-dev

#設定工作目錄 
WORKDIR /app

# 3. 複製相依性清單並下載 (像執行 go get)
COPY go.mod go.sum ./
RUN go mod download


COPY . .

RUN go build -o ./bin/app main.go

CMD ["./bin/app"]