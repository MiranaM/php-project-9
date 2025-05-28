FROM php:8.1-cli

# Установка зависимостей PHP
RUN apt-get update && apt-get install -y libzip-dev libpq-dev
RUN docker-php-ext-install zip pdo pdo_pgsql

# Установка Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# Разрешаем composer работать от root
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY . .

# Устанавливаем PHP-зависимости
RUN composer install --no-interaction --prefer-dist --ignore-platform-reqs

# Запуск проекта через make start
CMD ["make", "start"]
