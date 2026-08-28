#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/app/public/documents
mkdir -p /var/www/html/storage/app/private

php artisan config:cache --no-interaction
php artisan route:cache  --no-interaction
php artisan view:cache   --no-interaction
php artisan event:cache  --no-interaction
php artisan optimize     --no-interaction

php artisan storage:link

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
