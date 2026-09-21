FROM dunglas/frankenphp:latest-php8.3-alpine

# Install pdo_mysql and other extensions
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p /app/uploads/receipts && chmod -R 777 /app/uploads
