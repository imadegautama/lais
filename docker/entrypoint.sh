#!/bin/bash
set -e

cd /var/www

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diset di environment Coolify."
    exit 1
fi

wait_for_mysql() {
    echo "Menunggu MySQL ${DB_HOST}:${DB_PORT:-3306}..."

    for attempt in $(seq 1 30); do
        if php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; then
            echo "MySQL siap."
            return 0
        fi
        echo "   Percobaan $attempt/30 gagal, menunggu..."
        sleep 2
    done

    echo "MySQL tidak siap setelah 30 percobaan."
    exit 1
}

mkdir -p storage/logs storage/app/private storage/app/public storage/framework/{sessions,views,cache/data} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    wait_for_mysql
fi

echo "Migrasi database (${DB_DATABASE})..."
php artisan migrate --force

echo "Cache config, route, view, event..."
php artisan optimize

# Artisan above ran as root; hand storage back to www-data.
chown -R www-data:www-data storage bootstrap/cache

echo "Menjalankan supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
