#!/bin/sh
set -e
mkdir -p /var/www/data
chown -R www-data:www-data /var/www/data
exec docker-php-entrypoint "$@"
