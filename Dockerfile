# ─────────────────────────────────────────────
# Stage 3 : image finale (PHP-FPM + Nginx + Supervisor)
# ─────────────────────────────────────────────
FROM php:8.2-fpm
# Dépendances système + extensions PHP nécessaires à Laravel
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Augmente les limites d'upload PHP (photos smartphone souvent > 2 Mo)
RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/99-uploads.ini

WORKDIR /var/www/html
