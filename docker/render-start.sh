#!/bin/sh
set -eu

# Render Free has no persistent disk. Require an explicit preview flag and
# a fixed disposable SQLite location so real committee data cannot be
# silently stored on a filesystem that disappears after spin-down.
if [ "${RT03_DEMO_MODE:-}" != "ephemeral" ] || [ "${DB_CONNECTION:-}" != "sqlite" ] || [ "${DB_DATABASE:-}" != "/tmp/rt03-preview.sqlite" ]; then
    echo >&2 "PANITIA HUB refused to start: Render Free preview requires RT03_DEMO_MODE=ephemeral and disposable SQLite at /tmp/rt03-preview.sqlite."
    exit 1
fi
if [ -z "${APP_KEY:-}" ] || [ "${APP_DEBUG:-}" != "false" ]; then
    echo >&2 "PANITIA HUB refused to start: APP_KEY is required and APP_DEBUG must be false."
    exit 1
fi

touch /tmp/rt03-preview.sqlite
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

echo "RT03 preview is running with RESETTABLE sample structure only. Never enter real resident or finance data."
exec frankenphp run --config /etc/caddy/Caddyfile
