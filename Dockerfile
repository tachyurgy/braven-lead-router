# Braven Lead Router — self-contained WordPress demo (their exact stack).
# FrankenPHP (PHP 8.4) · WordPress + Elementor · SQLite (no MySQL) · Hello Elementor.
FROM dunglas/frankenphp:1-php8.4

# Tools + PHP extensions WordPress + the SQLite integration need.
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip curl ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions gd exif intl zip opcache pdo_sqlite sqlite3

# Raise PHP memory (base image ships 128M; wp-cli extraction + Elementor need more).
RUN printf "memory_limit=512M\n" > "$PHP_INI_DIR/conf.d/zz-memory.ini"

# WP-CLI for headless provisioning.
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
 && chmod +x /usr/local/bin/wp
ENV WP_CLI_ALLOW_ROOT=1

WORKDIR /var/www/html

# WordPress core + SQLite integration + Elementor + Hello Elementor parent theme.
RUN wp core download --path=/var/www/html \
 && curl -fsSL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o /tmp/sqlite.zip \
 && curl -fsSL https://downloads.wordpress.org/plugin/elementor.latest-stable.zip -o /tmp/elementor.zip \
 && curl -fsSL https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip -o /tmp/hello.zip \
 && unzip -q /tmp/sqlite.zip -d wp-content/plugins/ \
 && unzip -q /tmp/elementor.zip -d wp-content/plugins/ \
 && unzip -q /tmp/hello.zip -d wp-content/themes/ \
 && rm -f /tmp/*.zip

# Our code: the plugin (the actual IP), the brand child theme, the demo mock-CRM.
COPY wp-plugin/braven-lead-router wp-content/plugins/braven-lead-router
COPY wp-theme/braven-child        wp-content/themes/braven-child
RUN mkdir -p wp-content/mu-plugins
COPY provision/mock-crm.php       wp-content/mu-plugins/mock-crm.php
COPY provision/seed-pages.php     /usr/local/bin/seed-pages.php
COPY provision/healthz.php        healthz.php
COPY docs                         /var/www/html/docs
COPY Caddyfile                    /etc/caddy/Caddyfile
COPY docker-entrypoint.sh         /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

# Override the FrankenPHP base image's healthcheck. The base probes Caddy's admin
# API on :2019, but our Caddyfile sets `admin off`, so the inherited check can
# never pass and the container reports unhealthy forever while serving fine.
# Probe our own PHP-backed endpoint instead: it proves Caddy routes AND the PHP
# worker executes. start-period covers first-boot WP-CLI provisioning.
HEALTHCHECK --interval=30s --timeout=5s --start-period=180s --retries=3 \
	CMD curl -fsS -o /dev/null http://localhost:80/healthz.php || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
