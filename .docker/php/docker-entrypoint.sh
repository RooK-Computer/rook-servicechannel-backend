#!/usr/bin/env bash

set -euo pipefail

runtime_dirs=(
  "/var/www/html/docroot/sites/default/files"
  "/var/www/html/private"
)

for dir in "${runtime_dirs[@]}"; do
  mkdir -p "$dir"
  chown -R www-data:www-data "$dir"
  chmod -R ug+rwX "$dir"
done

exec docker-php-entrypoint "$@"
