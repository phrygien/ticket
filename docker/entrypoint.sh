#!/bin/sh
set -e

# Attendre que MySQL soit prêt (utile au premier démarrage)
until php artisan migrate:status > /dev/null 2>&1; do
  echo "En attente de la base de données..."
  sleep 2
done

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
