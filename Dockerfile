# ─────────────────────────────────────────────
# Stage 1 : build des assets front (Vite)
# ─────────────────────────────────────────────
FROM node:22-slim AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# ─────────────────────────────────────────────
# Stage 2 : dépendances PHP (Composer)
# ─────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.2 AS composer-builder

# Récupère le binaire composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer a besoin de unzip (ou de l'extension zip) et git pour certains packages
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
# Stage 3 : image finale (FrankenPHP)
# ─────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.2

# Dépendances système + extensions PHP nécessaires à Laravel
RUN apt-get update && apt-get install -y \
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
    && install-php-extensions \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copie de l'app avec vendor déjà installé
COPY --from=composer-builder /app ./
# Copie des assets compilés (Vite)
COPY --from=node-builder /app/public/build ./public/build

# Config Caddy (FrankenPHP)
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

ENV SERVER_NAME=":80"
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
