FROM php:8.4-cli

WORKDIR /var/www

# Instala as dependências de sistema essenciais para o Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala as extensões do PHP necessárias para o Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-interaction --optimize-autoloader

CMD php artisan serve --host=0.0.0.0 --port=8000