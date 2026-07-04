# PUI-UBMA R13 Patrimoine — application container (php-fpm)
# Runs as a non-root user per Security.md §9.
FROM php:8.4-fpm-alpine

# PHP extensions required by Laravel 12 + Filament + pgsql/redis stack
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis zip intl bcmath gd exif opcache pcntl

# Composer available inside the container so nothing is installed on the host
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node + npm for Vite/Filament theme builds (dev-time; assets are prebuilt in prod images)
RUN apk add --no-cache nodejs npm

COPY conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

# Non-root runtime user (uid 1000). php-fpm listens on an unprivileged port,
# so master + workers can all run as this user; the pool user/group directives
# are commented out because they only apply when the master runs as root.
RUN addgroup -g 1000 app \
 && adduser -G app -u 1000 -D -h /home/app app \
 && sed -i 's/^user = .*/;user = app/; s/^group = .*/;group = app/' /usr/local/etc/php-fpm.d/www.conf \
 && mkdir -p /var/www/html \
 && chown -R app:app /var/www/html /home/app

USER app
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
