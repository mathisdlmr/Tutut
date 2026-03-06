#!/bin/sh
set -eu

until php -r "
  \$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
  );
  echo 'MySQL prêt.' . PHP_EOL;
" 2>/dev/null; do
  echo "MySQL indisponible - nouvelle tentative dans 5s..."
  sleep 5
done

php artisan config:clear || true
php artisan cache:clear  || true
php artisan route:clear  || true
php artisan view:clear   || true
php artisan event:clear  || true

php artisan config:cache --no-interaction
php artisan route:cache  --no-interaction
php artisan view:cache   --no-interaction
php artisan event:cache  --no-interaction
php artisan optimize     --no-interaction

php artisan storage:link

mkdir -p /var/www/html/storage/app/public/documents
mkdir -p /var/www/html/storage/logs

php artisan migrate --force

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
