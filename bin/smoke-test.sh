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
echo "── Scenario 3: Abilities API (WP 6.9+, skips on older) ────────"
# Reuses scenario 2's options, so the ability's payload is asserted against
# the same known state.
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities.php"

echo ""
echo "── Scenario 4: Abilities API in everywhere mode ───────────────"
# remove_everywhere ON with a deliberately *partial* stored post-type list, so
# the ability has to report the effective set rather than the saved selection.
# allowed_comment_types keeps WP 6.9+ notes working even in everywhere mode, so
# the payload has to disclose it rather than claiming an absolute blackout.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":true,\"disabled_post_types\":[\"post\"],\"allowed_comment_types\":[\"note\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-everywhere.php"

echo ""
echo "── Scenario 5: Abilities API for a role-exempt caller ─────────"
# remove_everywhere ON *and* administrators exempted by role: the exemption
# must be disclosed without rewriting the site configuration in the payload.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":true,\"disabled_post_types\":[\"post\"],\"enable_exclude_by_role\":true,\"exclude_by_role\":[\"administrator\"]}" \
	--format=json >/dev/null
# Leave a stale network-wide settings option behind, as a network deactivation
# would. It must be loaded at construct time for the scenario to be meaningful.
"$WP" option update disable_comments_sitewide_settings 1 >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-role-exempt.php"
"$WP" option delete disable_comments_sitewide_settings >/dev/null 2>&1 || true

echo ""
echo "── Scenario 6: endpoint blocking independent of post types ────"
# REST/XML-RPC blocking on, post types deliberately untouched: the payload must
# not read as "comments fully enabled" to a REST client about to get a 403.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[],\"remove_rest_API_comments\":1,\"remove_xmlrpc_comments\":1}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-endpoints.php"

echo ""
echo "── Scenario 7: every public type ticked is not "everywhere" ───"
# All public comment-capable types disabled, global toggle OFF. A non-public
# type registered by the scenario stays open, so the payload must not claim the
# whole site is closed.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[\"post\",\"page\",\"attachment\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-nonpublic.php"

echo ""
echo "── Scenario 8: post types that no longer exist ────────────────"
# A slug left behind by a deactivated CPT plugin. Reporting must drop it while
# the enforcement path keeps it — filtering there would leave comments open on
# every custom post type.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[\"post\",\"dc_gone_smoke\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-stale-type.php"

echo ""
echo "── Scenario 9: page caches purged on settings change ──────────"
# Runs last: it drives the real save and delete handlers, so it rewrites the
# options and removes comments rather than reading a fixed state.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-cache-purge.php"

echo ""
echo "ALL SMOKE TESTS PASSED"
