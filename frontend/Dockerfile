FROM php:8.2-apache

# 1. 更新系統索引並安裝基礎工具
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. 安裝並啟用 PDO MySQL 擴展
# 注意：docker-php-ext-install 會自動處理編譯與設定
RUN docker-php-ext-install pdo pdo_mysql

# 3. 如果你有用到 Redis
RUN pecl install redis && docker-php-ext-enable redis

# 4. 啟動 Apache Rewrite 模組
RUN a2enmod rewrite
# 將 login.php 複製到 Apache 預設目錄
COPY . /var/www/html/

# 開放 Web Port
EXPOSE 80