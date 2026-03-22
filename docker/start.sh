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

# Run migrations. If MySQL rejects a CREATE TABLE because the table already
# exists (orphaned from a previous crashed deploy), wipe and start clean.
MIGRATE_OUT=$(php artisan migrate --force 2>&1) && MIGRATE_OK=1 || MIGRATE_OK=0

if [ "$MIGRATE_OK" = "0" ]; then
    if echo "$MIGRATE_OUT" | grep -q "Base table or view already exists"; then
        echo "Orphaned tables detected — running migrate:fresh --seed"
        php artisan migrate:fresh --force --seed
    else
        # Unrelated migration error — surface it and abort
        echo "$MIGRATE_OUT"
        exit 1
    fi
fi

# Seed reference data (uses firstOrCreate — safe to run on every deploy)
php artisan db:seed --class=ExpenseCategorySeeder --force
php artisan db:seed --class=BranchSeeder --force
php artisan db:seed --class=RoleSeeder --force

# Start PHP-FPM + Nginx via Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
