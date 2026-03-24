# 使用內建 Apache 的 PHP 鏡像
FROM php:8.2-apache

# 安裝 PHP 的 Redis 擴展 (讓 PHP 能與 Redis 溝通)
RUN pecl install redis && docker-php-ext-enable redis

# 將 login.php 複製到 Apache 預設目錄
COPY . /var/www/html/

# 開放 Web Port
EXPOSE 80