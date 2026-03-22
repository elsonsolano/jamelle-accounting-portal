#!/bin/sh
set -e

# Railway injects $PORT; default to 80 for local runs
PORT=${PORT:-80}

# Patch nginx to listen on the correct port
sed -i "s/listen 80 default_server/listen ${PORT} default_server/" /etc/nginx/nginx.conf

# Cache config, routes, and views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Start PHP-FPM + Nginx via Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
