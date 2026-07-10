# ─────────────────────────────────────────────
# Stage 1 : dépendances PHP (Composer)
# ─────────────────────────────────────────────
FROM php:8.2-fpm AS composer-builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ─────────────────────────────────────────────
# Stage 2 : build des assets front (Vite)
# ─────────────────────────────────────────────
FROM node:22-slim AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

# Récupère le code complet + vendor déjà installé (pour que Tailwind scanne vendor/)
COPY --from=composer-builder /app ./

RUN npm run build

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
WORKDIR /var/www/html

# Copie de l'app avec vendor déjà installé (depuis composer-builder)
COPY --from=composer-builder /app ./
# Copie des assets compilés (Vite), qui ont maintenant scanné vendor/ correctement
COPY --from=node-builder /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
