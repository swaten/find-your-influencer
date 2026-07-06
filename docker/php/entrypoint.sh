#!/bin/sh
set -e

# vendor/ is now installed locally (Laravel runs through XAMPP, not this container) -
# just wait for it to exist so queue/scheduler don't crash on a fresh checkout
until [ -f vendor/autoload.php ]; do
  echo "waiting for vendor/autoload.php - run 'composer install' locally first"
  sleep 2
done

if [ ! -f .env ]; then
  cp .env.example .env
fi

exec "$@"
