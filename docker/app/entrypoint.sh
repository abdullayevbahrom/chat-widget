#!/bin/bash
set -e

# Docker Secrets support: read secrets from /run/secrets/ and export as env vars
# This script must run as root to access secret files, then exec the target command

if [ -f /run/secrets/db_password ]; then
  export DB_PASSWORD="$(cat /run/secrets/db_password)"
fi

if [ -f /run/secrets/redis_password ]; then
  export REDIS_PASSWORD="$(cat /run/secrets/redis_password)"
fi

# Generate APP_KEY if not set (first-run setup)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
  echo "APP_KEY not set — generating..."
  php artisan key:generate --force --no-interaction
fi

# Execute the main container command (php-fpm)
exec "$@"
