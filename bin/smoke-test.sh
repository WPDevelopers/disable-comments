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
echo "── Scenario 12: conditional rules ─────────────────────────────"
# Nothing is disabled in the base configuration, so every closed comment in
# this scenario can only have come from the rules layer.
#
# The seed carries a placeholder rule naming a taxonomy that is not registered:
# it matches nothing, but it makes has_conditional_rules() true at construct
# time, which is what decides whether the per-post filters are registered at
# all. Seeding an empty rule list would leave them unregistered and the
# scenario would assert the logic while proving nothing about the wiring.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[],\"enable_conditional_rules\":true,\"auto_close_days\":0,\"conditional_rules\":[{\"type\":\"taxonomy\",\"action\":\"disable\",\"taxonomy\":\"dc_smoke_placeholder\",\"terms\":[1]}],\"settings_saved\":true}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-conditional-rules.php"

echo ""
echo "── Scenario 22: Site Health section ───────────────────────────"
# Reconfigures the plugin through the real save handler as it goes, so it is
# seeded with the base state and drives the rest itself.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[],\"settings_saved\":true}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-site-health.php"

echo ""
echo "── Scenario 13: blocked-attempt counting ──────────────────────"
# Comments disabled everywhere, so the render path takes its "disabled" branch
# on every call — which is exactly where a counter must NOT be wired.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":true,\"disabled_post_types\":[\"post\",\"page\",\"attachment\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-blocked-stats.php"

echo ""
echo "── Scenario 15: WooCommerce product reviews ───────────────────"
# Needs a real WooCommerce. CI runners do not have one, so this half skips
# loudly rather than silently passing — the absent half below always runs,
# and that is the half protecting every site that will never run a store.
#
# Seeded with a post type already disabled so init_wploaded_filters() registers
# the per-post comment filters at construct. The scenario cross-checks its
# reported review status against a real comments_open() call; without the
# filters registered that check passes vacuously and proves nothing.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[\"product\"]}" \
	--format=json >/dev/null
if "$WP" plugin is-installed woocommerce >/dev/null 2>&1; then
	"$WP" plugin activate woocommerce >/dev/null 2>&1
	"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-woocommerce-reviews.php"
	"$WP" plugin deactivate woocommerce >/dev/null 2>&1
else
	echo "  SKIP  WooCommerce is not installed in this sandbox"
	echo "        (install it with: bin/wp plugin install woocommerce)"
fi

echo ""
echo "── Scenario 16: every WooCommerce path with it uninstalled ────"
"$WP" plugin deactivate woocommerce >/dev/null 2>&1 || true
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-woocommerce-absent.php"

echo ""
echo "── Scenario 17: review prompt ─────────────────────────────────"
# Mostly asserts the prompt does NOT appear. See the file header for why.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-review-prompt.php"

echo ""
echo "── Scenario 19: theme conflict scanner ────────────────────────"
# The scanner makes the site request its own front end, so this scenario needs
# a live HTTP server. It is started here and stopped afterwards; if it cannot
# be reached the scenario skips its end-to-end half loudly and still asserts
# the verdict logic, which needs no HTTP.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[\"post\"]}" \
	--format=json >/dev/null

THEMES_DIR="$("$WP" eval 'echo get_theme_root();')"
cp -r "$PLUGIN_DIR/tests/themes/dc-correct" "$THEMES_DIR/" 2>/dev/null || true
cp -r "$PLUGIN_DIR/tests/themes/dc-broken" "$THEMES_DIR/" 2>/dev/null || true

SCAN_SERVER_PID=""
HOME_URL="$("$WP" option get home)"

# `set -e` is on. A bare `cmd && break` that fails is a statement returning
# non-zero and would abort the whole run, so every probe below stays inside an
# `if`, where a failure is just a condition.
dc_front_end_up() {
	curl -sf -o /dev/null --max-time 3 "$HOME_URL" >/dev/null 2>&1
}

if ! dc_front_end_up; then
	"$WP" server --host=127.0.0.1 --port=8888 >/tmp/dc-smoke-server.log 2>&1 &
	SCAN_SERVER_PID=$!
	# Courtesy wait, not a hard requirement: the scenario skips its end-to-end
	# half loudly if the server never comes up.
	for _ in 1 2 3 4 5 6 7 8 9 10; do
		if dc_front_end_up; then
			break
		fi
		sleep 1
	done
fi

"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-theme-scanner.php"

if [ -n "$SCAN_SERVER_PID" ]; then
	kill "$SCAN_SERVER_PID" 2>/dev/null || true
	wait "$SCAN_SERVER_PID" 2>/dev/null || true
fi

echo ""
echo "── Scenario 18: Abilities API write abilities ─────────────────"
# Mostly asserts refusal: who may call, the confirm flag, and the dry run.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-abilities-write.php"

echo ""
echo "── Scenario 14: settings export / import ──────────────────────"
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-settings-portability.php"

echo ""
echo "── Scenario 10: delete modes, dry-run preview and CSV export ──"
# Drives the real delete handler across every mode. Asserts the preview count
# equals what the delete then removes — the two share a query builder, and a
# drift between them is the bug this scenario exists to catch.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-delete-modes.php"

echo ""
echo "── Scenario 11: allowed comment types survive every delete ────"
# allowed_comment_types must be in place before the plugin constructs, so it is
# seeded here rather than inside the scenario.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[],\"allowed_comment_types\":[\"note\"]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-delete-allowed-types.php"

echo ""
echo "── Scenario 20: comment types closed while comments stay open ─"
# The blocklist has to be in place before the plugin constructs — its guards
# are registered at plugins_loaded from the stored option — so it is seeded
# here rather than inside the scenario.
#
# Nothing is disabled by post type on purpose: the assertion that matters is
# that comments_open() stays true on a product while reviews are closed, and a
# disabled post type would close it for an unrelated reason and hide a
# regression. WooCommerce is activated when the sandbox has it; the scenario
# announces the store-specific half as skipped when it does not.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[],\"blocked_comment_types\":[\"review\"]}" \
	--format=json >/dev/null
if "$WP" plugin is-installed woocommerce >/dev/null 2>&1; then
	"$WP" plugin activate woocommerce >/dev/null 2>&1
	# `set -e` would abort on a failing scenario and skip the deactivation
	# below, leaving WooCommerce active in the sandbox and breaking unrelated
	# scenarios on the next run. Capture the status, clean up, then fail.
	set +e
	"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-comment-type-blocklist.php"
	BLOCKLIST_STATUS=$?
	set -e
	"$WP" plugin deactivate woocommerce >/dev/null 2>&1
	[ "$BLOCKLIST_STATUS" -eq 0 ]
else
	"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-comment-type-blocklist.php"
fi

echo ""
echo "── Scenario 23: external services match the disclosure ────────"
# Intercepts every outbound request the opt-in usage tracker makes (nothing is
# really sent) and checks it against the opt-in notice and readme (#164).
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-external-services.php"

echo ""
echo "── Scenario 21: uninstall leaves nothing behind ───────────────"
# Runs last: it executes the real uninstall.php, which removes the settings
# every scenario above seeds.
"$WP" option update disable_comments_options \
	"{\"db_version\":$DBV,\"remove_everywhere\":false,\"disabled_post_types\":[]}" \
	--format=json >/dev/null
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-uninstall.php"

echo ""
echo "ALL SMOKE TESTS PASSED"
