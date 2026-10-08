# Образ для деплоя на Railway (Laravel + SQLite, без Node/Vite)
FROM php:8.2-cli

# Системные библиотеки и PHP-расширения, нужные Laravel и SQLite
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libsqlite3-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite mbstring bcmath dom curl \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Зависимости без dev и оптимизированный автозагрузчик
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

EXPOSE 8080

# Миграции при каждом старте (идемпотентно), сидинг только на пустой БД, затем встроенный сервер
CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
