#!/usr/bin/env bash
set -euo pipefail

if [ -f /var/www/html/composer.json ] && [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --no-progress
fi

db_host="${DB_HOSTNAME:-db}"
db_user="${DB_USERNAME:-nflpickem}"
db_pass="${DB_PASSWORD:-nflpickem}"
db_name="${DB_DATABASE:-nflpickem}"
db_port="${DB_PORT:-3306}"
db_prefix="${DB_PREFIX:-nflp_}"

# Best-effort cleanup of install SQL once DB is initialized.
if [ -f /var/www/html/install/install.sql ]; then
  for _ in $(seq 1 30); do
    php -r "mysqli_report(MYSQLI_REPORT_OFF); \$m=@new mysqli('$db_host','$db_user','$db_pass','$db_name',(int)'$db_port'); if (\$m->connect_errno) { exit(1);} \$res=\$m->query(\"select 1 from ${db_prefix}users limit 1\"); exit(\$res?0:1);" \
      && rm -f /var/www/html/install/install.sql \
      && echo "Install SQL removed after successful DB check." \
      && break
    sleep 2
  done
fi

if [ "${AUTO_REMOVE_INSTALL:-1}" = "1" ] && [ -d /var/www/html/install ] && [ ! -d /var/www/html/.git ]; then
  rm -rf /var/www/html/install
  echo "Install folder removed after successful DB check."
fi

exec apache2-foreground
