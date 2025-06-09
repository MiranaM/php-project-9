FROM php:8.2-cli

# Установка зависимостей системы + поддержка PostgreSQL
RUN apt-get update && apt-get install -y \
    git unzip zip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Копируем только composer.json и composer.lock для более быстрой сборки
COPY composer.json composer.lock* ./

# Установка composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# Установка зависимостей
RUN composer install --no-interaction --prefer-dist --ignore-platform-reqs --no-dev=false

# Копируем оставшиеся файлы проекта
COPY . .

# Команда запуска
CMD ["make", "start"]
