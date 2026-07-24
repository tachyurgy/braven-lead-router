# Braven Lead Router — self-contained WordPress demo (their exact stack).
# FrankenPHP (PHP 8.4) · WordPress + Elementor · SQLite (no MySQL) · Hello Elementor.
FROM dunglas/frankenphp:1-php8.4

# Tools + PHP extensions WordPress + the SQLite integration need.
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip curl ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions gd exif intl zip opcache pdo_sqlite sqlite3

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
COPY docs                         /var/www/html/docs
COPY Caddyfile                    /etc/caddy/Caddyfile
COPY docker-entrypoint.sh         /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
