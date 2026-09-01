#!/usr/bin/env bash
#
# Multisite smoke tests for Disable Comments.
#
# Builds (once) a separate WordPress sandbox, converts it to a network, creates
# two subsites and network-activates the plugin, then runs the multisite
# scenarios. Kept apart from bin/smoke-test.sh because converting to multisite
# is one-way — the single-site sandbox must stay single-site.
#
# The sandbox lives at ~/wp-sandbox-dc-multisite (override with WP_MS_DIR).
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export WP_SANDBOX_DIR="${WP_MS_DIR:-$HOME/wp-sandbox-dc-multisite}"
WP="$PLUGIN_DIR/bin/wp"

if ! "$WP" core is-installed >/dev/null 2>&1; then
	echo "── Building multisite sandbox at $WP_SANDBOX_DIR ──────────────"
	"$PLUGIN_DIR/bin/setup-wp-sandbox.sh" >/dev/null
fi

if ! "$WP" core is-installed --network >/dev/null 2>&1; then
	echo "── Converting to multisite ────────────────────────────────────"
	"$WP" core multisite-convert --title="DC Network" >/dev/null
fi

# Subsites the scenarios rely on. `site create` fails if the slug exists, which
# is fine on a re-run.
for slug in sub1 sub2; do
	"$WP" site create --slug="$slug" --title="Site $slug" >/dev/null 2>&1 || true
done

"$WP" plugin activate disable-comments --network >/dev/null 2>&1 || true

if [ "$("$WP" eval 'echo is_multisite() ? 1 : 0;')" != "1" ]; then
	echo "Sandbox is not multisite — cannot run these scenarios." >&2
	exit 1
fi

echo ""
echo "── Multisite: page cache purging across the network ───────────"
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-cache-purge-multisite.php"

echo ""
echo "── Multisite: settings export/import against network storage ──"
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-settings-portability-multisite.php"

echo ""
echo "── Multisite: per-site permissions on a network export ────────"
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-network-export-caps.php"

echo ""
echo "── Multisite: uninstall clears every subsite ──────────────────"
# Runs last: the per-site options live in each subsite's own options table, so
# this executes the real uninstall.php and checks every site it walked.
"$WP" eval-file "$PLUGIN_DIR/tests/smoke/scenario-uninstall.php"

echo ""
echo "ALL MULTISITE SMOKE TESTS PASSED"
