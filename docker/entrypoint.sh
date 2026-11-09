#!/bin/sh
set -eu

if [ "${1:-}" = init ]; then
    if [ ! -s /run/travelops/keys ]; then
        umask 077
        php -r 'printf("APP_KEY=base64:%s\nTRAVELOPS_WEBHOOK_SECRET=%s\n", base64_encode(random_bytes(32)), bin2hex(random_bytes(32)));' > /run/travelops/keys.tmp
        mv /run/travelops/keys.tmp /run/travelops/keys
    fi
    php artisan migrate --force
    php artisan db:seed --force
    exit
fi

exec "$@"
