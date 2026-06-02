FROM php:8.3-fpm

# تثبيت الاعتماديات الأساسية
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install

# حل مشكلة الـ TypeError قسرياً داخل Laravel قبل التشغيل
# تحويل البورت إلى integer قبل جمعه مع portOffset
RUN sed -i 's/$port = $port ?: 8000;/$port = (int)($port ?: 8000);/g' vendor/laravel/framework/src/Illuminate/Foundation/Console/ServeCommand.php

# تشغيل السيرفر المدمج مباشرة وتجنب الـ Artisan serve تماماً
CMD php -S 0.0.0.0:$PORT -t public