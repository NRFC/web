# syntax=docker/dockerfile:1
#
# NRFC production image.
#
# WordPress core, the NRFC theme/plugins and production PHP/Apache config are
# baked into the image. Build from the repository root:
#
#   docker build -f docker/production/Dockerfile -t ghcr.io/nrfc/wp-prod .
#
# Runtime expectations:
#   * DB connection supplied via WORDPRESS_DB_HOST / _USER / _PASSWORD / _NAME
#     (wp-config.php is generated from these by the upstream entrypoint).
#   * Uploads mounted at /var/www/nrfc/wp-content/uploads (writable by www-data).

# Build the Composer autoloaders (no dev dependencies) for the NRFC plugins.
FROM composer:2 AS plugins
WORKDIR /build
COPY wordpress/wp-content/plugins/ ./
RUN set -eux; \
    for dir in nrfc-*/; do \
        if [ -f "${dir}composer.json" ]; then \
            composer install --working-dir="$dir" --no-dev --no-interaction --no-progress \
                --prefer-dist --classmap-authoritative --ignore-platform-reqs; \
        fi; \
        rm -rf "${dir}tests" "${dir}composer.json" "${dir}composer.lock" "${dir}"phpunit.xml*; \
    done

FROM wordpress:7.1.2-php8.4-apache

# Serve the site from a directory that is NOT the upstream VOLUME
# (/var/www/html). Code baked into the image is therefore always what runs,
# rather than a stale copy left behind in an old anonymous volume.
ENV APACHE_DOCUMENT_ROOT=/var/www/nrfc
WORKDIR /var/www/nrfc

RUN set -eux; \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf; \
    a2enmod headers rewrite expires

COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-nrfc-production.ini
COPY docker/production/apache-security.conf /etc/apache2/conf-available/zz-nrfc-security.conf
RUN a2enconf zz-nrfc-security

# WordPress core from the upstream image, plus production hardening appended
# to the docker wp-config template (overridable via WORDPRESS_CONFIG_EXTRA).
RUN set -eux; \
    cp -a /usr/src/wordpress/. /var/www/nrfc/; \
    rm -rf /var/www/nrfc/wp-content/plugins/akismet /var/www/nrfc/wp-content/plugins/hello.php; \
    sed -i "/That's all, stop editing/i \
if (!defined('DISALLOW_FILE_MODS')) { define('DISALLOW_FILE_MODS', true); }\n\
if (!defined('AUTOMATIC_UPDATER_DISABLED')) { define('AUTOMATIC_UPDATER_DISABLED', true); }\n\
if (!defined('WP_ENVIRONMENT_TYPE')) { define('WP_ENVIRONMENT_TYPE', getenv_docker('WP_ENVIRONMENT_TYPE', 'production')); }\n" \
        /var/www/nrfc/wp-config-docker.php

# Project code: themes, plugins and must-use plugins.
COPY wordpress/wp-content/mu-plugins/ /var/www/nrfc/wp-content/mu-plugins/
COPY --from=plugins /build/ /var/www/nrfc/wp-content/plugins/
COPY wordpress/wp-content/themes/ /var/www/nrfc/wp-content/themes/

# Code is root-owned and read-only to Apache. Only the docroot (for the
# generated wp-config.php) and uploads are owned by www-data.
RUN set -eux; \
    chown -R root:root /var/www/nrfc; \
    chmod -R u=rwX,go=rX /var/www/nrfc; \
    mkdir -p /var/www/nrfc/wp-content/uploads; \
    chown www-data:www-data /var/www/nrfc /var/www/nrfc/wp-content/uploads

VOLUME /var/www/nrfc/wp-content/uploads

COPY --chmod=755 docker/production/nrfc-entrypoint.sh /usr/local/bin/nrfc-entrypoint.sh
ENTRYPOINT ["nrfc-entrypoint.sh"]
CMD ["apache2-foreground"]

# version.php executes PHP without touching the database, so this checks
# Apache + PHP health independently of MariaDB availability.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/wp-includes/version.php || exit 1

EXPOSE 80
