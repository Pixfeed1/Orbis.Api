#!/usr/bin/env bash
# Spins up the dockerized WordPress + WooCommerce environment, installs
# everything, activates the plugin and runs the smoke tests.
set -euo pipefail

cd "$(dirname "$0")/.."

WP="docker compose exec -T cli wp"

echo "==> Starting containers"
docker compose up -d

echo "==> Waiting for WordPress files"
for i in $(seq 1 60); do
  if docker compose exec -T cli test -f /var/www/html/wp-load.php 2>/dev/null; then break; fi
  sleep 2
done

echo "==> Installing WordPress"
if ! $WP core is-installed 2>/dev/null; then
  $WP core install \
    --url="http://localhost:8080" \
    --title="Colis Pro Dev" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
fi

echo "==> Installing WooCommerce"
if ! $WP plugin is-installed woocommerce 2>/dev/null; then
  if docker compose exec -T cli test -f /tests/woocommerce.zip; then
    # Offline environments: drop a woocommerce zip in ./tests/woocommerce.zip.
    $WP plugin install /tests/woocommerce.zip
  else
    $WP plugin install woocommerce
  fi
fi
$WP plugin activate woocommerce

echo "==> Activating Colisly Parcel Forwarding"
$WP plugin activate colisly

echo "==> Setting permalinks (My Account endpoints)"
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush --hard

echo "==> Running smoke tests"
$WP eval-file /tests/smoke-test.php

echo "==> Done. Admin: http://localhost:8080/wp-admin (admin/admin)"
