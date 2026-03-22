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

# Detect database state:
# - "Migration table not found" → fresh or corrupted DB → wipe and seed
# - Anything else               → established DB → run only new migrations
MIGRATE_STATUS=$(php artisan migrate:status --no-ansi 2>&1 || true)

if echo "$MIGRATE_STATUS" | grep -q "Migration table not found"; then
    echo "Fresh database detected — running migrate:fresh --seed"
    php artisan migrate:fresh --force --seed
else
    echo "Existing database detected — running migrate"
    php artisan migrate --force
fi

# Start PHP-FPM + Nginx via Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
