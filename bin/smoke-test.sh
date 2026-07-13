#!/usr/bin/env bash
#
# Runtime smoke tests for Disable Comments, run against the live WordPress
# sandbox created by bin/setup-wp-sandbox.sh (SQLite, no MySQL needed).
#
# Each scenario sets the plugin options via WP-CLI, then asserts real
# behaviour in a fresh WordPress process with `wp eval-file`.
#
# NOTE: this mutates the sandbox's disable_comments_options and leaves the
# last scenario's settings in place. The sandbox is disposable by design.
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP="$PLUGIN_DIR/bin/wp"

if ! "$WP" core is-installed >/dev/null 2>&1; then
	echo "Sandbox not ready — run bin/setup-wp-sandbox.sh first." >&2
	exit 1
fi

DBV="$("$WP" eval 'echo Disable_Comments::DB_VERSION;')"

echo "── Scenario 1: remove everywhere ──────────────────────────────"
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":true,\"disabled_post_types\":[\"post\",\"page\",\"attachment\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-remove-everywhere.php"

echo ""
echo "── Scenario 2: per post type (post only) ──────────────────────"
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[\"post\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-per-post-type.php"

echo ""
echo "ALL SMOKE TESTS PASSED"
