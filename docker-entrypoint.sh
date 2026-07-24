#!/usr/bin/env bash
#
# First-boot provisioning + server start for the Braven Lead Router demo.
# Installs WordPress on SQLite, activates the SQLite integration, Elementor, our
# plugin + child theme, then seeds pages/menu/videos/settings. Idempotent: on
# every later boot it detects an existing install and just starts the server.
#
set -euo pipefail
cd /var/www/html
export WP_CLI_ALLOW_ROOT=1

SITE_URL="${SITE_URL:-https://braven-demo.levelbrook.com}"
ADMIN_USER="${ADMIN_USER:-braven}"
ADMIN_PASS="${ADMIN_PASS:-changeme-please}"
ADMIN_EMAIL="${ADMIN_EMAIL:-team@levelbrook.com}"
export SITE_URL CRM_WEBHOOK_URL GTM_ID BOOKING_URL LEAD_MAGNET_URL GA4_MEASUREMENT_ID GA4_API_SECRET NOTIFY_EMAIL 2>/dev/null || true

mkdir -p /data/db

# 1) Ensure wp-config.php exists. It is regenerated deterministically (it points
#    at the persisted SQLite DB on /data), so a fresh image layer after a rebuild
#    reconnects to existing data instead of reinstalling.
if [ ! -f wp-config.php ]; then
		wp config create --dbname=braven --dbuser=root --dbpass= --skip-check --force --extra-php <<'PHP'
// Behind kamal-proxy (TLS terminated at the edge): trust the forwarded proto.
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}
define( 'WP_HOME', getenv( 'SITE_URL' ) ?: 'https://braven-demo.levelbrook.com' );
define( 'WP_SITEURL', getenv( 'SITE_URL' ) ?: 'https://braven-demo.levelbrook.com' );
// SQLite database lives on the mounted volume so it survives image rebuilds.
define( 'DB_DIR', '/data/db/' );
define( 'DB_FILE', 'braven.sqlite' );
define( 'FS_METHOD', 'direct' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
PHP
	fi

fi

# 2) Ensure the SQLite drop-in (db.php) is present — replace the two placeholders.
if [ ! -f wp-content/db.php ]; then
	cp -f wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php
	sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/var/www/html/wp-content/plugins/sqlite-database-integration#" wp-content/db.php
	sed -i "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" wp-content/db.php
fi

# 3) Install + seed only when the (persisted) database has no install yet.
if ! wp core is-installed >/dev/null 2>&1; then
	echo "[provision] first boot — installing WordPress on SQLite…"
	wp core install \
		--url="$SITE_URL" \
		--title="Braven Agency" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASS" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email

	wp plugin activate sqlite-database-integration || true
	wp plugin activate braven-lead-router
	wp plugin activate elementor || echo "[provision] Elementor activation skipped (widget code still ships)"
	wp theme activate braven-child || echo "[provision] child theme activation skipped"
	wp rewrite structure '/%postname%/' --hard || true

	wp eval-file /usr/local/bin/seed-pages.php || echo "[provision] seed step reported a non-fatal issue"
	wp rewrite flush --hard || true
	echo "[provision] done. wp-admin user: $ADMIN_USER  (${SITE_URL}/wp-admin/)"
else
	# Existing DB (e.g. after an image rebuild): make sure our code is active.
	echo "[provision] existing install — ensuring plugins/theme active."
	wp plugin activate sqlite-database-integration braven-lead-router >/dev/null 2>&1 || true
	wp plugin activate elementor >/dev/null 2>&1 || true
	wp theme activate braven-child >/dev/null 2>&1 || true
fi

exec frankenphp run --config /etc/caddy/Caddyfile
