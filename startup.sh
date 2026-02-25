#!/usr/bin/env sh
set -e

mkdir -p /var/www/html/db
mkdir -p /var/www/html/public/assets/images/uploads/logos

chown -R www-data:www-data /var/www/html/db /var/www/html/public/assets/images/uploads/logos

php /var/www/html/public/endpoints/db/migrate.php || true

# Run exchange rate update once daily at 09:00 Asia/Shanghai.
DAILY_EXCHANGE_CRON="0 9 * * * php /var/www/html/cli/cron.php exchange -v >> /proc/1/fd/1 2>> /proc/1/fd/2"
if ! grep -Fq "$DAILY_EXCHANGE_CRON" /etc/crontabs/root; then
    printf "%s\n" "$DAILY_EXCHANGE_CRON" >> /etc/crontabs/root
fi

php-fpm -D
crond
nginx -g 'daemon off;'
