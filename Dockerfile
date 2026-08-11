FROM php:7.2.34-apache

# Переключение репозиториев на archive.debian.org (buster is EOL)
RUN echo "deb http://archive.debian.org/debian buster main" > /etc/apt/sources.list \
    && echo "deb http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list \
    && echo "deb http://archive.debian.org/debian buster-updates main" >> /etc/apt/sources.list

# Установка системных зависимостей
RUN apt-get update && apt-get install -y --allow-unauthenticated \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Копирование приложения
COPY . /var/www/html/

# Права на загрузку
RUN mkdir -p /var/www/html/uploads /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads /var/www/html/exports

# Установка PHP-зависимостей
WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=php

# Apache конфигурация
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/vshpm.conf \
    && a2enconf vshpm

EXPOSE 80

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]