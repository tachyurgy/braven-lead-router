<?php
/**
 * Container liveness probe.
 *
 * Deliberately independent of WordPress and the SQLite database: this file is
 * executed by the FrankenPHP worker without loading wp-load.php, so a green
 * check means "Caddy is routing AND PHP is executing" and nothing more. That is
 * exactly the scope a container healthcheck should own — application-level
 * failures belong in monitoring, not in a restart trigger.
 *
 * Note this is NOT the kamal-proxy check. The proxy uses the static /up route
 * in the Caddyfile, which must stay dependency-free because the proxy probes it
 * with "Host: <container-id>".
 */

header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );
echo 'ok';
