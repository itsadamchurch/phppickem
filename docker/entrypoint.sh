#!/usr/bin/env bash
set -euo pipefail

if [ -f /var/www/html/composer.json ] && [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --no-progress
fi

exec apache2-foreground
