# PANITIA HUB RT03: demo-only Render Free web service.
# This image intentionally does NOT host persistent user/finance data.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM dunglas/frankenphp:1-php8.3-bookworm AS application
RUN install-php-extensions mbstring pdo_sqlite sqlite3 pdo_mysql zip gd intl opcache
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . .
COPY --from=frontend /app/public/build /app/public/build

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && printf "upload_max_filesize=12M\npost_max_size=16M\nmemory_limit=256M\nexpose_php=Off\n" > "$PHP_INI_DIR/conf.d/zz-rt03.ini" \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /config /data

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/render-start.sh /usr/local/bin/rt03-render-start
RUN chmod 755 /usr/local/bin/rt03-render-start
USER www-data
EXPOSE 10000
CMD ["/bin/sh", "/usr/local/bin/rt03-render-start"]
