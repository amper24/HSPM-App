FROM php:7.2.34-apache

# Переключение репозиториев на archive.debian.org (buster is EOL) с оптимизацией скорости
RUN echo "deb [check-valid-until=no] http://archive.debian.org/debian buster main" > /etc/apt/sources.list \
    && echo "deb [check-valid-until=no] http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list \
    && echo "deb [check-valid-until=no] http://archive.debian.org/debian buster-updates main" >> /etc/apt/sources.list \
    && echo 'Acquire::http::Timeout "10";' > /etc/apt/apt.conf.d/99timeout \
    && echo 'Acquire::Retries "3";' >> /etc/apt/apt.conf.d/99timeout \
    && echo 'Acquire::Check-Valid-Until "false";' >> /etc/apt/apt.conf.d/99timeout

# Установка системных зависимостей (одним RUN для минимизации слоев)
RUN apt-get update && apt-get install -y --no-install-recommends --allow-unauthenticated \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-mysql-client \
    locales \
    && echo "ru_RU.UTF-8 UTF-8" > /etc/locale.gen \
    && locale-gen \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Настройка локали и кодировки
ENV LANG=ru_RU.UTF-8 \
    LC_ALL=ru_RU.UTF-8 \
    LANGUAGE=ru_RU.UTF-8

# Настройка PHP: UTF-8 по умолчанию
RUN echo 'default_charset = "UTF-8"' > /usr/local/etc/php/conf.d/charset.ini \
    && echo 'mbstring.language = Russian' >> /usr/local/etc/php/conf.d/charset.ini \
    && echo 'mbstring.internal_encoding = UTF-8' >> /usr/local/etc/php/conf.d/charset.ini \
    && echo 'mbstring.http_output = UTF-8' >> /usr/local/etc/php/conf.d/charset.ini \
    && echo 'date.timezone = Europe/Moscow' >> /usr/local/etc/php/conf.d/charset.ini

# Composer (кешируется, если не менялся composer.lock)
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование composer-файлов ПЕРВЫМИ — это ключевая оптимизация для кеширования слоя vendor
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=php \
    && rm -rf /root/.composer/cache

# Копирование остального приложения (код, схема БД и т.д.)
COPY . .

# Права на загрузку и директории
RUN mkdir -p /var/www/html/uploads /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads /var/www/html/exports

# Apache: конфигурация для .htaccess и кодировки
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/vshpm.conf \
    && a2enconf vshpm \
    && echo 'AddDefaultCharset UTF-8' >> /etc/apache2/conf-enabled/charset.conf

EXPOSE 80

# CLI-утилита администрирования
COPY hspm-admin /var/www/html/hspm-admin
RUN chmod +x /var/www/html/hspm-admin

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
