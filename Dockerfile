FROM php:8.1-cli

# Устанавливаем зависимости и Composer
RUN apt-get update && apt-get install -y unzip curl git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Установка рабочей директории
WORKDIR /app

# Копируем всё в контейнер
COPY . .

# Устанавливаем зависимости
RUN composer install

# Порт, на котором работает приложение
EXPOSE 8000

# Команда запуска (тот же Makefile start)
CMD ["make", "start"]
