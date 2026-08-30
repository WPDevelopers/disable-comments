#!/usr/bin/env bash
#
# Bootstrap a runnable WordPress environment for the Disable Comments plugin
# WITHOUT Docker or MySQL: WordPress core + the official SQLite database
# drop-in, served by PHP's built-in web server.
#
# Designed for Claude Code web sandboxes and any Linux box with PHP >= 7.4
# (pdo_sqlite required). Idempotent — safe to re-run; re-runs are fast.
#
# Usage:
#   bin/setup-wp-sandbox.sh            # set up (or update) the sandbox
#   bin/wp <command>                   # run WP-CLI against the sandbox
#   bin/wp server --port=8888          # serve the site at http://localhost:8888
#
# Environment overrides:
#   WP_SANDBOX_DIR   where to put the sandbox   (default: ~/wp-sandbox-disable-comments)
#   WP_VERSION       WordPress core version      (default: latest)
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SANDBOX="${WP_SANDBOX_DIR:-$HOME/wp-sandbox-disable-comments}"
WP_PATH="$SANDBOX/wordpress"
WPCLI="$SANDBOX/wp-cli.phar"
SITE_URL="http://localhost:8888"

log() { printf '\n==> %s\n' "$*"; }

mkdir -p "$SANDBOX"

# ── 1. WP-CLI ────────────────────────────────────────────────────────────────
if [ ! -x "$WPCLI" ]; then
	log "Downloading WP-CLI"
	curl -fsSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x "$WPCLI"
fi
wp() { php "$WPCLI" --path="$WP_PATH" --allow-root "$@"; }

# ── 2. WordPress core ────────────────────────────────────────────────────────
if [ ! -f "$WP_PATH/wp-load.php" ]; then
	log "Downloading WordPress core (${WP_VERSION:-latest})"
	mkdir -p "$WP_PATH"
	if [ -n "${WP_VERSION:-}" ]; then
		wp core download --version="$WP_VERSION" --force
	else
		wp core download --force
	fi
fi

# ── 3. SQLite database drop-in (replaces MySQL) ──────────────────────────────
SQLITE_PLUGIN_DIR="$WP_PATH/wp-content/plugins/sqlite-database-integration"
if [ ! -f "$WP_PATH/wp-content/db.php" ]; then
	log "Installing SQLite database drop-in"
	curl -fsSL -o "$SANDBOX/sqlite-integration.zip" \
		https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
	unzip -oq "$SANDBOX/sqlite-integration.zip" -d "$WP_PATH/wp-content/plugins/"
	# db.copy ships with placeholders that must point at the plugin location.
	sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SQLITE_PLUGIN_DIR#g" \
		-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
		"$SQLITE_PLUGIN_DIR/db.copy" > "$WP_PATH/wp-content/db.php"
fi

# ── 4. wp-config.php ─────────────────────────────────────────────────────────
if [ ! -f "$WP_PATH/wp-config.php" ]; then
	log "Creating wp-config.php"
	# DB credentials are dummies — the SQLite drop-in ignores them.
	wp config create --dbname=wordpress --dbuser=wp --dbpass=wp --dbhost=localhost \
		--skip-check --force
	wp config set WP_DEBUG true --raw
	wp config set WP_DEBUG_LOG true --raw
	wp config set WP_DEBUG_DISPLAY false --raw
	wp config set WP_ENVIRONMENT_TYPE development
fi

# ── 5. Install the site ──────────────────────────────────────────────────────
if ! wp core is-installed 2>/dev/null; then
	log "Installing WordPress site"
	wp core install \
		--url="$SITE_URL" \
		--title="Disable Comments Dev" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=dev@example.test \
		--skip-email
fi

# ── 6. Link and activate the plugin under development ────────────────────────
PLUGIN_LINK="$WP_PATH/wp-content/plugins/disable-comments"
if [ ! -e "$PLUGIN_LINK" ]; then
	log "Symlinking plugin into wp-content/plugins"
	ln -s "$PLUGIN_DIR" "$PLUGIN_LINK"
fi
if ! wp plugin is-active disable-comments 2>/dev/null; then
	log "Activating disable-comments"
	wp plugin activate disable-comments
fi

# ── 7. Seed content so comment behaviour is observable ───────────────────────
if [ "$(wp post list --post_type=post --format=count)" -lt 2 ]; then
	log "Seeding sample content"
	wp post create --post_title="Sandbox test post" --post_status=publish --porcelain >/dev/null
	wp post create --post_type=page --post_title="Sandbox test page" --post_status=publish --porcelain >/dev/null
fi

# ── 8. Smoke check ───────────────────────────────────────────────────────────
log "Smoke check"
wp eval '
$ok = class_exists("Disable_Comments");
echo "Disable_Comments class loaded: " . ($ok ? "yes" : "NO") . "\n";
if ($ok) { echo "DB_VERSION: " . Disable_Comments::DB_VERSION . "\n"; }
' || { echo "SMOKE CHECK FAILED"; exit 1; }

log "Sandbox ready at $SANDBOX"
cat <<EOF

  WordPress : $WP_PATH
  Site URL  : $SITE_URL   (admin / password)
  WP-CLI    : bin/wp <command>          e.g.  bin/wp plugin list
  Serve     : bin/wp server --host=127.0.0.1 --port=8888
  Smoke test: bin/smoke-test.sh

EOF
