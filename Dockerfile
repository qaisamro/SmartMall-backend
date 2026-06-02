FROM php:8.3-apache

# تثبيت الاعتماديات الأساسية والـ zip
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# تفعيل مود الـ Rewrite الخاص بـ Apache لـ Laravel
RUN a2enmod rewrite

# تثبيت إضافات الـ PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# تغيير الـ Document Root الخاص بـ Apache ليتوجه لمجلد public مباشرة
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ضبط البورت ليتوافق مع Railway تلقائياً
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install

# تشغيل Apache في الواجهة
CMD ["apache2-foreground"]