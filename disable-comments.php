<?php

/**
 * Plugin Name: Disable Comments
 * Plugin URI: https://wordpress.org/plugins/disable-comments/
 * Description: Allows administrators to globally disable comments on their site. Comments can be disabled according to post type. You could bulk delete comments using Tools.
 * Version: 2.9.0
 * Author: WPDeveloper
 * Author URI: https://wpdeveloper.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: disable-comments
 * Domain Path: /languages/
 *
 * @package Disable_Comments
 */

if (!defined('ABSPATH')) {
	exit;
}

class Disable_Comments {
	const DB_VERSION         = 8;
	const BLOCKED_STATS_OPTION = 'disable_comments_blocked_stats';
	/** Option recording when counting started. */
	const BLOCKED_SINCE_OPTION = 'disable_comments_blocked_since';
	/**
	 * Per-user meta recording that the review prompt was dismissed.
	 *
	 * User meta rather than an option: dismissal belongs to the person who
	 * dismissed it, not to the site. It also survives plugin updates, which
	 * a version-stamped option would not.
	 */
	const REVIEW_DISMISSED_META = 'disable_comments_review_dismissed';
	/** Option holding the moment a bulk delete succeeded. */
	const REVIEW_TRIGGER_OPTION = 'disable_comments_review_trigger';
	/**
	 * Terms the conditional-rules picker asks for at a time.
	 *
	 * A page size, not a limit: the picker searches and pages, so a taxonomy
	 * larger than this is still reachable in full. Sized to fill a dropdown
	 * without making the first keystroke wait on a query that reads thousands
	 * of rows.
	 */
	const TERM_PAGE_SIZE = 50;
	/**
	 * How many matching comments the delete preview shows.
	 *
	 * Enough to recognise what the delete is about to take, not so many that
	 * the panel becomes the comments screen. The full set is what the CSV
	 * backup beside the button is for.
	 */
	const DELETE_PREVIEW_SAMPLE_SIZE = 10;
	/** Transient holding the current scan's one-time token. */
	const SCAN_TOKEN_TRANSIENT = 'disable_comments_scan_token';
	/** Query arg carrying that token on the scanned request. */
	const SCAN_QUERY_ARG       = 'dc_scan';
	/**
	 * Format version of the settings export payload.
	 *
	 * Bumped when the shape changes, so a future release can migrate an old
	 * file rather than silently misapplying it.
	 */
	const EXPORT_SCHEMA_VERSION = 1;
	private static $instance = null;
	private $options;
	public  $networkactive;
	public  $tracker;
	public  $is_CLI;
	public  $sitewide_settings;
	public  $setup_notice_flag;
	private $modified_types = array();
	/**
	 * Blocked attempts seen in this request, flushed once on shutdown.
	 *
	 * Incrementing an option per blocked request would put a write on the
	 * exact path a spam flood hammers, so counting stays in memory until the
	 * request is over.
	 *
	 * @var array
	 */
	private $blocked_pending = array();
	/**
	 * Comments removed by the last delete run in this request.
	 *
	 * apply_delete_comments() already works this out to decide whether the
	 * run earned a review prompt; it is kept so the AJAX response can report
	 * it, because the log string it returns is prose and cannot be counted on.
	 *
	 * @var int
	 */
	private $last_deleted_count = 0;
	/** Findings accumulated during a scanned front-end request. */
	private $scan_report = array();

	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	function __construct() {
		define('DC_VERSION', '2.9.0');
		define('DC_PLUGIN_SLUG', 'disable_comments_settings');
		define('DC_PLUGIN_ROOT_PATH', dirname(__FILE__));
		define('DC_PLUGIN_VIEWS_PATH', DC_PLUGIN_ROOT_PATH . '/views/');
		define('DC_PLUGIN_ROOT_URI', plugins_url("/", __FILE__));
		define('DC_ASSETS_URI', DC_PLUGIN_ROOT_URI . 'assets/');

		// save settings
		add_action('wp_ajax_disable_comments_save_settings', array($this, 'disable_comments_settings'));
		add_action('wp_ajax_disable_comments_delete_comments', array($this, 'delete_comments_settings'));
		add_action('wp_ajax_disable_comments_preview_delete', array($this, 'preview_delete_comments'));
		add_action('wp_ajax_disable_comments_export_comments', array($this, 'export_comments_download'));
		add_action('wp_ajax_get_sub_sites', array($this, 'get_sub_sites'));
		add_action('wp_ajax_disable_comments_get_terms', array($this, 'get_taxonomy_terms'));
		add_action('wp_ajax_disable_comments_reset_blocked_stats', array($this, 'reset_blocked_stats_ajax'));
		add_action('wp_ajax_disable_comments_dismiss_review', array($this, 'dismiss_review_prompt'));
		add_action('wp_ajax_disable_comments_scan_theme', array($this, 'scan_theme_conflict'));
		add_action('wp_ajax_disable_comments_export_settings', array($this, 'export_settings_download'));
		add_action('wp_ajax_disable_comments_import_settings', array($this, 'import_settings_ajax'));

		// Including cli.php
		if (defined('WP_CLI') && WP_CLI) {
			add_action('init', array($this, 'enable_cli'), 9999);
		}

		// Expose plugin state to the Abilities API (WordPress 6.9+), so AI
		// agents and MCP clients can query how comments are configured.
		add_action('wp_abilities_api_categories_init', array($this, 'register_ability_categories'));
		add_action('wp_abilities_api_init', array($this, 'register_abilities'));

		// are we network activated?
		$this->networkactive = (is_multisite() && array_key_exists(plugin_basename(__FILE__), (array) get_site_option('active_sitewide_plugins')));
		$this->is_CLI = defined('WP_CLI') && WP_CLI;

		$this->sitewide_settings = get_site_option('disable_comments_sitewide_settings', false);
		// Load options.
		// Uses is_network_admin_ajax_context() (routing hint, not capability
		// check) because current_user_can() is unavailable during plugin
		// construction — pluggable.php hasn't loaded yet. This only controls
		// which options table is READ (site vs blog) — writes are always
		// gated by capability checks in the AJAX handlers and settings_page().
		if ($this->networkactive && ($this->is_network_admin_ajax_context() || $this->sitewide_settings !== '1')) {
			$this->options = get_site_option('disable_comments_options', array());
			$this->options['disabled_sites'] = $this->get_disabled_sites();

			$blog_id = get_current_blog_id();
			if (
				!$this->is_network_admin_ajax_context() && (
					empty($this->options['disabled_sites']) ||
					// if site disabled
					empty($this->options['disabled_sites']["site_$blog_id"])
				)
			) {
				$this->options = [
					'remove_everywhere' => false,
					'disabled_post_types' => array(),
					'extra_post_types' => array(),
					'disabled_sites' => array(),
					'remove_xmlrpc_comments' => 0,
					'remove_rest_API_comments' => 0,
					'show_existing_comments' => false,
					'allowed_comment_types' => array(),
					'blocked_comment_types' => array(),
					'settings_saved' => true,
					'db_version' => $this->options['db_version']
				];
			}
		} else {
			$this->options = get_option('disable_comments_options', array());
			$not_configured = empty($this->options) || empty($this->options['settings_saved']);

			if (is_multisite() && $not_configured && $this->sitewide_settings == '1') {
				$this->options = get_site_option('disable_comments_options', array());
				$this->options['is_network_options'] = true;
			}
		}


		// If it looks like first run, check compat.
		if (empty($this->options)) {
			$this->check_compatibility();
		}

		$this->options['sitewide_settings'] = ($this->sitewide_settings == '1');

		// Upgrade DB if necessary.
		$this->check_db_upgrades();
		$this->check_upgrades();

		add_action('plugins_loaded', [$this, 'init_filters']);
		add_action('wp_loaded', [$this, 'start_plugin_usage_tracking']);

		// Add Site Health integration
		add_filter('debug_information', array($this, 'add_site_health_info'));
	}

	/**
	 * Routing hint: is this request from the network-admin screen?
	 *
	 * During AJAX, WP's is_network_admin() is always false, so the JS
	 * appends ?is_network_admin=1 to ajaxurl (value set server-side in
	 * admin_enqueue_scripts via is_network_admin()). The GET param is
	 * client-supplied and therefore forgeable — never use this method
	 * alone for authorization. Always pair with can_network_admin_ajax_context()
	 * or an explicit current_user_can() check.
	 */
	private function is_network_admin_ajax_context() {
		if (!$this->networkactive) {
			return false;
		}
		if (is_network_admin()) {
			return true;
		}
		if (defined('DOING_AJAX') && DOING_AJAX && is_multisite() && isset($_GET['is_network_admin'])) {
			$param = sanitize_text_field(wp_unslash($_GET['is_network_admin']));
			return $param === '1';
		}
		return false;
	}

	/**
	 * Capability-gated network-admin context check.
	 *
	 * Returns true only when the request appears to come from the
	 * network-admin screen AND the current user holds
	 * manage_network_plugins. Safe for authorization decisions.
	 */
	private function can_network_admin_ajax_context() {
		if ($this->is_network_admin_ajax_context() && current_user_can('manage_network_plugins')) {
			return true;
		}

		return false;
	}

	/**
	 * Enable CLI
	 * @since 2.0.0
	 */
	public function enable_cli() {
		require_once DC_PLUGIN_ROOT_PATH . "/includes/cli.php";
		new Disable_Comment_Command($this);
	}

	/**
	 * Load the Abilities API integration.
	 *
	 * Guarded on the API being present so nothing changes on WordPress < 6.9,
	 * where these hooks never fire anyway.
	 *
	 * @since 2.8.0
	 * @return bool True when the integration is available and loaded.
	 */
	private function load_abilities() {
		if (!function_exists('wp_register_ability') || !function_exists('wp_register_ability_category')) {
			return false;
		}
		require_once DC_PLUGIN_ROOT_PATH . '/includes/abilities.php';
		return true;
	}

	/**
	 * Register the plugin's ability category with the Abilities API.
	 *
	 * @since 2.8.0
	 * @return void
	 */
	public function register_ability_categories() {
		if ($this->load_abilities()) {
			disable_comments_register_ability_categories();
		}
	}

	/**
	 * Register the plugin's abilities with the Abilities API.
	 *
	 * @since 2.8.0
	 * @return void
	 */
	public function register_abilities() {
		if ($this->load_abilities()) {
			disable_comments_register_abilities();
		}
	}

	public function admin_notice() {
		if ($this->tracker instanceof DisableComments_Plugin_Tracker) {
			if (isset($this->setup_notice_flag) && $this->setup_notice_flag === true) {
				return;
			}
			$current_screen = get_current_screen()->id;
			$has_caps = $this->networkactive && is_network_admin() ? current_user_can('manage_network_plugins') : current_user_can('manage_options');
			// if( ! in_array( $current_screen, ['settings_page_disable_comments_settings', 'settings_page_disable_comments_settings-network']) && $has_caps ) {
			if ($has_caps && in_array($current_screen, ['dashboard-network', 'dashboard'])) {
				$this->tracker->notice();
			}
		}
	}

	public function start_plugin_usage_tracking() {
		if ($this->networkactive && !$this->options['sitewide_settings']) {
			$this->tracker = null;
			return;
		}
		if (!class_exists('DisableComments_Plugin_Tracker')) {
			include_once(DC_PLUGIN_ROOT_PATH . '/includes/class-plugin-usage-tracker.php');
		}
		$tracker = $this->tracker = DisableComments_Plugin_Tracker::get_instance(__FILE__, [
			'opt_in' => true,
			'goodbye_form' => true,
			'item_id' => 'b0112c9030af6ba53de4'
		]);
		$tracker->set_notice_options(array(
			'notice' => __('Want to help make Disable Comments even better?', 'disable-comments'),
			'extra_notice' => __('We collect non-sensitive diagnostic data and plugin usage information. Your site URL, WordPress & PHP version, plugins & themes and email address to send you the discount coupon. This data lets us make sure this plugin always stays compatible with the most popular plugins and themes. No spam, I promise.', 'disable-comments'),
		));
		$tracker->init();
	}

	private function check_compatibility() {
		if (version_compare($GLOBALS['wp_version'], '4.7', '<')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			deactivate_plugins(__FILE__);

			// @phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['action']) && ($_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape')) {
				// translators: %s: WordPress version no.
				exit(sprintf(esc_html__('Disable Comments requires WordPress version %s or greater.', 'disable-comments'), '4.7'));
			}
		}
	}

	private function check_db_upgrades() {
		$old_ver = isset($this->options['db_version']) ? $this->options['db_version'] : 0;
		if ($old_ver < self::DB_VERSION) {
			if ($old_ver < 2) {
				// upgrade options from version 0.2.1 or earlier to 0.3.
				$this->options['disabled_post_types'] = get_option('disable_comments_post_types', array());
				delete_option('disable_comments_post_types');
			}
			if ($old_ver < 5) {
				// simple is beautiful - remove multiple settings in favour of one.
				$this->options['remove_everywhere'] = isset($this->options['remove_admin_menu_comments']) ? $this->options['remove_admin_menu_comments'] : false;
				foreach (array('remove_admin_menu_comments', 'remove_admin_bar_comments', 'remove_recent_comments', 'remove_discussion', 'remove_rc_widget') as $v) {
					unset($this->options[$v]);
				}
			}
			if ($old_ver < 7 && function_exists('get_sites')) {
				$this->options['disabled_sites'] = [];
				$dc_options = get_site_option('disable_comments_options', array());

				foreach (get_sites(['number' => 0, 'fields' => 'ids']) as $blog_id) {
					if (isset($dc_options['disabled_sites'])) {
						$this->options['disabled_sites']["site_$blog_id"] = in_array($blog_id, $dc_options['disabled_sites']);
					} else {
						$this->options['disabled_sites']["site_$blog_id"] = true;
					}
				}
				$this->options['disabled_sites'] = $this->get_disabled_sites();
			}

			if ($old_ver < 8) {
				// Add new show_existing_comments option with default value false
				// This maintains backward compatibility - existing behavior is preserved
				$this->options['show_existing_comments'] = false;
			}

			foreach (array('remove_everywhere', 'extra_post_types', 'show_existing_comments') as $v) {
				if (!isset($this->options[$v])) {
					$this->options[$v] = false;
				}
			}

			$this->options['db_version'] = self::DB_VERSION;
			$this->update_options($this->networkactive);
		}
	}

	public function check_upgrades() {
		$dc_version = get_option('disable_comment_version');
		if (version_compare($dc_version, '2.3.1', '<')) {
			if ($this->is_remove_everywhere()) {
				update_option('show_avatars', true);
			}
		}
		if (!$dc_version || $dc_version != DC_VERSION) {
			update_option('disable_comment_version', DC_VERSION);
		}
	}

	private function update_options($is_network_ctx = false) {
		if ($this->networkactive && $is_network_ctx) {
			update_site_option('disable_comments_options', $this->options);
		} else {
			update_option('disable_comments_options', $this->options);
		}
	}

	/**
	 * Read the settings as they are stored, for the store a write would target.
	 *
	 * $this->options is the *effective* configuration for the current request,
	 * which is not always what is on disk. On a network-wide install the
	 * constructor replaces it with a blank, everything-enabled config whenever
	 * the current blog is not among the network's disabled_sites and the
	 * request is not a network admin one - which is every WP-CLI request, since
	 * is_network_admin_ajax_context() has no GET parameter to read there.
	 *
	 * Export would then write that blank out as if it were the network's
	 * settings, and import would take it as the baseline for every field the
	 * incoming file omits - so a partial import of one flag would quietly reset
	 * the rest of the network to defaults. Both need the stored row.
	 *
	 * The routing deliberately mirrors update_options() line for line: an
	 * import's baseline has to be the same row the import will write, or the
	 * diff it reports is a diff against something else.
	 *
	 * @param bool $is_network_ctx Whether the operation targets network storage.
	 * @return array Stored settings, empty if nothing has been saved yet.
	 */
	private function get_stored_settings($is_network_ctx = false) {
		if ($this->networkactive && $is_network_ctx) {
			return (array) get_site_option('disable_comments_options', array());
		}

		return (array) get_option('disable_comments_options', array());
	}

	/**
	 * Purges front-end page caches after a change to what the site renders.
	 *
	 * Disabling comments changes the HTML of every page that carries a comment
	 * form or count, but a full-page cache keeps serving the old markup until
	 * each entry expires. On a live nginx FastCGI host this was reproducible:
	 * after saving, `x-cache: HIT` responses still contained the comment form
	 * while a cache-busted request did not. Comments really were off — visitors
	 * just could not tell.
	 *
	 * Called from the save and delete handlers rather than from
	 * update_options(), deliberately. Those handlers run on `wp_ajax_*` or
	 * WP-CLI, long after `plugins_loaded`, so every cache plugin has already
	 * registered its listeners. update_options() is also reached from
	 * check_db_upgrades() during plugin construction, where firing these would
	 * be too early for anything to hear them.
	 *
	 * Only *page* caches are purged. The object cache (Redis/Memcached) is left
	 * alone on purpose: settings are read through options that WordPress already
	 * invalidates on write, so flushing a shared object cache would stampede a
	 * busy site's origin for no benefit.
	 *
	 * Each integration is guarded — a caching plugin that renames or drops its
	 * API must never turn "settings saved" into a fatal error.
	 *
	 * @since 2.8.0
	 * @return void
	 */
	public function purge_page_caches($blog_ids = array()) {
		// On a network the purge has to run *inside* each affected site. Several
		// integrations (WP Rocket, SiteGround Optimizer, W3 Total Cache) only
		// clear the site they are called from, so purging once from the network
		// admin would leave every subsite serving deleted comments, stale counts,
		// or an old comment form.
		if (!empty($blog_ids) && is_multisite() && function_exists('switch_to_blog')) {
			foreach (array_unique(array_map('intval', (array) $blog_ids)) as $blog_id) {
				switch_to_blog($blog_id);
				$this->purge_current_site_page_caches();
				restore_current_blog();
			}
			return;
		}

		$this->purge_current_site_page_caches();
	}

	/**
	 * Purges page caches for the site that is currently switched in.
	 *
	 * Split out from purge_page_caches() so the network loop can reuse it
	 * without re-entering the switching logic.
	 *
	 * @since 2.8.0
	 * @return void
	 */
	private function purge_current_site_page_caches() {
		/**
		 * Fires when Disable Comments has changed what the front end renders.
		 *
		 * On a network this fires once per affected site, with that site
		 * switched in, so `get_current_blog_id()` inside the handler is the site
		 * being purged. Hosts, caching plugins, and CDN integrations can hook
		 * this to clear their own layer. Fired before the bundled integrations
		 * below so a handler can act first.
		 *
		 * @since 2.8.0
		 */
		do_action('disable_comments_purge_caches');

		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if (function_exists('w3tc_flush_posts')) {
			w3tc_flush_posts(); // W3 Total Cache — page cache only, not the whole stack.
		}
		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain(); // WP Rocket.
		}
		if (function_exists('sg_cachepress_purge_cache')) {
			sg_cachepress_purge_cache(); // SiteGround Optimizer.
		}

		// Action-based integrations. do_action() with no listener is a no-op, so
		// these are safe to fire unconditionally and stay correct for a plugin
		// that registers its listener late.
		do_action('litespeed_purge_all');        // LiteSpeed Cache.
		do_action('rt_nginx_helper_purge_all');  // Nginx Helper.
		do_action('breeze_clear_all_cache');     // Breeze.
		do_action('wphb_clear_page_cache');      // Hummingbird.
	}

	/**
	 * Sites whose rendered pages a settings save has just invalidated.
	 *
	 * A save from the network admin changes what every site in the network
	 * renders, so every site's page cache is stale — not just the one the
	 * request happened to run on. Outside a network context this is the current
	 * site alone, which purge_page_caches() handles by default.
	 *
	 * @since 2.8.0
	 * @param bool $is_network_ctx Whether the save came from a network admin screen.
	 * @return array Blog IDs to purge. Empty means "just the current site".
	 */
	private function get_purge_blog_ids($is_network_ctx) {
		if (!$is_network_ctx || !is_multisite() || !function_exists('get_sites')) {
			return array();
		}

		$blog_ids = get_sites(array('number' => 0, 'fields' => 'ids'));

		/**
		 * Filters the sites purged after a network-wide settings change.
		 *
		 * Defaults to every site in the network, which is correct but O(sites).
		 * A very large network whose cache layer already purges network-wide
		 * from a single call can narrow this list.
		 *
		 * @since 2.8.0
		 * @param array $blog_ids Blog IDs about to be purged.
		 */
		return (array) apply_filters('disable_comments_purge_blog_ids', $blog_ids);
	}

	public function get_disabled_sites($default = false) {
		$disabled_sites = ['all' => true];
		foreach (get_sites(['number' => 0, 'fields' => 'ids']) as $blog_id) {
			$disabled_sites["site_{$blog_id}"] = true;
		}
		if ($default) {
			return $disabled_sites;
		}

		$this->options['disabled_sites'] = isset($this->options['disabled_sites']) ? $this->options['disabled_sites'] : [];
		$this->options['disabled_sites'] = wp_parse_args($this->options['disabled_sites'], $disabled_sites);
		$disabled_sites = $this->options['disabled_sites'];
		unset($disabled_sites['all']);
		if (in_array(false, $disabled_sites)) {
			$this->options['disabled_sites']['all'] = false;
		} else {
			$this->options['disabled_sites']['all'] = true;
		}
		return $this->options['disabled_sites'];
	}

	// public function get_disabled_count(){
	// 	$disabled_sites = isset($this->options['disabled_sites']) ? $this->options['disabled_sites'] : [];
	// 	unset($disabled_sites['all']);
	// 	return array_sum($disabled_sites);
	// }

	/**
	 * Get an array of disabled post type.
	 */
	public function get_disabled_post_types() {
		$types = $this->options['disabled_post_types'];
		// Not all extra_post_types might be registered on this particular site.
		if ($this->networkactive && !empty($this->options['extra_post_types'])) {
			foreach ((array) $this->options['extra_post_types'] as $extra) {
				if (post_type_exists($extra)) {
					$types[] = $extra;
				}
			}
		}
		return $types;
	}

	/**
	 * Check whether comments have been disabled on a given post type.
	 */
	private function is_exclude_by_role() {
		if (!empty($this->options['enable_exclude_by_role']) && !empty($this->options['exclude_by_role'])) {
			if (is_user_logged_in()) {
				$user = wp_get_current_user();
				$roles = (array) $user->roles;
				$diff = array_intersect($this->options['exclude_by_role'], $roles);
				if (count($diff) || (in_array("administrator", $this->options['exclude_by_role']) && is_super_admin())) {
					return true;
				}
			} else if (in_array('logged-out-users', $this->options['exclude_by_role'])) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Endpoint-level comment blocking state.
	 *
	 * These settings are independent of the post-type configuration: either can
	 * block comments over its transport while post types remain untouched. A
	 * consumer that only inspects post-type settings would report comments as
	 * fully enabled while REST comment creation returns 403.
	 *
	 * REST blocking has two sources — the dedicated toggle *and* global
	 * "disable everywhere" mode, whose branch in init_filters() installs the
	 * same rest_pre_dispatch/rest_endpoints/rest_comment_query filters. Either
	 * one results in a 403 for non-allowlisted comment requests, so both count.
	 * XML-RPC has only the dedicated toggle; global mode does not touch it.
	 *
	 * Reported role-independently, matching how the rest of the site's
	 * configuration is described.
	 *
	 * @since 2.8.0
	 * @return array {
	 *     @type bool $rest   Whether REST API comment endpoints are blocked.
	 *     @type bool $xmlrpc Whether XML-RPC comment methods are removed.
	 * }
	 */
	public function get_endpoint_blocking_state() {
		$rest_toggle = isset($this->options['remove_rest_API_comments']) && intval($this->options['remove_rest_API_comments']) === 1;
		return array(
			'rest'   => $rest_toggle || $this->is_remove_everywhere_configured(),
			'xmlrpc' => isset($this->options['remove_xmlrpc_comments']) && intval($this->options['remove_xmlrpc_comments']) === 1,
		);
	}

	/**
	 * Comment types that stay enabled even when comments are disabled.
	 *
	 * The allowlist (e.g. WordPress 6.9+ "note" comments) is preserved in
	 * comment queries, counted separately, and permitted through REST even in
	 * "disable everywhere" mode. Consumers describing the site's comment state
	 * must disclose it, otherwise "comments are disabled" reads as absolute
	 * when it is not.
	 *
	 * @since 2.8.0
	 * @return array List of allowed comment type slugs.
	 */
	public function get_allowed_comment_types_list() {
		$allowed = $this->get_allowed_comment_types();
		return is_array($allowed) ? array_values($allowed) : array();
	}

	/**
	 * Whether the site is *configured* to disable comments everywhere.
	 *
	 * Unlike is_remove_everywhere(), this reports the stored setting regardless
	 * of the current user's role exemption. Consumers that describe the site's
	 * configuration (such as the Abilities API integration) need the
	 * role-independent value; consumers deciding whether to filter a given
	 * request must keep using is_remove_everywhere().
	 *
	 * @since 2.8.0
	 * @return bool True when the global "disable everywhere" setting is on.
	 */
	/**
	 * Whether every comment-capable post type on this site is actually closed.
	 *
	 * "Every public post type is ticked" is not the same as "comments are off
	 * everywhere". get_all_post_types() — and so the settings screen — only
	 * lists `public` post types, but a non-public post type can support comments
	 * too. Tick every box and that type stays open; switch on the global setting
	 * and it is closed. Only the second is genuinely site-wide.
	 *
	 * Detected by looking for any post type that *still* supports comments and
	 * is not in the disabled list. The plugin removes comment support from the
	 * types it closes, so whatever still supports comments is precisely what it
	 * has not closed — including non-public and late-registered types the
	 * settings screen never shows.
	 *
	 * Not valid for the global setting: under "remove everywhere" the plugin
	 * closes types without necessarily having stripped support from ones
	 * registered after its filters ran. Callers must check
	 * is_remove_everywhere_configured() first.
	 *
	 * @since 2.8.0
	 * @return bool True when no comment-capable post type is left open.
	 */
	/**
	 * Disabled post types, limited to ones that actually exist right now.
	 *
	 * The stored selection outlives the post types in it. Disable comments on a
	 * CPT, then deactivate the plugin that registered it, and the slug stays in
	 * the option forever — so a status report would advertise a post type the
	 * site no longer has, while get_all_post_types() correctly omits it.
	 *
	 * FOR REPORTING ONLY. Never use this to decide which types to filter:
	 * get_disabled_post_types() is consulted while filters are being installed,
	 * before CPTs have registered on `init`, and dropping unregistered types
	 * there would leave comments open on every custom post type.
	 *
	 * @since 2.8.0
	 * @return array Disabled post type slugs that are currently registered.
	 */
	public function get_disabled_post_types_registered() {
		$types = $this->get_disabled_post_types();
		$types = is_array($types) ? $types : array();

		$existing = array();
		foreach ($types as $type) {
			if (post_type_exists($type)) {
				$existing[] = $type;
			}
		}

		return array_values($existing);
	}

	public function is_every_comment_capable_type_disabled() {
		$disabled = $this->get_disabled_post_types();
		$disabled = is_array($disabled) ? $disabled : array();

		foreach (get_post_types(array(), 'names') as $post_type) {
			if (post_type_supports($post_type, 'comments') && !in_array($post_type, $disabled, true)) {
				return false;
			}
		}

		return true;
	}

	public function is_remove_everywhere_configured() {
		return !empty($this->options['remove_everywhere']);
	}

	/**
	 * Role-exclusion state for the current request.
	 *
	 * Role exclusion is a per-user override: when the current user matches an
	 * excluded role, comments are left open for them even though the site is
	 * configured to disable them. Consumers that report configuration (such as
	 * the Abilities API integration) need to disclose this, otherwise the
	 * reported status is misleading for exempt users.
	 *
	 * @since 2.8.0
	 * @return array {
	 *     @type bool $enabled  Whether role-based exclusion is configured at all.
	 *     @type bool $excluded Whether the *current* user is exempt.
	 * }
	 */
	public function get_role_exclusion_state() {
		$enabled = !empty($this->options['enable_exclude_by_role']) && !empty($this->options['exclude_by_role']);
		return array(
			'enabled'  => (bool) $enabled,
			'excluded' => (bool) $this->is_exclude_by_role(),
		);
	}

	private function is_remove_everywhere() {
		if ($this->is_exclude_by_role()) {
			return false;
		}
		if (isset($this->options['remove_everywhere'])) {
			return $this->options['remove_everywhere'];
		}
		return false;
	}

	/**
	 * Check whether comments have been disabled on a given post type.
	 */
	private function is_post_type_disabled($type) {
		if ($this->is_exclude_by_role()) {
			return false;
		}
		return $type && in_array($type, $this->get_disabled_post_types());
	}

	/**
	 * Is the conditional-rules layer switched on and carrying anything?
	 *
	 * Cheap enough to call from a per-post filter: it only looks at options
	 * already in memory, and short-circuits every rule evaluation when the
	 * feature is off - which is the overwhelmingly common case.
	 *
	 * @return bool
	 */
	public function has_conditional_rules() {
		if (empty($this->options['enable_conditional_rules'])) {
			return false;
		}

		return !empty($this->options['conditional_rules']) || $this->get_auto_close_days() > 0;
	}

	/**
	 * Configured auto-close window in days, or 0 when disabled.
	 *
	 * @return int
	 */
	public function get_auto_close_days() {
		if (empty($this->options['enable_conditional_rules'])) {
			return 0;
		}

		$days = isset($this->options['auto_close_days']) ? (int) $this->options['auto_close_days'] : 0;

		return $days > 0 ? $days : 0;
	}

	/**
	 * The saved conditional rules, always as a list.
	 *
	 * @return array
	 */
	public function get_conditional_rules() {
		if (empty($this->options['enable_conditional_rules']) || empty($this->options['conditional_rules'])) {
			return array();
		}

		return array_values((array) $this->options['conditional_rules']);
	}

	/**
	 * Is any active rule an "enable" exception?
	 *
	 * Asked by anything that wants to state comments are off everywhere. One
	 * enable rule means some post is deliberately still open, so the claim
	 * cannot be made — whether or not the rule currently matches anything,
	 * because "no post accepts comments" and "an exception exists that may
	 * match tomorrow" should not both be reported as a site-wide shutdown.
	 *
	 * @since 2.9.0
	 * @return bool
	 */
	public function has_enable_conditional_rules() {
		foreach ($this->get_conditional_rules() as $rule) {
			if (isset($rule['action']) && 'enable' === $rule['action']) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does one rule apply to this post?
	 *
	 * Existence is checked here rather than at save time: a taxonomy from a
	 * temporarily deactivated plugin should stop matching, not be silently
	 * dropped from the stored configuration.
	 *
	 * @param array $rule    A sanitized rule.
	 * @param int   $post_id Post being evaluated.
	 * @return bool
	 */
	private function conditional_rule_matches($rule, $post_id) {
		$type = isset($rule['type']) ? $rule['type'] : '';

		if ('taxonomy' === $type) {
			$taxonomy = isset($rule['taxonomy']) ? $rule['taxonomy'] : '';
			$terms    = isset($rule['terms']) ? array_filter((array) $rule['terms']) : array();

			if ('' === $taxonomy || empty($terms) || !taxonomy_exists($taxonomy)) {
				return false;
			}

			return (bool) has_term($terms, $taxonomy, $post_id);
		}

		if ('template' === $type) {
			$template = isset($rule['template']) ? $rule['template'] : '';

			if ('' === $template) {
				return false;
			}

			return $template === get_page_template_slug($post_id);
		}

		return false;
	}

	/**
	 * Has this post passed the auto-close window?
	 *
	 * @param int $post_id Post being evaluated.
	 * @return bool
	 */
	private function is_past_auto_close($post_id) {
		$days = $this->get_auto_close_days();

		if ($days < 1) {
			return false;
		}

		$published = get_post_time('U', true, $post_id);

		if (empty($published)) {
			return false;
		}

		return (time() - (int) $published) > ($days * DAY_IN_SECONDS);
	}

	/**
	 * Resolve the conditional rules for one post.
	 *
	 * An "enable" rule is an exception and wins outright, so "disable
	 * everywhere except Announcements" is expressible without enumerating
	 * every other term. An exception also survives the auto-close window -
	 * a post you deliberately kept open stays open.
	 *
	 * @param int $post_id Post being evaluated.
	 * @return string 'enable', 'disable', or '' when no rule applies.
	 */
	private function match_conditional_rules($post_id) {
		$result = '';

		foreach ($this->get_conditional_rules() as $rule) {
			if (!$this->conditional_rule_matches($rule, $post_id)) {
				continue;
			}

			if (isset($rule['action']) && 'enable' === $rule['action']) {
				return 'enable';
			}

			$result = 'disable';
		}

		if ('' === $result && $this->is_past_auto_close($post_id)) {
			$result = 'disable';
		}

		return $result;
	}

	/**
	 * Are comments disabled for this specific post?
	 *
	 * The base answer comes from the global toggle and the post-type list;
	 * conditional rules then override it. This is the single decision the
	 * per-post filters ask, so comments_open(), the comment count and the
	 * existing-comment list can never disagree with each other.
	 *
	 * @param int $post_id Post being evaluated.
	 * @return bool
	 */
	public function is_disabled_for_post($post_id) {
		if ($this->is_exclude_by_role()) {
			return false;
		}

		$disabled = $this->is_remove_everywhere() || $this->is_post_type_disabled(get_post_type($post_id));

		if (!$this->has_conditional_rules()) {
			return $disabled;
		}

		$match = $this->match_conditional_rules($post_id);

		if ('enable' === $match) {
			return false;
		}

		if ('disable' === $match) {
			return true;
		}

		return $disabled;
	}

	/**
	 * Normalise submitted conditional rules.
	 *
	 * Anything that does not describe a complete, actionable rule is dropped
	 * rather than stored half-formed - a rule with no terms would otherwise
	 * sit in the options looking active while matching nothing.
	 *
	 * @param mixed $rules Raw submitted rules.
	 * @return array Sanitized list.
	 */
	/**
	 * Rewrite taxonomy rules for travel between sites.
	 *
	 * Rules are stored with term IDs, which are rows in one site's terms table
	 * and mean nothing anywhere else. Writing them into an export file is worse
	 * than leaving the rules out: term 104 exists on the destination too, as
	 * something entirely unrelated, so the import silently closes comments on
	 * the wrong category while the one the operator meant stays open.
	 *
	 * Slugs are what travel. A term that cannot be resolved is dropped, and a
	 * taxonomy rule left with no terms is dropped whole, because such a rule
	 * matches nothing and would only sit in the UI looking meaningful.
	 *
	 * The export format is new in 2.9.0, so there are no term-ID files in the
	 * wild to stay compatible with.
	 *
	 * @param array $rules Stored rules, taxonomy terms as IDs.
	 * @return array Portable rules, taxonomy terms as slugs.
	 */
	private function rules_to_portable($rules) {
		$out = array();

		foreach ((array) $rules as $rule) {
			if (!isset($rule['type']) || 'taxonomy' !== $rule['type']) {
				$out[] = $rule;
				continue;
			}

			$taxonomy = isset($rule['taxonomy']) ? $rule['taxonomy'] : '';
			$slugs    = array();

			foreach ((array) (isset($rule['terms']) ? $rule['terms'] : array()) as $term_id) {
				$term = get_term((int) $term_id, $taxonomy);

				if ($term && !is_wp_error($term) && '' !== $term->slug) {
					$slugs[] = $term->slug;
				}
			}

			if (empty($slugs)) {
				continue;
			}

			$rule['terms'] = array_values(array_unique($slugs));
			$out[]         = $rule;
		}

		return $out;
	}

	/**
	 * Resolve a portable rule set against this site's terms.
	 *
	 * The inverse of rules_to_portable(). Runs before sanitize_conditional_rules(),
	 * which intval()s terms and would turn every slug into 0.
	 *
	 * @param array $rules Portable rules, taxonomy terms as slugs.
	 * @return array Rules for storage, taxonomy terms as IDs on this site.
	 */
	private function rules_from_portable($rules) {
		$out = array();

		foreach ((array) $rules as $rule) {
			if (!is_array($rule) || !isset($rule['type']) || 'taxonomy' !== $rule['type']) {
				$out[] = $rule;
				continue;
			}

			$taxonomy = isset($rule['taxonomy']) ? sanitize_key($rule['taxonomy']) : '';
			$ids      = array();

			foreach ((array) (isset($rule['terms']) ? $rule['terms'] : array()) as $slug) {
				if (!is_scalar($slug)) {
					continue;
				}

				$term = get_term_by('slug', sanitize_title((string) $slug), $taxonomy);

				if ($term && !is_wp_error($term)) {
					$ids[] = (int) $term->term_id;
				}
			}

			// A term the destination does not have cannot be matched, so the
			// rule is dropped rather than stored pointing at nothing.
			if (empty($ids)) {
				continue;
			}

			$rule['terms'] = array_values(array_unique($ids));
			$out[]         = $rule;
		}

		return $out;
	}

	private function sanitize_conditional_rules($rules) {
		$clean = array();

		foreach ((array) $rules as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$type = isset($rule['type']) ? sanitize_key($rule['type']) : '';

			if ('taxonomy' !== $type && 'template' !== $type) {
				continue;
			}

			$action = (isset($rule['action']) && 'enable' === $rule['action']) ? 'enable' : 'disable';
			$entry  = array(
				'type'   => $type,
				'action' => $action,
			);

			if ('taxonomy' === $type) {
				$taxonomy = isset($rule['taxonomy']) ? sanitize_key($rule['taxonomy']) : '';
				$terms    = isset($rule['terms']) ? array_values(array_filter(array_map('intval', (array) $rule['terms']))) : array();

				if ('' === $taxonomy || empty($terms)) {
					continue;
				}

				$entry['taxonomy'] = $taxonomy;
				$entry['terms']    = $terms;
			} else {
				$template = isset($rule['template']) ? sanitize_text_field($rule['template']) : '';

				if ('' === $template) {
					continue;
				}

				$entry['template'] = $template;
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * Is blocked-attempt counting switched on?
	 *
	 * A very high traffic site may not want the shutdown write at all. The
	 * filter is the supported way off; there is no setting, because a setting
	 * to stop counting is a setting nobody would find.
	 *
	 * @return bool
	 */
	public function blocked_stats_enabled() {
		return (bool) apply_filters('disable_comments_count_blocked_attempts', true);
	}

	/**
	 * Record one blocked attempt.
	 *
	 * In-memory only - see flush_blocked_stats() for the single write.
	 *
	 * @param string $vector One of 'comment', 'trackback', 'rest'.
	 */
	public function count_blocked_attempt($vector) {
		if (!$this->blocked_stats_enabled()) {
			return;
		}

		if (!isset($this->blocked_pending[$vector])) {
			$this->blocked_pending[$vector] = 0;
		}

		$this->blocked_pending[$vector]++;
	}

	/**
	 * Blocked-attempt totals, normalised.
	 *
	 * @return array {
	 *     @type int   $since  Timestamp counting started from.
	 *     @type array $counts Vector => count.
	 * }
	 */
	public function get_blocked_stats() {
		$since = (int) get_option(self::BLOCKED_SINCE_OPTION, 0);

		if ($since < 1) {
			// Falling back to time() without storing it made the start date
			// re-compute on every read, so a site that had not blocked
			// anything yet reported "counting since" today, then tomorrow, then
			// the day after - which reads as a counter that keeps resetting
			// itself. The first read is as good a moment as any to fix it, and
			// it is the first moment anybody could have noticed.
			$since = $this->stamp_blocked_since();
		}

		$counts = array();
		foreach ($this->get_blocked_vectors() as $vector => $label) {
			$counts[$vector] = (int) get_option($this->get_blocked_vector_option($vector), 0);
		}

		return array(
			'since'  => $since,
			'counts' => $counts,
			'total'  => array_sum($counts),
		);
	}

	/**
	 * Fix the moment counting started, and keep it.
	 *
	 * Returns the timestamp that is now stored, which is not always the one
	 * this call proposed: a request that blocked something first has already
	 * stamped it, and its timestamp is the true one.
	 *
	 * @return int Unix timestamp counting is measured from.
	 */
	private function stamp_blocked_since() {
		$now = time();

		// add_option(), not update_option(): this must never move a start date
		// that is already recorded, and its failure is how a race with
		// flush_blocked_stats() is detected rather than silently won.
		if (add_option(self::BLOCKED_SINCE_OPTION, $now, '', 'yes')) {
			return $now;
		}

		$stored = (int) get_option(self::BLOCKED_SINCE_OPTION, 0);

		if ($stored > 0) {
			return $stored;
		}

		// A row exists holding something that is not a timestamp, so the read
		// above will keep falling through to "now" on every request - the same
		// drifting date, from corrupt data rather than a missing option. The
		// only value being overwritten here is one that never meant anything.
		update_option(self::BLOCKED_SINCE_OPTION, $now, 'yes');

		return $now;
	}

	/**
	 * The vectors we can count, and what to call them.
	 *
	 * Deliberately does not include XML-RPC. wp.newComment is removed from the
	 * server rather than rejected, so there is no dispatch to observe, and
	 * pingback.ping is refused inside core's own method without firing anything
	 * we can hang a counter on. A counter permanently reading zero would be
	 * read as "no attempts", which is a different and false claim - so the
	 * trackback vector is named for the wp-trackback.php flow it actually
	 * covers rather than claiming pingbacks too.
	 *
	 * @return array Vector => translated label.
	 */
	public function get_blocked_vectors() {
		return array(
			'comment'   => __('Comment form submissions', 'disable-comments'),
			'trackback' => __('Trackbacks', 'disable-comments'),
			'rest'      => __('REST API comment requests', 'disable-comments'),
		);
	}

	/**
	 * Write this request's blocked attempts, once.
	 *
	 * At most one write per request that actually blocked something - not per
	 * request. The option is autoloaded because it is a handful of integers and
	 * autoloading removes a read query from the path that has to write it.
	 */
	public function flush_blocked_stats() {
		global $wpdb;

		if (empty($this->blocked_pending)) {
			return;
		}

		$pending               = $this->blocked_pending;
		$this->blocked_pending = array();

		// Read-modify-write would drop increments whenever two blocked requests
		// shut down together - which is exactly what a spam burst looks like,
		// and exactly when this number is supposed to be meaningful. Each
		// vector is its own numeric option incremented in one statement, so the
		// database does the addition and concurrent writers cannot clobber each
		// other.
		foreach ($pending as $vector => $increment) {
			$option = $this->get_blocked_vector_option($vector);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $wpdb->options SET option_value = option_value + %d WHERE option_name = %s",
					(int) $increment,
					$option
				)
			);

			if (!$updated) {
				// No row yet. Two requests racing to be the first would both
				// land here and only one add_option() can win the unique
				// option_name index, silently losing the other's increment - so
				// the loser retries the UPDATE against the row the winner just
				// created rather than dropping its count.
				if (!add_option($option, (int) $increment, '', 'yes')) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE $wpdb->options SET option_value = option_value + %d WHERE option_name = %s",
							(int) $increment,
							$option
						)
					);
				}
			}

			wp_cache_delete($option, 'options');
		}

		// Alloptions caches the whole set, so the individual deletes above are
		// not enough on a site using it.
		wp_cache_delete('alloptions', 'options');

		if (!get_option(self::BLOCKED_SINCE_OPTION)) {
			add_option(self::BLOCKED_SINCE_OPTION, time(), '', 'yes');
		}
	}

	/**
	 * Option name holding one vector's running total.
	 *
	 * @param string $vector Vector key.
	 * @return string
	 */
	private function get_blocked_vector_option($vector) {
		return self::BLOCKED_STATS_OPTION . '_' . $vector;
	}

	/**
	 * Start counting again from now.
	 */
	public function reset_blocked_stats() {
		$this->blocked_pending = array();

		foreach (array_keys($this->get_blocked_vectors()) as $vector) {
			update_option($this->get_blocked_vector_option($vector), 0, 'yes');
		}

		update_option(self::BLOCKED_SINCE_OPTION, time(), 'yes');
	}

	/**
	 * AJAX: reset the counters.
	 *
	 * Agencies hand sites over; the previous owner's numbers are noise.
	 */
	public function reset_blocked_stats_ajax() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		$this->reset_blocked_stats();

		wp_send_json_success($this->get_blocked_stats());
	}

	/**
	 * A comment submission core rejected because we closed comments.
	 *
	 * Fired from core's `comment_closed`, which runs only on a real POST to
	 * wp-comments-post.php - never on render.
	 */
	public function record_blocked_comment($post_id = 0) {
		// core fires this whenever a submission is refused, including posts
		// closed by their own comment_status or by another plugin. Crediting
		// ourselves for those would make the number meaningless.
		if (!$this->closed_by_this_plugin($post_id, 'comment')) {
			return;
		}

		$this->count_blocked_attempt('comment');
	}

	/**
	 * Is this plugin the reason comments are closed on this post?
	 *
	 * @param int $post_id Post being submitted to.
	 * @return bool
	 */
	private function closed_by_this_plugin($post_id, $vector = 'comment') {
		$post_id = (int) $post_id;

		if ($post_id < 1) {
			return false;
		}

		if ($this->is_exclude_by_role()) {
			return false;
		}

		// is_disabled_for_post(), not the base settings on their own. A
		// taxonomy, template or age rule can be the only reason a post is
		// closed, and asking only whether the global toggle or the post-type
		// list covers it meant every attempt blocked purely by a rule went
		// uncounted — invisible in the totals on exactly the sites configured
		// with rules and nothing else.
		if (!$this->is_disabled_for_post($post_id)) {
			return false;
		}

		// The post is covered, but this particular post may already have
		// been closed on its own - by its own status, by core's
		// close_comments_for_old_posts, or by another plugin. Our filters would
		// have closed it anyway, so we cannot tell from the result alone; ask
		// the stored status, which is what we do not touch.
		//
		// The status has to match the attempt: a post with comment_status
		// closed and ping_status open rejects a comment on its own, and the
		// open ping status says nothing about that.
		$post = get_post($post_id);

		if (!$post) {
			return false;
		}

		$status = ('trackback' === $vector) ? $post->ping_status : $post->comment_status;

		return 'open' === $status;
	}

	/**
	 * A trackback aimed at a post whose pings we closed.
	 *
	 * `pre_trackback_post` fires just before core's own pings_open() check, so
	 * the same question is asked here to avoid counting ones that go through.
	 *
	 * @param int $post_id Target post.
	 */
	public function record_blocked_trackback($post_id) {
		if (pings_open($post_id)) {
			return;
		}

		// Same reasoning as record_blocked_comment(): pings closed natively, or
		// by somebody else, are not ours to claim.
		if (!$this->closed_by_this_plugin($post_id, 'trackback')) {
			return;
		}

		$this->count_blocked_attempt('trackback');
	}

	public function init_filters() {
		if ($this->blocked_stats_enabled()) {
			// Core fires these only on real submissions, never on render.
			// Priority/args: core passes the post id, which is what decides
			// whether this plugin is the reason it was refused.
			add_action('comment_closed', array($this, 'record_blocked_comment'), 10, 1);
			add_action('pre_trackback_post', array($this, 'record_blocked_trackback'));
			add_action('shutdown', array($this, 'flush_blocked_stats'));
		}

		// Inert unless the request carries a token this site issued seconds
		// ago, so an ordinary visitor never reaches any of it.
		if (!is_admin()) {
			$this->maybe_arm_scan_probe();
		}

		// These need to happen now.
		if ($this->is_remove_everywhere()) {
			add_action('widgets_init', array($this, 'disable_rc_widget'));
			add_filter('wp_headers', array($this, 'filter_wp_headers'));
			add_action('template_redirect', array($this, 'filter_query'), 9);   // before redirect_canonical.

			// Admin bar filtering has to happen here since WP 3.6.
			add_action('template_redirect', array($this, 'filter_admin_bar'));
			add_action('admin_init', array($this, 'filter_admin_bar'));

			// Disable Comments REST API Endpoint (but allow notes)
			add_filter('rest_endpoints', array($this, 'filter_rest_endpoints'));
			add_filter('rest_pre_dispatch', array($this, 'filter_rest_comment_dispatch'), 10, 3);
			add_filter('rest_comment_query', array($this, 'filter_rest_comment_query'), 10, 2);
		}

		// remove create comment via xmlrpc
		if (isset($this->options['remove_xmlrpc_comments']) && intval($this->options['remove_xmlrpc_comments']) === 1) {
			add_filter('xmlrpc_methods', array($this, 'disable_xmlrc_comments'));
		}
		// rest API Comment Block (but allow notes)
		if (isset($this->options['remove_rest_API_comments']) && intval($this->options['remove_rest_API_comments']) === 1) {
			add_filter('rest_endpoints', array($this, 'filter_rest_endpoints'));
			add_filter('rest_pre_insert_comment', array($this, 'disable_rest_API_comments'), 10, 2);
			add_filter('rest_pre_dispatch', array($this, 'filter_rest_comment_dispatch'), 10, 3);
			add_filter('rest_comment_query', array($this, 'filter_rest_comment_query'), 10, 2);
		}

		// Comment types closed by the blocklist. Registered on the strength of
		// the stored option alone: whether the caller is exempt is decided
		// inside the callbacks, which run long after the current user has been
		// resolved. Note the deliberate absence of a comments_open() filter -
		// see reject_blocked_comment_type().
		if ($this->has_blocked_comment_types()) {
			add_filter('preprocess_comment', array($this, 'reject_blocked_comment_type'), 20);
			add_filter('rest_pre_insert_comment', array($this, 'reject_blocked_comment_type_rest'), 20, 2);
		}

		// These can happen later.
		add_action('wp_loaded', array($this, 'init_wploaded_filters'));
		// Disable "Latest comments" block in Gutenberg.
		add_action('enqueue_block_editor_assets', array($this, 'filter_gutenberg_blocks'));
		// settings page assets
		add_action('admin_enqueue_scripts', array($this, 'settings_page_assets'));

		if (!$this->networkactive || $this->options['sitewide_settings']) {
			add_filter('comment_status_links', function ($status_links) {
				$status_links['disable_comments'] = sprintf("<a href='" . $this->settings_page_url() . "'>%s</a>", __("Disable Comments", 'disable-comments'));
				return $status_links;
			});
		}
	}

	public function init_wploaded_filters() {
		$disabled_post_types = $this->get_disabled_post_types();
		if (!empty($disabled_post_types) && !$this->is_exclude_by_role()) {
			foreach ($disabled_post_types as $type) {
				// we need to know what native support was for later.
				if (post_type_supports($type, 'comments')) {
					$this->modified_types[] = $type;
					// Keep comments support if show_existing_comments is enabled
					// or if there are allowed comment types that need to be displayed
					if (empty($this->options['show_existing_comments']) && !$this->has_allowed_comment_types()) {
						remove_post_type_support($type, 'comments');
					}
					remove_post_type_support($type, 'trackbacks');
				}
			}
		} elseif (is_admin() && !$this->is_configured()) {
			/**
			 * It is possible that $disabled_post_types is empty if other
			 * plugins have disabled comments. Hence we also check for
			 * remove_everywhere. If you still get a warning you probably
			 * shouldn't be using this plugin.
			 */
			add_action('all_admin_notices', array($this, 'setup_notice'));
		}

		// Conditional rules are evaluated per post, so they need these filters
		// even when no post type is ticked and the global toggle is off -
		// otherwise a site configured purely with rules silently does nothing.
		if ($this->is_remove_everywhere() || $this->has_conditional_rules() || (!empty($disabled_post_types) && !$this->is_exclude_by_role())) {
			add_filter('comments_array', array($this, 'filter_existing_comments'), 20, 2);
			add_filter('comments_open', array($this, 'filter_comment_status'), 20, 2);
			add_filter('pings_open', array($this, 'filter_comment_status'), 20, 2);
			add_filter('get_comments_number', array($this, 'filter_comments_number'), 20, 2);
		}

		// A store that closed reviews should stop displaying the review UI, or
		// customers are invited to submit something the blocklist will refuse.
		// WooCommerce's own "enable reviews" switch is not usable for this: it
		// strips comments support from the product post type, and WooCommerce
		// then closes comments_open() for every product - taking the other
		// comment types on products down with it, which is precisely what this
		// setting exists to keep working.
		//
		// The reviews already in the database are left alone. Untick the
		// setting and they are all still there.
		if (
			$this->is_woocommerce_active()
			&& $this->is_comment_type_blocked($this->get_review_comment_type())
			&& !$this->is_exempt_from_blocklist()
		) {
			// Before woocommerce_sort_product_tabs(), which runs at 99.
			add_filter('woocommerce_product_tabs', array($this, 'remove_woocommerce_reviews_tab'), 98);
			// The star rating in the product summary links to #reviews, which
			// the line above just removed from the page.
			remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
		}

		// Filters for the admin only.
		if (is_admin()) {
			add_action('all_admin_notices', array($this, 'admin_notice'));
			if ($this->networkactive && is_network_admin()) {
				add_action('network_admin_menu', array($this, 'settings_menu'));
				add_action('network_admin_menu', array($this, 'tools_menu'));
				add_filter('network_admin_plugin_action_links', array($this, 'plugin_actions_links'), 10, 2);
			} elseif (!$this->networkactive || $this->options['sitewide_settings']) {
				add_action('admin_menu', array($this, 'settings_menu'));
				add_action('admin_menu', array($this, 'tools_menu'));
				add_filter('plugin_action_links', array($this, 'plugin_actions_links'), 10, 2);
				if (is_multisite()) {    // We're on a multisite setup, but the plugin isn't network activated.
					register_deactivation_hook(__FILE__, array($this, 'single_site_deactivate'));
				}
			}
			add_action('admin_notices', array($this, 'discussion_notice'));
			// Gated on our own screens inside should_show_review_prompt(), not
			// by the hook - admin_notices fires everywhere. Network admin pages
			// fire network_admin_notices instead, so allowlisting the -network
			// screen ids achieves nothing without this second registration.
			add_action('admin_notices', array($this, 'review_prompt'));
			add_action('network_admin_notices', array($this, 'review_prompt'));
			add_filter('plugin_row_meta', array($this, 'set_plugin_meta'), 10, 2);

			if ($this->is_remove_everywhere()) {
				add_action('admin_menu', array($this, 'filter_admin_menu'), 9999);  // do this as late as possible.
				add_action('admin_print_styles-index.php', array($this, 'admin_css'));
				add_action('admin_print_styles-profile.php', array($this, 'admin_css'));
				add_action('wp_dashboard_setup', array($this, 'filter_dashboard'));
				add_filter('pre_option_default_pingback_flag', '__return_zero');
			}
		}
		// Filters for front end only.
		else {
			add_action('template_redirect', array($this, 'check_comment_template'));

			if ($this->is_remove_everywhere()) {
				add_filter('feed_links_show_comments_feed', '__return_false');
			}
		}
	}

	// public function get_option( $key, $default = false ){
	// 	return $this->networkactive ? get_site_option( $key, $default ) : get_option( $key, $default );
	// }
	// public function update_option( $option, $value ){
	// 	return $this->networkactive ? update_site_option( $option, $value ) : update_option( $option, $value );
	// }
	// public function delete_option( $option ){
	// 	return $this->networkactive ? delete_site_option( $option ) : delete_option( $option );
	// }

	/**
	 * Replace the theme's comment template with a blank one.
	 * To prevent this, define DISABLE_COMMENTS_REMOVE_COMMENTS_TEMPLATE
	 * and set it to True
	 */
	public function check_comment_template() {
		// is_disabled_for_post(), not the global settings: an "enable" rule has
		// to reach here too. Otherwise comments_open() reports open, the theme
		// asks for the comments template, and we hand it the empty one - so the
		// advertised exception produces a post with no comment form on it.
		if (is_singular() && $this->is_disabled_for_post(get_queried_object_id())) {
			if (!defined('DISABLE_COMMENTS_REMOVE_COMMENTS_TEMPLATE') || DISABLE_COMMENTS_REMOVE_COMMENTS_TEMPLATE == true) {
				// Kill the comments template unless:
				// - show_existing_comments is enabled, OR
				// - there are allowed comment types that need to be displayed
				if (empty($this->options['show_existing_comments']) && !$this->has_allowed_comment_types()) {
					add_filter('comments_template', array($this, 'dummy_comments_template'), 20);
				}
			}
			// Remove comment-reply script for themes that include it indiscriminately.
			wp_deregister_script('comment-reply');
			// feed_links_extra inserts a comments RSS link.
			remove_action('wp_head', 'feed_links_extra', 3);
		}
	}

	public function dummy_comments_template() {
		return dirname(__FILE__) . '/views/comments.php';
	}

	public function is_xmlrpc_rest() {
		// remove create comment via xmlrpc
		if (isset($this->options['remove_xmlrpc_comments']) && intval($this->options['remove_xmlrpc_comments']) === 1) {
			return true;
		}
		// rest API Comment Block
		if (isset($this->options['remove_rest_API_comments']) && intval($this->options['remove_rest_API_comments']) === 1) {
			return true;
		}
		return false;
	}

	/**
	 * Remove the X-Pingback HTTP header
	 */
	public function filter_wp_headers($headers) {
		unset($headers['X-Pingback']);
		return $headers;
	}

	/**
	 * remove method wp.newComment
	 */
	public function disable_xmlrc_comments($methods) {
		unset($methods['wp.newComment']);
		return $methods;
	}

	public function disable_rest_API_comments($prepared_comment, $request) {
		// Allow comment types in the allowlist (e.g., WordPress 6.9+ block notes)
		if ($this->is_allowed_comment_type_request($request)) {
			return $prepared_comment;
		}

		// A conditional exception keeps comments open on this post, so a REST
		// submission to it must go through as well - blocking it here would
		// make comments_open() a lie for every API client.
		//
		// Only when the block comes from the global toggle or the post-type
		// list, though. "Disable comments via REST API" is an explicit
		// site-wide decision about the API itself, not about any one post, so a
		// per-post exception must not quietly switch it back on.
		if ($this->rest_blocking_is_conditional()) {
			$post_id = $this->get_request_post_id($request, $prepared_comment);
			if ($post_id && !$this->is_disabled_for_post($post_id)) {
				return $prepared_comment;
			}
		}


		$this->count_blocked_attempt('rest');

		return;
	}

	/**
	 * Get the list of allowed comment types from settings
	 *
	 * @return array Array of allowed comment types
	 */
	private function get_allowed_comment_types() {
		if (!isset($this->options['allowed_comment_types']) || !is_array($this->options['allowed_comment_types'])) {
			return array(); // Default: all special comment types disabled
		}

		// A type ticked on both lists is a contradiction: "keep this working
		// where comments are off" and "close this where comments are on". The
		// blocklist wins - this is a plugin for switching comments off, so the
		// restrictive reading is the safe one. Resolving it in the single
		// reader means the delete tool, the REST allowance and the status
		// report cannot end up disagreeing with each other.
		$blocked = $this->get_blocked_comment_types();
		if (empty($blocked)) {
			return $this->options['allowed_comment_types'];
		}

		return array_values(array_diff($this->options['allowed_comment_types'], $blocked));
	}

	/**
	 * Check if any comment types are enabled in the allowlist
	 *
	 * @return bool True if there are allowed comment types, false otherwise
	 */
	private function has_allowed_comment_types() {
		$allowed_types = $this->get_allowed_comment_types();
		return !empty($allowed_types);
	}

	/**
	 * Check if a specific comment type is allowed (enabled in the allowlist)
	 *
	 * @param string $comment_type The comment type to check
	 * @return bool True if the comment type is allowed, false otherwise
	 */
	private function is_comment_type_allowed($comment_type) {
		$allowed_types = $this->get_allowed_comment_types();
		return in_array($comment_type, $allowed_types, true);
	}

	/**
	 * Get the list of blocked comment types from settings
	 *
	 * The blocklist is the mirror image of the allowlist. The allowlist keeps
	 * named types working where comments are off; the blocklist closes named
	 * types where comments are on. Both exist because comments_open() is one
	 * boolean per post and cannot split a comment type from the post type
	 * carrying it - a WooCommerce store that wants product reviews closed while
	 * other comments on products stay open has no way to say so otherwise.
	 *
	 * @since 2.9.1
	 * @return array Array of blocked comment types
	 */
	private function get_blocked_comment_types() {
		if (!isset($this->options['blocked_comment_types']) || !is_array($this->options['blocked_comment_types'])) {
			return array(); // Default: nothing is closed by type
		}
		return $this->options['blocked_comment_types'];
	}

	/**
	 * Check if any comment types are closed by the blocklist
	 *
	 * @since 2.9.1
	 * @return bool True if there are blocked comment types, false otherwise
	 */
	private function has_blocked_comment_types() {
		$blocked_types = $this->get_blocked_comment_types();
		return !empty($blocked_types);
	}

	/**
	 * Check if a specific comment type is closed by the blocklist
	 *
	 * @since 2.9.1
	 * @param string $comment_type The comment type to check
	 * @return bool True if the comment type is blocked, false otherwise
	 */
	private function is_comment_type_blocked($comment_type) {
		$blocked_types = $this->get_blocked_comment_types();
		return in_array($comment_type, $blocked_types, true);
	}

	/**
	 * Comment types that are closed even where comments are open.
	 *
	 * The counterpart to get_allowed_comment_types_list(). Consumers describing
	 * the site's comment state must disclose it: "comments are open on
	 * products" reads as absolute when it is not, and an agent that submits a
	 * review on the strength of it gets a 403 it could have predicted.
	 *
	 * @since 2.9.1
	 * @return array List of blocked comment type slugs.
	 */
	public function get_blocked_comment_types_list() {
		$blocked = $this->get_blocked_comment_types();
		return is_array($blocked) ? array_values($blocked) : array();
	}

	/**
	 * The effective type of a comment being submitted.
	 *
	 * WordPress has written '' for the default type historically and 'comment'
	 * since 5.5, and both still arrive. Normalising here means the blocklist
	 * compares like with like no matter which one a caller sent.
	 *
	 * @since 2.9.1
	 * @param mixed $comment_type Raw comment type from a submission payload.
	 * @return string
	 */
	private function normalize_comment_type($comment_type) {
		$comment_type = is_string($comment_type) ? trim($comment_type) : '';
		return ('' === $comment_type) ? 'comment' : $comment_type;
	}

	/**
	 * Is this caller exempt from the blocklist?
	 *
	 * Two exemptions, both deliberate:
	 *
	 * - The plugin's own role exclusion. Someone the site has exempted from
	 *   comment disabling is exempt from this too, or the setting would mean
	 *   something different depending on which screen it is read from.
	 * - Anyone who can moderate comments. Closing a comment type is a statement
	 *   about what the public may submit, not about what a moderator may add by
	 *   hand: the rest of the plugin leaves back-end comment creation alone,
	 *   and wp_die()-ing the Reply button on wp-admin's comment screen would be
	 *   a bug, not a feature. Such a user can untick the setting anyway.
	 *
	 * @since 2.9.1
	 * @return bool
	 */
	private function is_exempt_from_blocklist() {
		if ($this->is_exclude_by_role()) {
			return true;
		}

		return function_exists('current_user_can') && current_user_can('moderate_comments');
	}

	/**
	 * Refuse a comment whose type the site has closed.
	 *
	 * Runs on preprocess_comment at priority 20, which matters: WooCommerce
	 * rewrites every default-type front-end comment on a product into a
	 * 'review' from priority 1, so anything earlier would judge the type before
	 * WooCommerce had finished deciding it.
	 *
	 * comments_open() is deliberately left alone. Closing it for the product
	 * would close every other comment type on that product too, which is the
	 * exact thing this setting exists to avoid.
	 *
	 * @since 2.9.1
	 * @param array $commentdata Comment data on its way to wp_insert_comment().
	 * @return array Unchanged when the type is not blocked; execution ends otherwise.
	 */
	public function reject_blocked_comment_type($commentdata) {
		if (!is_array($commentdata) || !$this->has_blocked_comment_types()) {
			return $commentdata;
		}

		$comment_type = $this->normalize_comment_type(isset($commentdata['comment_type']) ? $commentdata['comment_type'] : '');

		if (!$this->is_comment_type_blocked($comment_type) || $this->is_exempt_from_blocklist()) {
			return $commentdata;
		}

		$this->count_blocked_attempt('comment');

		wp_die(
			esc_html__('Sorry, this kind of comment is no longer being accepted on this item.', 'disable-comments'),
			esc_html__('Comment Type Closed', 'disable-comments'),
			array('response' => 403)
		);
	}

	/**
	 * Refuse a REST-created comment whose type the site has closed.
	 *
	 * The REST controller calls wp_insert_comment() directly, so it never
	 * reaches preprocess_comment and needs its own guard. Note that WooCommerce
	 * does not rewrite types on this path either: a review arrives over REST
	 * only when the caller asked for one by name.
	 *
	 * @since 2.9.1
	 * @param array|mixed     $prepared_comment Comment data prepared for the database.
	 * @param WP_REST_Request $request          The request.
	 * @return array|WP_Error|mixed
	 */
	public function reject_blocked_comment_type_rest($prepared_comment, $request) {
		// An earlier filter (disable_rest_API_comments) may already have
		// refused this request and returned nothing. Passing that through
		// unchanged keeps its refusal intact instead of masking it.
		if (!is_array($prepared_comment) || !$this->has_blocked_comment_types()) {
			return $prepared_comment;
		}

		$comment_type = $this->normalize_comment_type(isset($prepared_comment['comment_type']) ? $prepared_comment['comment_type'] : '');

		if (!$this->is_comment_type_blocked($comment_type) || $this->is_exempt_from_blocklist()) {
			return $prepared_comment;
		}

		$this->count_blocked_attempt('rest');

		return new WP_Error(
			'disable_comments_type_closed',
			__('Sorry, this kind of comment is no longer being accepted on this item.', 'disable-comments'),
			array('status' => 403)
		);
	}

	/**
	 * Drop WooCommerce's Reviews tab from the product page.
	 *
	 * Registered only while the review type is blocked. The tab renders both
	 * the review list and the review form, so removing it is what stops a store
	 * inviting a submission it is about to refuse.
	 *
	 * @since 2.9.1
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public function remove_woocommerce_reviews_tab($tabs) {
		if (is_array($tabs)) {
			unset($tabs['reviews']);
		}

		return $tabs;
	}

	/**
	 * Get available comment type options for the "Enable Certain Comment Types" UI
	 *
	 * This function returns a list of known special comment types that users can enable,
	 * regardless of whether any comments of those types currently exist in the database.
	 *
	 * IMPORTANT: WordPress does not provide a formal API for registering or retrieving
	 * comment types (unlike post types with get_post_types()). Comment types are simply
	 * arbitrary string values stored in the wp_comments table. Therefore, we maintain
	 * a curated list of known special comment types that plugins commonly use.
	 *
	 * This function returns only predefined known types plus any types added via the
	 * 'disable_comments_known_comment_types' filter hook.
	 *
	 * @return array Associative array of comment_type => label
	 */
	/**
	 * Is WooCommerce running on this site?
	 *
	 * Every WooCommerce touch-point in this plugin goes through here. A store
	 * plugin being absent, or renaming its API, must never turn "settings
	 * saved" into a fatal - the same rule the cache integrations follow.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists('WooCommerce');
	}

	/**
	 * The post type WooCommerce stores products under.
	 *
	 * @return string
	 */
	public function get_product_post_type() {
		return 'product';
	}

	/**
	 * The comment type WooCommerce stores reviews under.
	 *
	 * @return string
	 */
	public function get_review_comment_type() {
		return 'review';
	}

	/**
	 * How product reviews stand right now.
	 *
	 * Reviews are comments on the product post type, so they are governed by
	 * the same settings as everything else - but a store owner does not think
	 * of them that way, and looking for "reviews" in a list of post types
	 * finds nothing. This is the answer in their language.
	 *
	 * Deliberately role-independent: the Abilities schema promises that every
	 * field except excluded_for_current_user describes the site's
	 * configuration, and this ability is only callable by settings-capable
	 * users, who are exactly the ones a role exemption usually covers. Asking
	 * "are reviews off?" must not answer "not for you".
	 *
	 * @return array {
	 *     @type bool   $woocommerce_active  True when WooCommerce is running.
	 *     @type bool   $reviews_disabled    True when customers cannot leave a review.
	 *     @type bool   $reviews_allowlisted True when existing reviews stay readable.
	 *     @type bool   $reviews_blocklisted True when the review type is closed on its own,
	 *                                       leaving other comments on products open.
	 *     @type string $disabled_by         'woocommerce', 'disable-comments', or ''.
	 * }
	 */
	public function get_product_review_status() {
		if (!$this->is_woocommerce_active()) {
			return array(
				'woocommerce_active'  => false,
				'reviews_disabled'    => false,
				'reviews_allowlisted' => false,
				'reviews_blocklisted' => false,
				'disabled_by'         => '',
			);
		}

		// WooCommerce has its own switch. With it off there are no reviews to
		// leave no matter what this plugin says, and reporting them "enabled"
		// would be simply false.
		$wc_enabled = ('yes' === get_option('woocommerce_enable_reviews', 'yes'));

		// The blocklist closes the review type by name, without closing the
		// product. It is the only one of these three that leaves other comments
		// on products working, but for the question this function answers -
		// "can a customer leave a review?" - it counts exactly like the others.
		$blocklisted = $this->is_comment_type_blocked($this->get_review_comment_type());

		$ours = $blocklisted
			|| $this->is_remove_everywhere_configured()
			|| in_array($this->get_product_post_type(), (array) $this->get_disabled_post_types(), true);

		// The allowlist keeps allowlisted types readable and permits them
		// through REST. It does NOT reopen the comment form: comments_open()
		// is one boolean per post, so a product with comments disabled has no
		// review form regardless of the allowlist. Reporting these separately
		// is the only honest thing to do.
		$allowlisted = in_array($this->get_review_comment_type(), (array) $this->get_allowed_comment_types_list(), true);

		if (!$wc_enabled) {
			$disabled_by = 'woocommerce';
		} elseif ($ours) {
			$disabled_by = 'disable-comments';
		} else {
			$disabled_by = '';
		}

		return array(
			'woocommerce_active'  => true,
			'reviews_disabled'    => (bool) (!$wc_enabled || $ours),
			'reviews_allowlisted' => (bool) $allowlisted,
			'reviews_blocklisted' => (bool) $blocklisted,
			'disabled_by'         => $disabled_by,
		);
	}

	public function get_available_comment_type_options() {
		// Predefined known special comment types with descriptive labels
		// These are shown even if no comments of these types exist yet in the database
		//
		// Note: WordPress does not have a formal comment type registration API,
		// so this list is maintained manually based on common plugin usage.
		$known_types = array(
			'note' => __('Notes - WordPress 6.9+ (note)', 'disable-comments'),
		);

		// Offer reviews before any exist. Discovery is driven off rows already
		// in the comments table, so a new store could not choose to keep
		// reviews working until somebody had already left one.
		if ($this->is_woocommerce_active()) {
			$known_types[$this->get_review_comment_type()] = __('Product reviews - WooCommerce (review)', 'disable-comments');
		}

		/**
		 * Filter the list of known comment types shown in the "Enable Certain Comment Types" UI
		 *
		 * Plugins can add their own comment types to this list so users can enable them
		 * even before any comments of those types exist in the database.
		 *
		 * Example:
		 *   add_filter( 'disable_comments_known_comment_types', function( $types ) {
		 *       $types['my_custom_type'] = __( 'My Custom Comment Type', 'my-plugin' );
		 *       return $types;
		 *   } );
		 *
		 * @param array $known_types Associative array of comment_type => label
		 */
		return apply_filters('disable_comments_known_comment_types', $known_types);
	}

	/**
	 * Check if a REST API request is for an allowed comment type
	 *
	 * @param WP_REST_Request $request The REST API request object
	 * @return bool True if the request is for an allowed comment type, false otherwise
	 */
	private function is_allowed_comment_type_request($request = null) {
		$comment_type = null;

		// Check if we have a request object
		if (!$request) {
			// Check global $_REQUEST for type parameter
			if (isset($_REQUEST['type'])) {
				$comment_type = sanitize_text_field(wp_unslash($_REQUEST['type']));
			}
			// Check if we're in a REST API context
			elseif (defined('REST_REQUEST') && REST_REQUEST) {
				global $wp;
				if (isset($wp->query_vars['type'])) {
					$comment_type = sanitize_text_field($wp->query_vars['type']);
				}
			}
		} else {
			// Check the request object for type parameter
			$type = $request->get_param('type');
			if ($type) {
				$comment_type = $type;
			}

			// Check the request body for type parameter (for POST requests)
			if (!$comment_type) {
				$body = $request->get_body_params();
				if (isset($body['type'])) {
					$comment_type = $body['type'];
				}
			}

			// Check JSON body for type parameter
			if (!$comment_type) {
				$json = $request->get_json_params();
				if (isset($json['type'])) {
					$comment_type = $json['type'];
				}
			}

			// For UPDATE requests (PUT/PATCH), check if the existing comment is an allowed type
			// WordPress doesn't send the type parameter when updating, only the ID and content
			if (!$comment_type) {
				$comment_id = $request->get_param('id');
				if ($comment_id) {
					$comment = get_comment($comment_id);
					if ($comment && isset($comment->comment_type)) {
						$comment_type = $comment->comment_type;
					}
				}
			}

			// For DELETE requests, extract comment ID from the route path
			// The comment ID is only in the URL (e.g., /wp/v2/comments/123), not in request params
			if (!$comment_type && $request->is_method('DELETE')) {
				$route_parts = explode('/', $request->get_route());
				$comment_id = end($route_parts);

				// Ensure we have a numeric comment ID
				if (is_numeric($comment_id)) {
					$comment = get_comment((int) $comment_id);
					if ($comment && isset($comment->comment_type)) {
						$comment_type = $comment->comment_type;
					}
				}
			}
		}

		// Check if the comment type is in the allowlist
		if ($comment_type && $this->is_comment_type_allowed($comment_type)) {
			return true;
		}

		return false;
	}

	/**
	 * Issue a 403 for all comment feed requests.
	 */
	public function filter_query() {
		if (is_comment_feed()) {
			wp_die(esc_html__('Comments are closed.', 'disable-comments'), '', array('response' => 403));
		}
	}

	/**
	 * Remove comment links from the admin bar.
	 */
	public function filter_admin_bar() {
		if (is_admin_bar_showing()) {
			// Remove comments links from admin bar.
			remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
			if (is_multisite()) {
				add_action('admin_bar_menu', array($this, 'remove_network_comment_links'), 500);
			}
		}
	}

	/**
	 * Remove the comments endpoint for the REST API
	 * But allow WordPress 6.9+ block notes (type=note) to work
	 */
	public function filter_rest_endpoints($endpoints) {
		// Don't remove endpoints entirely - instead we'll use permission callbacks
		// and other filters to block regular comments while allowing notes

		// We still need to add a filter to block non-note requests
		// This is handled by rest_pre_dispatch filter added in init_filters

		return $endpoints;
	}

	/**
	 * Filter REST API comment requests to block comments except allowed types
	 *
	 * @param mixed $result Response to replace the requested version with
	 * @param WP_REST_Server $server Server instance
	 * @param WP_REST_Request $request Request used to generate the response
	 * @return mixed
	 */
	/**
	 * May a per-post rule reopen REST comment access?
	 *
	 * Only when rules are active AND the block is coming from the global
	 * toggle or the post-type list. If the administrator explicitly ticked
	 * "Disable comments via REST API", that is a statement about the API
	 * rather than about any particular post, and no exception overrides it.
	 *
	 * @return bool
	 */
	private function rest_blocking_is_conditional() {
		if (!$this->has_conditional_rules()) {
			return false;
		}

		return empty($this->options['remove_rest_API_comments']);
	}

	/**
	 * Which post is a comment REST request about?
	 *
	 * Returns 0 when it cannot be determined - a listing request, say - so the
	 * caller falls back to the site-wide decision rather than guessing.
	 *
	 * @param WP_REST_Request $request          The request.
	 * @param array|object    $prepared_comment Prepared comment, when there is one.
	 * @return int Post id, or 0.
	 */
	private function get_request_post_id($request, $prepared_comment = null) {
		if (is_array($prepared_comment) && !empty($prepared_comment['comment_post_ID'])) {
			return (int) $prepared_comment['comment_post_ID'];
		}

		if (is_object($prepared_comment) && !empty($prepared_comment->comment_post_ID)) {
			return (int) $prepared_comment->comment_post_ID;
		}

		if (!is_object($request) || !method_exists($request, 'get_param')) {
			return 0;
		}

		$post_id = $request->get_param('post');

		if (is_scalar($post_id) && (int) $post_id > 0) {
			return (int) $post_id;
		}

		// Item routes - GET/PUT/DELETE /wp/v2/comments/<id> - carry an id and
		// no post, so this used to answer 0 and the caller rejected them even
		// when an "enable" rule keeps that comment's post open. Reading or
		// editing a comment on a post the site has deliberately reopened has
		// to work, or the exception only half exists.
		//
		// This can only widen what a rule already permits: the caller still
		// asks is_disabled_for_post() about whatever post comes back.
		$comment_id = $request->get_param('id');

		if (is_scalar($comment_id) && (int) $comment_id > 0) {
			$comment = get_comment((int) $comment_id);

			if ($comment && !empty($comment->comment_post_ID)) {
				return (int) $comment->comment_post_ID;
			}
		}

		return 0;
	}

	/**
	 * Did this REST request try to change something?
	 *
	 * Written as "not a read" rather than as a list of write verbs, so a
	 * method nobody thought of counts rather than silently not counting. GET,
	 * HEAD and OPTIONS are the safe methods HTTP defines; everything else -
	 * POST, PUT, PATCH, DELETE - is an attempt to write. DELETE is included
	 * deliberately: a refused attempt to delete a comment is still an attempt
	 * the plugin turned away.
	 *
	 * Anything that is not a request object counts, so a caller that cannot be
	 * inspected is treated the way this filter treated everything before.
	 *
	 * @since 2.9.1
	 * @param WP_REST_Request|mixed $request The request being dispatched.
	 * @return bool
	 */
	private function is_rest_write_request($request) {
		if (!is_object($request) || !method_exists($request, 'get_method')) {
			return true;
		}

		$method = strtoupper((string) $request->get_method());

		return !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true);
	}

	public function filter_rest_comment_dispatch($result, $server, $request) {
		// Somebody upstream already rejected this - authentication, rate
		// limiting, a security plugin. WordPress keeps running later filters
		// anyway, so without this we would both replace their error with ours
		// and count their rejection as one of our blocks.
		if (is_wp_error($result)) {
			return $result;
		}

		// Only filter comment-related routes
		$route = $request->get_route();
		if (strpos($route, '/wp/v2/comments') === false) {
			return $result;
		}

		// Allow requests for comment types in the allowlist to pass through
		if ($this->is_allowed_comment_type_request($request)) {
			return $result;
		}

		// Likewise for a post an exception rule keeps open - but again only
		// when the dedicated REST toggle is not what is doing the blocking.
		if ($this->rest_blocking_is_conditional()) {
			$post_id = $this->get_request_post_id($request);
			if ($post_id && !$this->is_disabled_for_post($post_id)) {
				return $result;
			}
		}

		// Counted only when something tried to WRITE. The block below still
		// covers reads - that part is unchanged - but a blocked GET is not an
		// "attempt" in the sense the counter is read: an admin looks at that
		// number as spam pressure, and a crawler or uptime check polling
		// /wp/v2/comments would pad it with traffic that never tried to leave
		// a comment. It also handed an unauthenticated caller a way to force a
		// database write on every request, since a non-empty tally is flushed
		// on shutdown.
		if ($this->is_rest_write_request($request)) {
			$this->count_blocked_attempt('rest');
		}

		// Block all other comment requests
		return new WP_Error(
			'rest_comment_disabled',
			__('Comments are disabled.', 'disable-comments'),
			array('status' => 403)
		);
	}

	/**
	 * Filter comment queries in REST API to allow only allowed comment types
	 *
	 * @param array $prepared_args Array of arguments for WP_Comment_Query
	 * @param WP_REST_Request $request The REST API request
	 * @return array
	 */
	public function filter_rest_comment_query($prepared_args, $request) {
		// If this is a request for an allowed comment type, allow it
		if ($this->is_allowed_comment_type_request($request)) {
			return $prepared_args;
		}

		// A post an exception keeps open must return its comments here too.
		// The pre-dispatch filter already lets the request through; forcing an
		// empty result set afterwards would hand back a 200 with nothing in it,
		// which is a subtler kind of wrong than a 403.
		if ($this->rest_blocking_is_conditional()) {
			$post_id = $this->get_request_post_id($request);
			if ($post_id && !$this->is_disabled_for_post($post_id)) {
				return $prepared_args;
			}
		}

		// For non-allowed requests, return empty results
		// by setting an impossible condition
		$prepared_args['comment__in'] = array(0);

		return $prepared_args;
	}

	/**
	 * Determines if scripts should be enqueued
	 */
	public function filter_gutenberg_blocks($hook) {
		global $post;
		if ($this->is_remove_everywhere() || (isset($post->post_type) && $this->is_post_type_disabled($post->post_type))) {
			return $this->disable_comments_script();
		}
	}

	/**
	 * Enqueues scripts
	 */
	public function disable_comments_script() {
		wp_enqueue_script('disable-comments-gutenberg', plugin_dir_url(__FILE__) . 'assets/js/disable-comments.js', array(), DC_VERSION, true);
	}

	/**
	 * Enqueues Scripts for Settings Page
	 */
	public function settings_page_assets($hook_suffix) {
		// The review prompt is not a settings-page feature. review_prompt() is
		// hooked to admin_notices AND network_admin_notices, and renders on
		// every screen get_own_screen_ids() covers - the Tools page and the
		// -network variants included. Its dismiss handler used to ship only
		// inside the settings bundle below, so on those other screens nothing
		// listened: "No thanks", the click-through and the notice's own X all
		// appeared to work and the prompt returned on the next page load.
		//
		// Keyed off should_show_review_prompt() rather than the hook suffix, so
		// the script loads exactly where the notice does and nowhere else. It
		// runs before admin_notices and reads the same option and user meta, so
		// the two cannot disagree within a request.
		if ($this->should_show_review_prompt()) {
			wp_enqueue_script(
				'disable-comments-review-prompt',
				DC_ASSETS_URI . 'js/review-prompt.js',
				array('jquery'),
				DC_VERSION,
				true
			);
		}

		if (
			$hook_suffix === 'settings_page_' . DC_PLUGIN_SLUG ||
			$hook_suffix === 'options-general_' . DC_PLUGIN_SLUG
		) {
			// css
			wp_enqueue_style('sweetalert2', DC_ASSETS_URI . 'css/sweetalert2.min.css', [], DC_VERSION);
			// wp_enqueue_style('pagination',  DC_ASSETS_URI . 'css/pagination.css', [], false);
			wp_enqueue_style('disable-comments-style', DC_ASSETS_URI . 'css/style.css', [], DC_VERSION);
			wp_enqueue_style('select2', DC_ASSETS_URI . 'css/select2.min.css', [], DC_VERSION);
			// js
			wp_enqueue_script('sweetalert2', DC_ASSETS_URI . 'js/sweetalert2.all.min.js', array('jquery'), DC_VERSION, true);
			wp_enqueue_script('pagination', DC_ASSETS_URI . 'js/pagination.min.js', array('jquery'), DC_VERSION, true);
			wp_enqueue_script('select2', DC_ASSETS_URI . 'js/select2.min.js', array('jquery'), DC_VERSION, true);
			wp_enqueue_script('disable-comments-scripts', DC_ASSETS_URI . 'js/disable-comments-settings-scripts.js', array('jquery', 'select2', 'pagination', 'sweetalert2', 'wp-i18n'), DC_VERSION, true);
			wp_localize_script(
				'disable-comments-scripts',
				'disableCommentsObj',
				array(
					'save_action'      => 'disable_comments_save_settings',
					'delete_action'    => 'disable_comments_delete_comments',
					'settings_URI'     => $this->settings_page_url(),
					'_nonce'           => wp_create_nonce('disable_comments_save_settings'),
					'is_network_admin' => is_network_admin() ? '1' : '0',
				)
			);
			wp_set_script_translations('disable-comments-scripts', 'disable-comments');
		} else {
			// notice css
			wp_enqueue_style('disable-comments-notice', DC_ASSETS_URI . 'css/notice.css', [], DC_VERSION);
		}
	}

	/**
	 * Remove comment links from the admin bar in a multisite network.
	 */
	public function remove_network_comment_links($wp_admin_bar) {
		if ($this->networkactive && is_user_logged_in()) {
			foreach ((array) $wp_admin_bar->user->blogs as $blog) {
				$wp_admin_bar->remove_menu('blog-' . $blog->userblog_id . '-c');
			}
		} else {
			// We have no way to know whether the plugin is active on other sites, so only remove this one.
			$wp_admin_bar->remove_menu('blog-' . get_current_blog_id() . '-c');
		}
	}

	public function discussion_notice() {
		$disabled_post_types = $this->get_disabled_post_types();
		if (get_current_screen()->id == 'options-discussion' && !empty($disabled_post_types)) {
			$names_escaped = array();
			foreach ($disabled_post_types as $type) {
				$names_escaped[$type] = esc_html(get_post_type_object($type)->labels->name);
			}

			// translators: %s: disabled post types.
			echo '<div class="notice notice-warning"><p>' . sprintf(esc_html__('Note: The <em>Disable Comments</em> plugin is currently active, and comments are completely disabled on: %s. Many of the settings below will not be applicable for those post types.', 'disable-comments'), implode(esc_html__(', ', 'disable-comments'), $names_escaped)) . '</p></div>';
		}
	}

	/**
	 * Return context-aware settings page URL
	 */
	private function settings_page_url() {
		$base = $this->networkactive && is_network_admin() ? network_admin_url('settings.php') : admin_url('options-general.php');
		return add_query_arg('page', DC_PLUGIN_SLUG, $base);
	}

	/**
	 * Return context-aware tools page URL
	 */
	private function tools_page_url() {
		$base = $this->networkactive && is_network_admin() ? network_admin_url('settings.php') : admin_url('tools.php');
		return add_query_arg('page', 'disable_comments_tools', $base);
	}


	public function setup_notice() {
		$current_screen = get_current_screen()->id;
		if (!in_array($current_screen, ['dashboard-network', 'dashboard'])) {
			return;
		}
		$hascaps = $this->networkactive && is_network_admin() ? current_user_can('manage_network_plugins') : current_user_can('manage_options');
		if ($this->networkactive && !is_network_admin() && !$this->options['sitewide_settings']) {
			$hascaps = false;
		}
		if ($hascaps) {
			$this->setup_notice_flag = true;
			// translators: %s: URL to Disabled Comment settings page.
			$html = sprintf(__('The <strong>Disable Comments</strong> plugin is active, but isn\'t configured to do anything yet. Visit the <a href="%s">configuration page</a> to choose which post types to disable comments on.', 'disable-comments'), esc_attr($this->settings_page_url()));
			// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
			echo wp_kses_post('<div class="notice dc-text__block disable__comment__alert mb30"><img height="30" src="' . esc_url(DC_ASSETS_URI . 'img/icon-logo.png') . '" alt=""><p>' . $html . '</p></div>');
		}
	}

	public function filter_admin_menu() {
		global $pagenow;

		if (empty($this->options['show_existing_comments'])) {
			if ($pagenow == 'comment.php' || $pagenow == 'edit-comments.php') {
				wp_die(esc_html__('Comments are closed.', 'disable-comments'), '', array('response' => 403));
			}

			remove_menu_page('edit-comments.php');
		}

		if (!$this->discussion_settings_allowed()) {
			if ($pagenow == 'options-discussion.php') {
				wp_die(esc_html__('Comments are closed.', 'disable-comments'), '', array('response' => 403));
			}

			remove_submenu_page('options-general.php', 'options-discussion.php');
		}
	}

	public function filter_dashboard() {
		remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
	}

	public function admin_css() {
		echo '<style>
			#dashboard_right_now .comment-count,
			#dashboard_right_now .comment-mod-count,
			#latest-comments,
			#welcome-panel .welcome-comments,
			.user-comment-shortcuts-wrap {
				display: none !important;
			}
		</style>';
	}

	public function filter_existing_comments($comments, $post_id) {
		$comments_disabled = $this->is_disabled_for_post($post_id);

		// If comments are disabled but show_existing_comments is enabled, return existing comments
		if ($comments_disabled && !empty($this->options['show_existing_comments'])) {
			$comments_disabled = false;
		}

		// If comments are disabled, filter out regular comments but keep allowed comment types
		if ($comments_disabled && !empty($comments)) {
			$filtered_comments = array();
			foreach ($comments as $comment) {
				// Keep comment types that are in the allowlist even when comments are disabled
				if (isset($comment->comment_type) && $this->is_comment_type_allowed($comment->comment_type)) {
					$filtered_comments[] = $comment;
				}
			}
			return $filtered_comments;
		}

		// Default behavior: return all comments if not disabled
		return $comments;
	}

	public function filter_comment_status($open, $post_id) {
		return ($this->is_disabled_for_post($post_id) ? false : $open);
	}

	public function filter_comments_number($count, $post_id) {
		$comments_disabled = $this->is_disabled_for_post($post_id);

		// If comments are disabled but show_existing_comments is enabled, return actual count
		if ($comments_disabled && !empty($this->options['show_existing_comments'])) {
			return $count;
		}

		// If comments are disabled but there are allowed comment types, count only those types
		if ($comments_disabled && $this->has_allowed_comment_types()) {
			return $this->count_allowed_comment_types($post_id);
		}

		return $comments_disabled ? 0 : $count;
	}

	/**
	 * Count comments of allowed types for a specific post
	 *
	 * @param int $post_id The post ID
	 * @return int The count of comments matching allowed types
	 */
	private function count_allowed_comment_types($post_id) {
		$allowed_types = $this->get_allowed_comment_types();
		if (empty($allowed_types)) {
			return 0;
		}

		$comments = get_comments(array(
			'post_id' => $post_id,
			'type__in' => $allowed_types,
			'status' => 'approve',
			'count' => true,
		));

		return (int) $comments;
	}

	public function disable_rc_widget() {
		unregister_widget('WP_Widget_Recent_Comments');
		/**
		 * The widget has added a style action when it was constructed - which will
		 * still fire even if we now unregister the widget... so filter that out
		 */
		add_filter('show_recent_comments_widget_style', '__return_false');
	}

	public function set_plugin_meta($links, $file) {
		static $plugin;
		$plugin = plugin_basename(__FILE__);
		if ($file == $plugin) {
			$links[] = '<a href="https://github.com/WPDevelopers/disable-comments">GitHub</a>';
		}
		return $links;
	}

	/**
	 * Add links to Settings page
	 */
	public function plugin_actions_links($links, $file) {
		static $plugin;
		$plugin = plugin_basename(__FILE__);
		if ($file == $plugin && current_user_can('manage_options')) {
			array_unshift(
				$links,
				sprintf('<a href="%s">%s</a>', esc_attr($this->settings_page_url()), __('Settings', 'disable-comments')),
				sprintf('<a href="%s">%s</a>', esc_attr($this->tools_page_url()), __('Tools', 'disable-comments'))
			);
		}

		return $links;
	}

	public function settings_menu() {
		$title = _x('Disable Comments', 'settings menu title', 'disable-comments');
		if ($this->networkactive && is_network_admin()) {
			add_submenu_page('settings.php', $title, $title, 'manage_network_plugins', DC_PLUGIN_SLUG, array($this, 'settings_page'));
		} elseif (!$this->networkactive || $this->options['sitewide_settings']) {
			add_submenu_page('options-general.php', $title, $title, 'manage_options', DC_PLUGIN_SLUG, array($this, 'settings_page'));
		}
	}

	public function tools_menu() {
		$title = __('Delete Comments', 'disable-comments');
		$hook = '';
		if ($this->networkactive && is_network_admin()) {
			$hook = add_submenu_page('settings.php', $title, $title, 'manage_network_plugins', 'disable_comments_tools', array($this, 'tools_page'));
		} elseif (!$this->networkactive || $this->options['sitewide_settings']) {
			$hook = add_submenu_page('tools.php', $title, $title, 'manage_options', 'disable_comments_tools', array($this, 'tools_page'));
		}
		add_action('load-' . $hook, array($this, 'redirectToMainSettingsPage'));
	}

	public function redirectToMainSettingsPage() {
		wp_safe_redirect($this->settings_page_url() . '#delete');
		exit;
	}

	public function get_all_comments_number() {
		global $wpdb;
		if (is_network_admin() && function_exists('get_sites') && class_exists('WP_Site_Query')) {
			$count = 0;
			$sites = get_sites([
				'number' => 0,
				'fields' => 'ids',
			]);
			foreach ($sites as $blog_id) {
				switch_to_blog($blog_id);
				$count += $this->__get_comment_count();
				restore_current_blog();
			}
			return $count;
		} else {
			return $this->__get_comment_count();
		}
	}

	public function get_all_comment_types($exclude_allowed = true) {
		if ($this->networkactive && is_network_admin() && function_exists('get_sites')) {
			$comment_types = [];
			$sites = get_sites([
				'number' => 0,
				'fields' => 'ids',
			]);
			foreach ($sites as $blog_id) {
				switch_to_blog($blog_id);
				$comment_types = array_merge($this->_get_all_comment_types($exclude_allowed), $comment_types);
				restore_current_blog();
			}
			return $comment_types;
		} else {
			return $this->_get_all_comment_types($exclude_allowed);
		}
	}
	public function _get_all_comment_types($exclude_allowed = true) {
		global $wpdb;
		$commenttypes = array();
		// we need fresh data in every call.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- We need to count comments across multiple sites
		$commenttypes_query = $wpdb->get_results("SELECT DISTINCT comment_type FROM $wpdb->comments", ARRAY_A);
		if (!empty($commenttypes_query) && is_array($commenttypes_query)) {
			foreach ($commenttypes_query as $entry) {
				$value = $entry['comment_type'];
				// Exclude comment types that are in the allowlist from deletable comment types
				// These are protected and should not appear in the "Delete Certain Comment Types" interface
				if ($exclude_allowed && $this->is_comment_type_allowed($value)) {
					continue;
				}
				if ('' === $value) {
					$commenttypes['default'] = __('Default (no type)', 'disable-comments');
				} elseif ($this->is_woocommerce_active() && $this->get_review_comment_type() === $value) {
					// "Review (review)" tells a store owner nothing about what
					// they are about to delete.
					$commenttypes[$value] = __('Product reviews - WooCommerce (review)', 'disable-comments');
				} else {
					$commenttypes[$value] = ucwords(str_replace('_', ' ', $value)) . ' (' . $value . ')';
				}
			}
		}
		return $commenttypes;
	}

	public function get_all_post_types($network = false) {
		$typeargs = array('public' => true);
		if ($network || $this->networkactive && is_network_admin()) {
			$typeargs['_builtin'] = true;   // stick to known types for network.
		}
		$types = get_post_types($typeargs, 'objects');
		foreach (array_keys($types) as $type) {
			if (!in_array($type, $this->modified_types) && !post_type_supports($type, 'comments')) {   // the type doesn't support comments anyway.
				unset($types[$type]);
			}
		}
		return $types;
	}

	public function get_roles($selected) {
		$roles = [
			[
				"id" => 'logged-out-users',
				"text" => __('Logged out users', 'disable-comments'),
				"selected" => in_array('logged-out-users', (array) $selected),
			]
		];
		$editable_roles = array_reverse(get_editable_roles());
		foreach ($editable_roles as $role => $details) {
			$roles[] = [
				"id" => esc_attr($role),
				"text" => esc_html(translate_user_role($details['name'])),
				"selected" => in_array($role, (array) $selected),
			];
		}
		return $roles;
	}

	public function tools_page() {
		return;
	}

	public function settings_page() {
		// Belt-and-suspenders: add_submenu_page already gates on capability,
		// but verify here too so a direct URL request can never render the page.
		$required_cap = $this->networkactive && is_network_admin() ? 'manage_network_plugins' : 'manage_options';
		if (!current_user_can($required_cap)) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'disable-comments'), 403);
		}

		$avatar_status = '-1';
		if ($this->can_network_admin_ajax_context()) {
			$show_avatars = [];
			$sites = get_sites([
				'number' => 0,
				'fields' => 'ids',
			]);
			foreach ($sites as $blog_id) {
				switch_to_blog($blog_id);
				$show_avatars[] = (int) get_option('show_avatars', '0');
				restore_current_blog();
			}
			if (count($show_avatars) == array_sum($show_avatars)) {
				$avatar_status = '0';
			} elseif (0 == array_sum($show_avatars)) {
				$avatar_status = '1';
			}
		}

		include_once DC_PLUGIN_VIEWS_PATH . 'settings.php';
	}

	public function get_sub_sites() {
		$nonce = (isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '');
		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json(['data' => [], 'totalNumber' => 0]);
		}
		// Listing subsites is always a network-level operation on multisite —
		// require manage_network_plugins regardless of how the plugin is activated
		// (network-wide or per-site). A per-site admin must never enumerate all
		// network sites. On single-site installs manage_options suffices.
		$required_cap = is_multisite() ? 'manage_network_plugins' : 'manage_options';
		if (!current_user_can($required_cap)) {
			wp_send_json(['data' => [], 'totalNumber' => 0]);
		}

		$_sub_sites = [];
		$type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'disabled';
		$search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
		$pageSize = isset($_GET['pageSize']) ? sanitize_text_field(wp_unslash($_GET['pageSize'])) : 50;
		$pageNumber = isset($_GET['pageNumber']) ? sanitize_text_field(wp_unslash($_GET['pageNumber'])) : 1;
		$offset = ($pageNumber - 1) * $pageSize;
		$sub_sites = get_sites([
			'number' => $pageSize,
			'offset' => $offset,
			'search' => $search,
			'fields' => 'ids',
		]);
		$totalNumber = get_sites([
			// 'number' => $pageSize,
			// 'offset' => $offset,
			'search' => $search,
			'count' => true,
		]);

		if ($type == 'disabled') {
			$disabled_site_options = isset($this->options['disabled_sites']) ? $this->options['disabled_sites'] : [];
		} else { // if($type == 'delete')
			$disabled_site_options = $this->get_disabled_sites(true);
		}

		foreach ($sub_sites as $sub_site_id) {
			$blog = get_blog_details($sub_site_id);
			$is_checked = checked(!empty($disabled_site_options["site_$sub_site_id"]), true, false);
			$_sub_sites[] = [
				'site_id' => $sub_site_id,
				'is_checked' => $is_checked,
				'blogname' => $blog->blogname,
			];
		}
		wp_send_json(['data' => $_sub_sites, 'totalNumber' => $totalNumber]);
	}

	/**
	 * AJAX: terms for one taxonomy, for the conditional-rules term picker.
	 *
	 * Reads nothing a user with manage_options cannot already see on the
	 * taxonomy screens, but it is gated all the same - an endpoint that
	 * enumerates every term on the site is not something to leave open.
	 */
	public function get_taxonomy_terms() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		$taxonomy = (isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '');
		// sanitize_text_field(), not sanitize_key(): this is matched against
		// term names, which have spaces, accents and capitals in them.
		$search   = (isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '');
		$page     = (isset($_POST['page']) ? (int) $_POST['page'] : 1);

		wp_send_json_success($this->get_taxonomy_terms_page($taxonomy, $search, $page));
	}

	/**
	 * One page of terms for the conditional-rules picker.
	 *
	 * The picker used to be handed the taxonomy's first 200 terms and nothing
	 * else. On a shop with a few thousand product categories that meant every
	 * term after those 200 was simply unreachable for a new rule - visible on
	 * the taxonomy screen, listed by the REST API, and absent from the one
	 * control that needed it. Raising the number would have moved the ceiling
	 * rather than removed it, so the picker searches and pages instead.
	 *
	 * Kept apart from the AJAX wrapper above so the query can be exercised
	 * directly; the wrapper owns the nonce and capability checks.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $search   Optional. Fragment to match against term names.
	 * @param int    $page     Optional. 1-based page of results.
	 * @return array {
	 *     @type string $taxonomy The taxonomy answered for. Echoed back because
	 *                            two quick changes of taxonomy can land out of
	 *                            order, and terms rendered under the wrong one
	 *                            save as term IDs that do not belong to it.
	 *     @type array  $terms    List of array('id' => int, 'name' => string).
	 *     @type bool   $more     Whether a further page exists.
	 * }
	 */
	public function get_taxonomy_terms_page($taxonomy, $search = '', $page = 1) {
		$empty = array(
			'taxonomy' => (string) $taxonomy,
			'terms'    => array(),
			'more'     => false,
		);

		if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
			return $empty;
		}

		$page = max(1, (int) $page);

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			// id=>name, because nothing here needs a WP_Term: on a taxonomy
			// with thousands of rows the objects are the expensive part.
			'fields'     => 'id=>name',
			// One row past the page. Whether another page exists is then
			// answered by this query rather than a second counting one.
			'number'     => self::TERM_PAGE_SIZE + 1,
			'offset'     => ($page - 1) * self::TERM_PAGE_SIZE,
		);

		$search = trim((string) $search);
		if ('' !== $search) {
			$args['search'] = $search;
		}

		$terms = get_terms($args);

		if (is_wp_error($terms) || empty($terms)) {
			return $empty;
		}

		$more = (count($terms) > self::TERM_PAGE_SIZE);
		if ($more) {
			array_pop($terms);
		}

		$out = array();
		foreach ($terms as $term_id => $name) {
			$out[] = array(
				'id'   => (int) $term_id,
				'name' => $name,
			);
		}

		return array(
			'taxonomy' => (string) $taxonomy,
			'terms'    => $out,
			'more'     => $more,
		);
	}

	/**
	 * Screens this plugin owns.
	 *
	 * The review prompt renders on these and nowhere else. Two 1-star reviews
	 * in May 2025 were about promotion appearing in the post editor and around
	 * the admin; both were revised to 5 stars once it was pulled. This list is
	 * the guarantee that does not happen again, so keep it exact.
	 *
	 * @return array Screen ids.
	 */
	private function get_own_screen_ids() {
		$ids = array(
			'settings_page_' . DC_PLUGIN_SLUG,
			'options-general_page_' . DC_PLUGIN_SLUG,
			'tools_page_disable_comments_tools',
			'settings_page_disable_comments_tools',
		);

		// On a network-activated install WordPress suffixes screen ids with
		// "-network". Without these a super admin who just cleared comments
		// across the whole network is the one person never asked.
		foreach ($ids as $id) {
			$ids[] = $id . '-network';
		}

		return $ids;
	}

	/**
	 * Are we on one of this plugin's own screens?
	 *
	 * @return bool
	 */
	private function is_own_screen() {
		if (!function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();

		if (!$screen || empty($screen->id)) {
			return false;
		}

		return in_array($screen->id, $this->get_own_screen_ids(), true);
	}

	/**
	 * Note that something worth being pleased about just happened.
	 *
	 * Called after a bulk delete completes. Activation deliberately does not
	 * call this: the user has done nothing yet and has no basis for an opinion.
	 *
	 * @param int $deleted How many comments were removed.
	 */
	public function record_review_trigger($deleted = 0) {
		if ($deleted < 1) {
			return;
		}

		update_option(
			self::REVIEW_TRIGGER_OPTION,
			array(
				'at'      => time(),
				'deleted' => (int) $deleted,
			),
			false
		);
	}

	/**
	 * Should this user see the review prompt right now?
	 *
	 * Every condition here is a reason not to show it. That asymmetry is the
	 * point: the cost of showing this in the wrong place is far higher than
	 * the cost of never showing it at all.
	 *
	 * @return bool
	 */
	public function should_show_review_prompt() {
		/**
		 * Filter whether the review prompt may be shown at all.
		 *
		 * @param bool $show Whether to consider showing the prompt.
		 */
		if (!apply_filters('disable_comments_show_review_prompt', true)) {
			return false;
		}

		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			return false;
		}

		// Our screens only. Never the dashboard, never the post editor, never
		// a site-wide admin notice.
		if (!$this->is_own_screen()) {
			return false;
		}

		// Dismissed means dismissed. Permanently, for this user.
		if (get_user_meta(get_current_user_id(), self::REVIEW_DISMISSED_META, true)) {
			return false;
		}

		$trigger = get_option(self::REVIEW_TRIGGER_OPTION, array());

		// No successful action yet, so there is nothing to be pleased about.
		if (empty($trigger['at']) || empty($trigger['deleted'])) {
			return false;
		}

		return true;
	}

	/**
	 * Render the review prompt.
	 */
	public function review_prompt() {
		if (!$this->should_show_review_prompt()) {
			return;
		}

		$trigger = get_option(self::REVIEW_TRIGGER_OPTION, array());
		$deleted = isset($trigger['deleted']) ? (int) $trigger['deleted'] : 0;

		?>
		<div class="notice notice-info is-dismissible disable-comments-review-prompt"
			id="disable_comments_review_prompt"
			data-nonce="<?php echo esc_attr(wp_create_nonce('disable_comments_save_settings')); ?>">
			<p>
				<?php
				printf(
					/* translators: %s: number of comments deleted. */
					esc_html(_n('Disable Comments just cleared %s comment for you.', 'Disable Comments just cleared %s comments for you.', $deleted, 'disable-comments')),
					'<strong>' . esc_html(number_format_i18n($deleted)) . '</strong>'
				);
				?>
				<?php esc_html_e('If it saved you some time, a quick review helps other people find it.', 'disable-comments'); ?>
			</p>
			<p>
				<a href="https://wordpress.org/support/plugin/disable-comments/reviews/#new-post"
					class="button button-primary"
					target="_blank"
					rel="noopener noreferrer"
					data-dc-review="leave">
					<?php esc_html_e('Leave a review', 'disable-comments'); ?>
				</a>
				<button type="button" class="button-link" data-dc-review="dismiss">
					<?php esc_html_e('No thanks, don\'t ask again', 'disable-comments'); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: record that this user does not want to be asked again.
	 */
	public function dismiss_review_prompt() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		update_user_meta(get_current_user_id(), self::REVIEW_DISMISSED_META, time());

		wp_send_json_success();
	}

	/**
	 * Hook the self-report into a scanned front-end request.
	 *
	 * Called from init_filters(). Everything here is inert unless the request
	 * carries a token this site issued moments ago, so an ordinary visitor
	 * never touches any of it.
	 */
	public function maybe_arm_scan_probe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset($_GET[self::SCAN_QUERY_ARG]) ? sanitize_text_field(wp_unslash($_GET[self::SCAN_QUERY_ARG])) : '';

		if ('' === $token) {
			return;
		}

		// Keyed by the token itself, so two administrators scanning at once do
		// not overwrite each other's slot and then both fail validation.
		$expected = get_transient(self::SCAN_TOKEN_TRANSIENT . '_' . md5($token));

		// hash_equals: the token is a secret for the lifetime of one scan, and
		// a timing oracle on it is free to avoid.
		if (empty($expected) || !hash_equals((string) $expected, $token)) {
			return;
		}

		// Record what the theme asked for. Our own filter runs at priority 20;
		// this sits later so it sees the final resolved path either way.
		add_filter('comments_template', array($this, 'record_scanned_comments_template'), 999);
		add_action('wp_footer', array($this, 'print_scan_marker'), 9999);
		add_action('shutdown', array($this, 'print_scan_marker'), 9999);
	}

	/**
	 * Remember which comments template was resolved during a scan.
	 *
	 * @param string $template Resolved template path.
	 * @return string Unmodified.
	 */
	public function record_scanned_comments_template($template) {
		$this->scan_report['comments_template'] = (string) $template;
		$this->scan_report['template_called']   = true;

		return $template;
	}

	/**
	 * Emit the scan's findings as an HTML comment.
	 *
	 * Printed from inside the scanned request, which is the only place that
	 * can say whether the theme called comments_template() at all - the thing
	 * the FAQ has been describing in prose for a decade.
	 */
	public function print_scan_marker() {
		if (!empty($this->scan_report['printed'])) {
			return;
		}

		$this->scan_report['printed'] = true;

		$report = array(
			'template_called'   => !empty($this->scan_report['template_called']),
			'comments_template' => isset($this->scan_report['comments_template']) ? $this->scan_report['comments_template'] : '',
			'dummy_used'        => isset($this->scan_report['comments_template'])
				&& $this->scan_report['comments_template'] === $this->dummy_comments_template(),
			'comments_open'     => is_singular() ? (bool) comments_open(get_queried_object_id()) : null,
			// These two make check_comment_template() deliberately leave the
			// real template in place, so comment markup on the page is expected
			// rather than a theme defeating us.
			'preserved'         => !empty($this->options['show_existing_comments']) || $this->has_allowed_comment_types(),
		);

		echo "\n<!--disable-comments-scan:" . wp_json_encode($report) . ":disable-comments-scan-->\n";
	}

	/**
	 * Markup that means a comment UI reached the page.
	 *
	 * Matched against class and id attributes rather than visible text, so it
	 * does not depend on the site's language.
	 *
	 * @return array Signal key => list of needles.
	 */
	private function get_comment_markup_signals() {
		return array(
			// Anchored to a tag boundary and an attribute name. A bare
			// substring search matches ".comment-form" in inline CSS, a
			// selector in inline JS, or the word inside a text node, and would
			// report a co-operating theme as broken.
			'comment_form' => array(
				'/<form[^>]+id=["\']commentform["\']/i',
				'/<form[^>]+class=["\'][^"\']*\bcomment-form\b/i',
				'/<div[^>]+id=["\']respond["\']/i',
			),
			'comment_list' => array(
				'/<(ol|ul)[^>]+class=["\'][^"\']*\bcomment-list\b/i',
				'/<(div|section)[^>]+id=["\']comments["\']/i',
			),
			'reply_script' => array(
				'/<script[^>]+comment-reply(\.min)?\.js/i',
			),
		);
	}

	/**
	 * Strip the parts of a response that are not rendered markup.
	 *
	 * Inline styles and scripts routinely mention .comment-form and
	 * .comment-list. Matching them would make every theme that styles its
	 * comment area look like it was rendering one.
	 *
	 * @param string $body Response body.
	 * @return string Body with script and style blocks removed.
	 */
	private function strip_non_markup($body) {
		$body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body);
		$body = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $body);
		$body = preg_replace('#<!--(?!\s*disable-comments-scan).*?-->#s', '', $body);

		return (string) $body;
	}

	/**
	 * Scan one URL and work out whether the theme is co-operating.
	 *
	 * @param string $url Permalink to scan.
	 * @return array|WP_Error Findings, or an error when the page could not be fetched.
	 */
	public function run_theme_scan($url) {
		$token = wp_generate_password(20, false);
		$slot  = self::SCAN_TOKEN_TRANSIENT . '_' . md5($token);
		set_transient($slot, $token, 2 * MINUTE_IN_SECONDS);

		// A page cache serves identical output no matter what changed, so an
		// un-busted request proves nothing. See CLAUDE.md - a full front-end
		// pass once returned identical results across four different states
		// purely as a cache artefact.
		$request_url = add_query_arg(
			array(
				self::SCAN_QUERY_ARG => $token,
				'dc_cb'              => time(),
			),
			$url
		);

		$response = wp_remote_get(
			$request_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'sslverify'   => false,
				'headers'     => array('Cache-Control' => 'no-cache'),
			)
		);

		delete_transient($slot);

		if (is_wp_error($response)) {
			// Loopback requests are blocked on plenty of hosts. That is a
			// "could not check", never a pass and never a fatal.
			return new WP_Error(
				'dc_scan_unreachable',
				sprintf(
					/* translators: %s: error message from the HTTP request. */
					__('Could not load the front end to check it (%s). Some hosts block a site from requesting its own pages; that is not a problem with your theme.', 'disable-comments'),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);

		if ($code < 200 || $code >= 300) {
			return new WP_Error(
				'dc_scan_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__('The front end returned HTTP %d, so there was nothing to check.', 'disable-comments'),
					$code
				)
			);
		}

		$body = (string) wp_remote_retrieve_body($response);

		$report = array();
		if (preg_match('/<!--disable-comments-scan:(.*?):disable-comments-scan-->/s', $body, $matches)) {
			$decoded = json_decode($matches[1], true);
			if (is_array($decoded)) {
				$report = $decoded;
			}
		}

		$markup = $this->strip_non_markup($body);

		$found = array();
		foreach ($this->get_comment_markup_signals() as $signal => $patterns) {
			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $markup)) {
					$found[] = $signal;
					break;
				}
			}
		}

		$theme = wp_get_theme();

		return array(
			'url'               => $url,
			'reached'           => !empty($report),
			'template_called'   => !empty($report['template_called']),
			'comments_template' => isset($report['comments_template']) ? $report['comments_template'] : '',
			'dummy_used'        => !empty($report['dummy_used']),
			'preserved'         => !empty($report['preserved']),
			'comments_open'     => isset($report['comments_open']) ? $report['comments_open'] : null,
			'markup_found'      => $found,
			'theme'             => $theme ? $theme->get('Name') : '',
			'theme_comments_php' => $this->locate_theme_comments_template(),
			// A cached response is why "nothing I change makes any difference".
			// Surface it rather than letting it silently invalidate the result.
			'x_cache'           => wp_remote_retrieve_header($response, 'x-cache'),
			'http_code'         => $code,
		);
	}

	/**
	 * The active theme's own comments.php, if it has one.
	 *
	 * locate_template() falls back to core's theme-compat copy when the theme
	 * has none, and telling somebody to edit a file in wp-includes is worse
	 * advice than telling them nothing.
	 *
	 * @return string Absolute path, or '' when the theme provides none.
	 */
	private function locate_theme_comments_template() {
		$found = locate_template('comments.php');

		if (empty($found)) {
			return '';
		}

		foreach (array(get_stylesheet_directory(), get_template_directory()) as $dir) {
			if (0 === strpos($found, $dir)) {
				return $found;
			}
		}

		return '';
	}

	/**
	 * Turn raw findings into a verdict and something worth reading.
	 *
	 * @param array $scan Result of run_theme_scan().
	 * @return array Scan plus `verdict` and `message`.
	 */
	public function interpret_theme_scan($scan) {
		$has_markup = !empty($scan['markup_found']);

		if (!$scan['reached']) {
			// Without the marker we cannot tell a co-operating theme from a
			// cached page, so we say so instead of guessing.
			$scan['verdict'] = 'inconclusive';
			$scan['message'] = __('The page loaded, but this plugin could not report from inside it. That usually means a page cache served an older copy. Try again, or clear your cache first.', 'disable-comments');

			return $scan;
		}

		if (false === $scan['comments_open'] && !$has_markup) {
			$scan['verdict'] = 'clean';
			$scan['message'] = __('Comments are off on this page and your theme is respecting that. Nothing to fix.', 'disable-comments');

			return $scan;
		}

		// "Show existing comments" and the comment-type allowlist both tell
		// this plugin to leave the real template alone, so comment markup here
		// is the setting working - not the theme defeating it. Saying otherwise
		// would send someone editing their theme to fix something they asked
		// for.
		if (!empty($scan['preserved'])) {
			$scan['verdict'] = 'preserved_by_setting';
			$scan['message'] = __('Comments are off on this page, but you have chosen to keep existing comments (or certain comment types) visible — so the comment area is still rendered on purpose. If you did not expect that, check "Show Existing Comments" and "Enable Certain Comment Types" in the settings.', 'disable-comments');

			return $scan;
		}

		if (true === $scan['comments_open']) {
			$scan['verdict'] = 'not_disabled';
			$scan['message'] = __('Comments are still open on this page, so there is nothing for the theme to hide yet. Check the settings above before reading anything into this.', 'disable-comments');

			return $scan;
		}

		// Comments are closed and the theme rendered a comment UI anyway.
		if (!$scan['template_called']) {
			$scan['verdict'] = 'theme_ignores_template';
			$scan['message'] = sprintf(
				/* translators: 1: theme name, 2: template file path. */
				__('%1$s never calls comments_template(), so this plugin has no way to replace what it renders. The comment markup is being output directly by the theme. Look in %2$s, or in the single-post template that draws the comment area.', 'disable-comments'),
				$scan['theme'],
				$scan['theme_comments_php'] ? $scan['theme_comments_php'] : 'the theme\'s template files'
			);

			return $scan;
		}

		if (!$scan['dummy_used']) {
			$scan['verdict'] = 'template_overridden';
			$scan['message'] = sprintf(
				/* translators: 1: theme name, 2: resolved template path. */
				__('%1$s calls comments_template(), but something is overriding the empty template this plugin substitutes. The template actually rendered was %2$s.', 'disable-comments'),
				$scan['theme'],
				$scan['comments_template']
			);

			return $scan;
		}

		$scan['verdict'] = 'markup_outside_template';
		$scan['message'] = sprintf(
			/* translators: %s: theme name. */
			__('This plugin replaced the comments template successfully, but comment markup is still on the page — so %s is drawing part of the comment area outside comments_template(). That part has to be removed in the theme.', 'disable-comments'),
			$scan['theme']
		);

		return $scan;
	}

	/**
	 * How much a verdict matters, for picking which page to report.
	 *
	 * @param string $verdict Verdict key.
	 * @return int Higher is more serious.
	 */
	private function scan_verdict_rank($verdict) {
		$order = array(
			'clean'                   => 0,
			'preserved_by_setting'    => 1,
			'not_disabled'            => 2,
			'inconclusive'            => 3,
			'markup_outside_template' => 4,
			'template_overridden'     => 5,
			'theme_ignores_template'  => 6,
		);

		return isset($order[$verdict]) ? $order[$verdict] : 0;
	}

	/**
	 * A published post the plugin currently closes comments on.
	 *
	 * Scanning a page where comments are open would prove nothing, so the
	 * scanner picks a target rather than trusting whatever it is handed.
	 *
	 * @return int|false Post id, or false when there is nothing suitable.
	 */
	public function find_scan_target() {
		$targets = $this->find_scan_targets();

		return empty($targets) ? false : $targets[0];
	}

	/**
	 * One published post per disabled post type.
	 *
	 * Scanning a single arbitrary post is how a scanner reports "clean" while
	 * the page the user is actually complaining about is broken: themes
	 * commonly hand different post types to different templates, and only one
	 * of them may be drawing its own comment area. One representative per
	 * disabled type is the smallest set that cannot miss that.
	 *
	 * @param int $limit Maximum number of posts to return.
	 * @return array Post ids.
	 */
	public function find_scan_targets($limit = 5) {
		$disabled = $this->get_disabled_post_types();

		if ($this->is_remove_everywhere()) {
			$disabled = array_keys($this->get_all_post_types());
		}

		if (empty($disabled)) {
			// A rules-only site has nothing disabled by type, so the scanner
			// used to answer "no disabled content" to the very people most
			// likely to need it — someone whose taxonomy or age rule closed a
			// post and whose theme still draws a comment form on it.
			//
			// Only reached where the old code returned an empty list, so no
			// configuration that works today changes behaviour. The candidate
			// pool is capped because this walks posts rather than types.
			if (!$this->has_conditional_rules()) {
				return array();
			}

			$targets = array();

			// From both ends of the archive, not just the newest posts.
			// get_posts() defaults to newest-first, and an age rule closes the
			// OLDEST posts: a site with fifty posts inside the age window
			// returned fifty posts none of which were closed, so the scanner
			// reported nothing to scan while every older post was shut. A
			// taxonomy or template rule can match anywhere, which the
			// newest-first batch samples. Keyed by id so a small site does not
			// examine the same post twice.
			$candidates = array();

			foreach (array('DESC', 'ASC') as $order) {
				$batch = get_posts(
					array(
						'post_type'        => array_keys($this->get_all_post_types()),
						'post_status'      => 'publish',
						'numberposts'      => 50,
						'orderby'          => 'date',
						'order'            => $order,
						'suppress_filters' => false,
					)
				);

				foreach ($batch as $post) {
					$candidates[(int) $post->ID] = $post;
				}
			}

			foreach ($candidates as $candidate) {
				if (count($targets) >= $limit) {
					break;
				}

				if ($this->is_disabled_for_post($candidate->ID)) {
					$targets[] = (int) $candidate->ID;
				}
			}

			return $targets;
		}

		$targets = array();

		foreach (array_values($disabled) as $post_type) {
			if (count($targets) >= $limit) {
				break;
			}

			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'suppress_filters' => false,
				)
			);

			if (!empty($posts)) {
				$targets[] = (int) $posts[0]->ID;
			}
		}

		return $targets;
	}

	/**
	 * AJAX: run the theme conflict scan.
	 */
	public function scan_theme_conflict() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		$is_network_ctx = $this->is_network_admin_ajax_context();

		// The scan makes the site request its own pages. Gate it like the rest
		// of this plugin's admin actions.
		if ($is_network_ctx) {
			if (!$this->can_network_admin_ajax_context()) {
				wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
			}
		} elseif (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		$post_ids = $this->find_scan_targets();

		if (empty($post_ids)) {
			wp_send_json_error(
				array('message' => __('There is no published content with comments disabled to check yet. Disable comments on a post type that has published content, then run this again.', 'disable-comments')),
				400
			);
		}

		$results = array();
		$worst   = null;

		foreach ($post_ids as $post_id) {
			$scan = $this->run_theme_scan(get_permalink($post_id));

			if (is_wp_error($scan)) {
				// A loopback that fails once will fail for every page, so there
				// is nothing to learn from continuing.
				wp_send_json_error(array('message' => $scan->get_error_message()), 200);
			}

			$scan            = $this->interpret_theme_scan($scan);
			$scan['post_id'] = $post_id;
			$results[]       = $scan;

			// Report the page with the real problem, not whichever happened to
			// be checked first - a "clean" verdict from one template is exactly
			// how a scanner tells somebody there is nothing to fix while the
			// page they are looking at is broken.
			if (null === $worst || $this->scan_verdict_rank($scan['verdict']) > $this->scan_verdict_rank($worst['verdict'])) {
				$worst = $scan;
			}
		}

		$worst['checked']     = count($results);
		$worst['all_results'] = $results;

		wp_send_json_success($worst);
	}

	/**
	 * Settings that travel between sites, and how to clean each one.
	 *
	 * Anything not listed here is site-specific or internal and is deliberately
	 * not portable: `disabled_sites` names blog ids that mean nothing
	 * elsewhere, `db_version` and `settings_saved` are bookkeeping, and
	 * `sitewide_settings` lives in a network option rather than these.
	 *
	 * @return array Option key => type ('bool', 'int', 'keys', 'strings').
	 */
	private function get_portable_settings_map() {
		return array(
			'remove_everywhere'        => 'bool',
			'disabled_post_types'      => 'keys',
			'extra_post_types'         => 'keys',
			'remove_xmlrpc_comments'   => 'int',
			'remove_rest_API_comments' => 'int',
			'show_existing_comments'   => 'bool',
			'allowed_comment_types'    => 'keys',
			'blocked_comment_types'    => 'keys',
			'enable_exclude_by_role'   => 'bool',
			'exclude_by_role'          => 'keys',
			// Conditional rules ship in the same release as this exporter, and
			// leaving them out made the file quietly incomplete: the screen
			// says the settings were copied, the destination gets no taxonomy,
			// template or age rules, and behaves differently for reasons
			// nothing on either site explains.
			'enable_conditional_rules' => 'bool',
			'conditional_rules'        => 'rules',
			'auto_close_days'          => 'int',
		);
	}

	/**
	 * Coerce one imported value to the type its option expects.
	 *
	 * An import is an uploaded file: every value is treated as hostile, and
	 * anything that cannot be coerced becomes the empty form of its type
	 * rather than reaching the options as-is.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Type from get_portable_settings_map().
	 * @return mixed Sanitized value.
	 */
	private function sanitize_portable_value($value, $type) {
		if ('bool' === $type) {
			// PHP treats every non-empty string except "0" as true, so a
			// producer that encoded booleans as text would have
			// "remove_everywhere":"false" disable comments everywhere. This
			// importer already coerces malformed types; it has to coerce this
			// one correctly too.
			if (is_string($value)) {
				return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
			}

			return (bool) $value;
		}

		if ('int' === $type) {
			return (int) $value;
		}

		if ('rules' === $type) {
			// Straight through the same sanitiser the settings screen uses, so
			// an imported rule cannot be shaped in a way a saved one could not.
			// It already tolerates null and non-arrays, which is what a missing
			// field arrives as.
			return array_values($this->sanitize_conditional_rules($value));
		}

		if ('keys' === $type) {
			if (!is_array($value)) {
				return array();
			}

			$clean = array();
			foreach ($value as $item) {
				if (!is_scalar($item)) {
					continue;
				}
				$key = sanitize_key($item);
				if ('' !== $key) {
					$clean[] = $key;
				}
			}

			return array_values(array_unique($clean));
		}

		return '';
	}

	/**
	 * The current configuration as a portable payload.
	 *
	 * @return array
	 */
	public function export_settings($is_network_ctx = false) {
		$settings = array();
		$stored   = $this->get_stored_settings($is_network_ctx);

		foreach ($this->get_portable_settings_map() as $key => $type) {
			$value          = isset($stored[$key]) ? $stored[$key] : null;
			$settings[$key] = $this->sanitize_portable_value($value, $type);

			if ('conditional_rules' === $key) {
				$settings[$key] = $this->rules_to_portable($settings[$key]);
			}
		}

		return array(
			'schema_version' => self::EXPORT_SCHEMA_VERSION,
			'plugin_version' => defined('DC_VERSION') ? DC_VERSION : '',
			'exported_at'    => gmdate('c'),
			'settings'       => $settings,
		);
	}

	/**
	 * Read an import payload, whatever form it arrived in.
	 *
	 * @param mixed $payload JSON string or already-decoded array.
	 * @return array|WP_Error Sanitized settings, or an error naming the problem.
	 */
	private function parse_settings_payload($payload) {
		if (is_string($payload)) {
			$payload = json_decode($payload, true);
		}

		if (!is_array($payload)) {
			return new WP_Error('dc_import_invalid', __('That file is not valid JSON.', 'disable-comments'));
		}

		if (!isset($payload['settings']) || !is_array($payload['settings'])) {
			return new WP_Error('dc_import_no_settings', __('That file does not contain Disable Comments settings.', 'disable-comments'));
		}

		$schema = isset($payload['schema_version']) ? (int) $payload['schema_version'] : 0;

		if ($schema > self::EXPORT_SCHEMA_VERSION) {
			return new WP_Error(
				'dc_import_newer_schema',
				sprintf(
					/* translators: 1: schema version in the file, 2: schema version this plugin understands. */
					__('That file was written by a newer version of Disable Comments (format %1$d; this site understands %2$d). Update the plugin first.', 'disable-comments'),
					$schema,
					self::EXPORT_SCHEMA_VERSION
				)
			);
		}

		$clean = array();
		foreach ($this->get_portable_settings_map() as $key => $type) {
			if (!array_key_exists($key, $payload['settings'])) {
				continue;
			}
			$incoming = $payload['settings'][$key];

			// Before sanitising, not after: sanitize_conditional_rules()
			// intval()s terms, which would turn every portable slug into 0.
			if ('conditional_rules' === $key) {
				$incoming = $this->rules_from_portable($incoming);
			}

			$clean[$key] = $this->sanitize_portable_value($incoming, $type);
		}

		if (empty($clean)) {
			return new WP_Error('dc_import_no_known_keys', __('That file contains no settings this version recognises.', 'disable-comments'));
		}

		return $clean;
	}

	/**
	 * The full portable configuration this import would leave behind.
	 *
	 * Built before anything is diffed or written, because normalisation can
	 * move values between fields: a preview computed from the raw payload
	 * describes values that were never stored.
	 *
	 * @param array $incoming       Sanitized incoming settings.
	 * @param bool  $is_network_ctx Whether this import targets network storage.
	 * @return array Portable key => value.
	 */
	private function build_import_target($incoming, $is_network_ctx) {
		$target = array();

		// Role exclusion is a per-site rule and the network settings screen
		// deliberately does not offer it. Letting a single-site export write it
		// into the network option would apply an invisible rule to every
		// subsite - "exclude logged-out users" leaves comments open to the
		// public everywhere, with nothing on the network screen to explain why.
		$per_site_only = array('enable_exclude_by_role', 'exclude_by_role');

		// Everything the payload omits is carried over from what is stored,
		// not from the effective config this request happens to be running
		// with - see get_stored_settings().
		$stored = $this->get_stored_settings($is_network_ctx);

		foreach ($this->get_portable_settings_map() as $key => $type) {
			if ($is_network_ctx && in_array($key, $per_site_only, true)) {
				$target[$key] = $this->sanitize_portable_value(
					isset($stored[$key]) ? $stored[$key] : null,
					$type
				);
				continue;
			}

			$current      = isset($stored[$key]) ? $stored[$key] : null;
			$target[$key] = array_key_exists($key, $incoming)
				? $incoming[$key]
				: $this->sanitize_portable_value($current, $type);
		}

		// Only when the payload actually carries post types. Re-splitting the
		// site's existing lists on an unrelated partial import would drop any
		// disabled custom type that happens to be unregistered right now -
		// silently re-enabling its comments once it is registered again.
		$touches_post_types = array_key_exists('disabled_post_types', $incoming)
			|| array_key_exists('extra_post_types', $incoming);

		if ($touches_post_types) {
			$target = $this->normalize_post_type_fields($target, $is_network_ctx);
		}

		return $target;
	}

	/**
	 * Re-split post-type fields for this site's activation mode.
	 *
	 * The two fields mean different things depending on how the plugin is
	 * activated: get_disabled_post_types() only merges `extra_post_types` when
	 * network active, and a network context restricts `disabled_post_types` to
	 * built-ins. Copying both across verbatim loses custom post types in either
	 * direction, so both lists are merged and re-split by what this site can
	 * actually answer.
	 *
	 * @param array $target         Portable values being assembled.
	 * @param bool  $is_network_ctx Whether this import targets network storage.
	 * @return array $target with the two post-type fields rewritten.
	 */
	private function normalize_post_type_fields($target, $is_network_ctx) {
		$all   = array_values(array_unique(array_merge(
			isset($target['disabled_post_types']) ? (array) $target['disabled_post_types'] : array(),
			isset($target['extra_post_types']) ? (array) $target['extra_post_types'] : array()
		)));
		$known = array_keys($this->get_all_post_types($is_network_ctx));

		$target['disabled_post_types'] = array_values(array_intersect($all, $known));

		$leftover = array_values(array_diff($all, $known));

		if ($this->networkactive) {
			// Network storage is where a custom slug belongs; it is applied
			// per-site by get_disabled_post_types() wherever it is registered.
			$target['extra_post_types'] = $leftover;
		} else {
			// A single site has nowhere to keep a slug it does not register.
			// diff_settings() reports these as unknown_post_types.
			$target['extra_post_types'] = array();
		}

		return $target;
	}

	/**
	 * What would this import change?
	 *
	 * Diffed against the normalized target rather than the raw payload, so the
	 * preview and the applied report both describe values that will actually
	 * be stored.
	 *
	 * @param array $target   The configuration this import would leave behind.
	 * @param array $incoming The sanitized payload, for the unknown-type report.
	 * @return array {
	 *     @type array $changes            Key => array(from, to) for values that differ.
	 *     @type array $unknown_post_types Slugs the destination site does not register.
	 * }
	 */
	private function diff_settings($target, $incoming, $is_network_ctx = false) {
		$changes            = array();
		$unknown_post_types = array();
		// The same stored row build_import_target() worked from. Diffing
		// against the effective config instead would report every field the
		// constructor blanked as a change this file is about to make, when the
		// file leaves it exactly as it is.
		$stored             = $this->get_stored_settings($is_network_ctx);

		foreach ($target as $key => $value) {
			$current = isset($stored[$key]) ? $stored[$key] : null;
			$current = $this->sanitize_portable_value($current, $this->get_portable_settings_map_type($key));

			if ($current !== $value) {
				$changes[$key] = array(
					'from' => $current,
					'to'   => $value,
				);
			}
		}

		// A post type present on the source and missing here is the single most
		// likely way an import quietly does less than the user expects, so it
		// is surfaced rather than dropped. Only slugs the payload actually
		// asked for - values retained from this site are not "incoming".
		$requested = array_merge(
			isset($incoming['disabled_post_types']) ? (array) $incoming['disabled_post_types'] : array(),
			isset($incoming['extra_post_types']) ? (array) $incoming['extra_post_types'] : array()
		);

		if (!empty($requested)) {
			$registered = array_keys($this->get_all_post_types());

			foreach (array_unique($requested) as $slug) {
				if (!in_array($slug, $registered, true)) {
					$unknown_post_types[] = $slug;
				}
			}
		}

		return array(
			'changes'            => $changes,
			'unknown_post_types' => $unknown_post_types,
		);
	}

	/**
	 * Declared type for one portable setting.
	 *
	 * @param string $key Option key.
	 * @return string Type, or '' when the key is not portable.
	 */
	private function get_portable_settings_map_type($key) {
		$map = $this->get_portable_settings_map();

		return isset($map[$key]) ? $map[$key] : '';
	}

	/**
	 * Apply an import.
	 *
	 * @param mixed $payload        JSON string or decoded array.
	 * @param bool  $dry_run        When true, report the diff and write nothing.
	 * @param bool  $is_network_ctx Whether this runs in a network admin context.
	 * @return array|WP_Error Diff plus an `applied` flag, or an error.
	 */
	public function import_settings($payload, $dry_run = false, $is_network_ctx = false) {
		$incoming = $this->parse_settings_payload($payload);

		if (is_wp_error($incoming)) {
			return $incoming;
		}

		$target = $this->build_import_target($incoming, $is_network_ctx);
		$diff   = $this->diff_settings($target, $incoming, $is_network_ctx);

		if ($dry_run) {
			$diff['applied'] = false;
			return $diff;
		}

		// Nothing to write, so nothing to purge. Applying an identical file
		// used to rewrite the options and invalidate every subsite's page cache
		// while reporting no changes.
		if (empty($diff['changes'])) {
			$diff['applied'] = false;

			return $diff;
		}

		// Compose onto the stored row, not onto $this->options: update_options()
		// writes the whole array, so starting from a blanked effective config
		// would persist its defaults for every key outside the portable map -
		// disabled_sites among them, wiping the network's per-site exclusions
		// as a side effect of importing one flag.
		$stored = $this->get_stored_settings($is_network_ctx);

		foreach ($target as $key => $value) {
			$stored[$key] = $value;
		}

		$stored['db_version']     = self::DB_VERSION;
		$stored['settings_saved'] = true;

		$this->options = $stored;

		$this->update_options($is_network_ctx);

		// An import changes what the front end renders, exactly like a save.
		$this->purge_page_caches($this->get_purge_blog_ids($is_network_ctx));

		$diff['applied'] = true;

		return $diff;
	}

	/**
	 * AJAX: download the current settings as JSON.
	 */
	public function export_settings_download() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		// Same gate as the import. The context comes from a GET parameter, so a
		// subsite administrator holding a valid nonce from their own screen can
		// ask for the network context - and the constructor will already have
		// loaded the network option by the time we get here. Reading it needs
		// the network capability, exactly as writing it does.
		$is_network_ctx = $this->is_network_admin_ajax_context();

		if (!current_user_can($this->get_required_import_cap($is_network_ctx))) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		nocache_headers();
		header('Content-Type: application/json; charset=' . get_option('blog_charset'));
		header('Content-Disposition: attachment; filename=disable-comments-settings-' . gmdate('Y-m-d') . '.json');

		echo wp_json_encode($this->export_settings($is_network_ctx));

		exit;
	}

	/**
	 * AJAX: preview or apply an uploaded settings file.
	 */
	public function import_settings_ajax() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		$is_network_ctx = $this->is_network_admin_ajax_context();

		// An import writes the plugin's configuration, so it is gated exactly
		// like a save - including the network sitewide lock.
		if (!current_user_can($this->get_required_import_cap($is_network_ctx))) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		// Not sanitize_text_field(): this is a JSON document and that would
		// mangle it. It is validated key by key in parse_settings_payload()
		// instead, which is the only thing that makes it safe.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
		$dry_run = !empty($_POST['dry_run']);

		$result = $this->import_settings($payload, $dry_run, $is_network_ctx);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		wp_send_json_success($result);
	}

	/**
	 * Capability required to import settings in the current context.
	 *
	 * @param bool $is_network_ctx Whether the request came from a network admin screen.
	 * @return string Capability name.
	 */
	private function get_required_import_cap($is_network_ctx) {
		if ($is_network_ctx) {
			return 'manage_network_plugins';
		}

		if ($this->networkactive && $this->sitewide_settings === '1') {
			return 'manage_network_plugins';
		}

		return 'manage_options';
	}

	public function get_form_array_escaped($_args = array()) {
		$formArray = [];
		if (!empty($_args)) {
			$formArray = wp_parse_args($_args);
		}
		// nonce is verified in the calling function
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		else if (isset($_POST['data'])) {
			// need to use wp_parse_args before map_deep sanitize_text_field
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			$formArray = map_deep(wp_parse_args(wp_unslash($_POST['data'])), 'sanitize_text_field');
		}
		return $formArray;
	}

	public function disable_comments_settings($_args = array()) {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');
		if (($this->is_CLI && !empty($_args)) || wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			// Resolve context ONCE — used for both cap check and save routing.
			$is_network_ctx = $this->is_network_admin_ajax_context();

			if (!$this->is_CLI) {
				if ($is_network_ctx) {
					// Network admin context → must be super admin.
					$required_cap = 'manage_network_plugins';
				} elseif ($this->networkactive && $this->sitewide_settings === '1') {
					// Sitewide lock is on → only super admin may write.
					$required_cap = 'manage_network_plugins';
				} else {
					// Subsite or single-site → manage_options suffices.
					$required_cap = 'manage_options';
				}
				if (!current_user_can($required_cap)) {
					wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
				}
			}

			$this->apply_settings($this->get_form_array_escaped($_args), $is_network_ctx, $this->is_CLI);
		}
		if (!$this->is_CLI) {
			wp_send_json_success(array('message' => __('Saved', 'disable-comments')));
			wp_die();
		}
	}

	/**
	 * Write a settings payload. Does not verify permission and does not respond.
	 *
	 * disable_comments_settings() is an AJAX endpoint: it authenticates, calls
	 * this, then terminates the request with a JSON envelope. Any caller that
	 * has done its own authentication — the Abilities API, for one — has to
	 * come in here instead, or it gets wp_die()'d before it can return a
	 * result, having changed nothing.
	 *
	 * @param array $formArray         Parsed settings payload.
	 * @param bool  $is_network_ctx    Whether to route storage to the network option.
	 * @param bool  $preserve_existing Keep the current value of anything the
	 *                                 payload omits, rather than clearing it.
	 *                                 The settings screen posts every field, so
	 *                                 it wants false; a partial programmatic
	 *                                 update wants true.
	 * @return void
	 */
	public function apply_settings($formArray, $is_network_ctx = false, $preserve_existing = false) {
		{
			$old_options = $this->options;
			$this->options = [];
			if ($preserve_existing) {
				$this->options = $old_options;
			}

			if ($is_network_ctx && function_exists('get_sites') && empty($formArray['sitewide_settings'])) {
				$formArray['disabled_sites'] = isset($formArray['disabled_sites']) ? $formArray['disabled_sites'] : [];
				$this->options['disabled_sites'] = isset($old_options['disabled_sites']) ? $old_options['disabled_sites'] : [];
				$this->options['disabled_sites'] = array_merge($this->options['disabled_sites'], $formArray['disabled_sites']);
			} elseif ($is_network_ctx && !empty($formArray['sitewide_settings'])) {
				$this->options['disabled_sites'] = $old_options['disabled_sites'];
			}

			if (isset($formArray['mode'])) {
				$this->options['remove_everywhere'] = (sanitize_text_field($formArray['mode']) == 'remove_everywhere');
			}
			$post_types = $this->get_all_post_types($is_network_ctx);

			if ($this->options['remove_everywhere']) {
				$disabled_post_types = array_keys($post_types);
			} else {
				$disabled_post_types = (isset($formArray['disabled_types']) ? array_map('sanitize_key', (array) $formArray['disabled_types']) : ($preserve_existing && isset($this->options['disabled_post_types']) ? $this->options['disabled_post_types'] : []));
			}

			$disabled_post_types = array_intersect($disabled_post_types, array_keys($post_types));
			$this->options['disabled_post_types'] = $disabled_post_types;

			// Extra custom post types.
			if ($this->networkactive && isset($formArray['extra_post_types'])) {
				$extra_post_types = array_filter(array_map('sanitize_key', explode(',', $formArray['extra_post_types'])));
				$this->options['extra_post_types'] = array_diff($extra_post_types, array_keys($post_types)); // Make sure we don't double up builtins.
			}

			if ($is_network_ctx && isset($formArray['sitewide_settings'])) {
				update_site_option('disable_comments_sitewide_settings', $formArray['sitewide_settings']);
			}

			if (isset($formArray['disable_avatar'])) {
				if ($is_network_ctx) {
					if ($formArray['disable_avatar'] == '0' || $formArray['disable_avatar'] == '1') {
						$sites = get_sites([
							'number' => 0,
							'fields' => 'ids',
						]);
						foreach ($sites as $blog_id) {
							switch_to_blog($blog_id);
							update_option('show_avatars', (bool) !$formArray['disable_avatar']);
							restore_current_blog();
						}
					}
				} else {
					update_option('show_avatars', (bool) !$formArray['disable_avatar']);
				}
			}

			if (isset($formArray['enable_exclude_by_role'])) {
				$this->options['enable_exclude_by_role'] = $formArray['enable_exclude_by_role'];
			}
			if (isset($formArray['exclude_by_role'])) {
				$this->options['exclude_by_role'] = $formArray['exclude_by_role'];
			}

			// xml rpc
			$this->options['remove_xmlrpc_comments'] = (isset($formArray['remove_xmlrpc_comments']) ? intval($formArray['remove_xmlrpc_comments']) : ($preserve_existing && isset($this->options['remove_xmlrpc_comments']) ? $this->options['remove_xmlrpc_comments'] : 0));
			// rest api comments
			$this->options['remove_rest_API_comments'] = (isset($formArray['remove_rest_API_comments']) ? intval($formArray['remove_rest_API_comments']) : ($preserve_existing && isset($this->options['remove_rest_API_comments']) ? $this->options['remove_rest_API_comments'] : 0));
			// show existing comments
			$this->options['show_existing_comments'] = (isset($formArray['show_existing_comments']) ? (bool) $formArray['show_existing_comments'] : ($preserve_existing && isset($this->options['show_existing_comments']) ? $this->options['show_existing_comments'] : false));

			// conditional rules: taxonomy / template overrides plus auto-close
			//
			// $preserve_existing, not $this->is_CLI. These three fallbacks were
			// written when WP-CLI was the only partial-update caller and the
			// two flags meant the same thing. They no longer do: an Abilities
			// API set-status is a partial update that is explicitly NOT CLI, so
			// keying on is_CLI meant a call changing only the base mode wiped
			// the site's whole rules configuration — enable off, rules emptied,
			// auto-close reset — without saying so.
			$this->options['enable_conditional_rules'] = (isset($formArray['enable_conditional_rules']) ? (bool) $formArray['enable_conditional_rules'] : ($preserve_existing && isset($old_options['enable_conditional_rules']) ? $old_options['enable_conditional_rules'] : false));

			if (isset($formArray['conditional_rules'])) {
				$this->options['conditional_rules'] = $this->sanitize_conditional_rules($formArray['conditional_rules']);
			} elseif ($preserve_existing && isset($old_options['conditional_rules'])) {
				$this->options['conditional_rules'] = $old_options['conditional_rules'];
			} else {
				$this->options['conditional_rules'] = array();
			}

			$this->options['auto_close_days'] = (isset($formArray['auto_close_days']) ? max(0, intval($formArray['auto_close_days'])) : ($preserve_existing && isset($old_options['auto_close_days']) ? (int) $old_options['auto_close_days'] : 0));

			// allowed comment types (opt-in allowlist)
			if (isset($formArray['allowed_comment_types']) && is_array($formArray['allowed_comment_types'])) {
				// Sanitize and validate the allowed comment types
				$this->options['allowed_comment_types'] = array_map('sanitize_key', $formArray['allowed_comment_types']);
			} elseif ($preserve_existing && isset($old_options['allowed_comment_types'])) {
				// A partial update must not silently un-protect the comment
				// types someone deliberately allowlisted. The settings screen
				// always posts this field, so it still clears on a real save.
				$this->options['allowed_comment_types'] = $old_options['allowed_comment_types'];
			} else {
				// Default: empty array (all special comment types disabled)
				$this->options['allowed_comment_types'] = array();
			}

			// blocked comment types (opt-in blocklist - the reverse of the
			// allowlist: these close even where comments are open)
			if (isset($formArray['blocked_comment_types']) && is_array($formArray['blocked_comment_types'])) {
				$this->options['blocked_comment_types'] = array_map('sanitize_key', $formArray['blocked_comment_types']);
			} elseif ($preserve_existing && isset($old_options['blocked_comment_types'])) {
				// Same reasoning as the allowlist above: a partial update must
				// not silently reopen a comment type someone closed.
				$this->options['blocked_comment_types'] = $old_options['blocked_comment_types'];
			} else {
				// Default: empty array (nothing closed by type)
				$this->options['blocked_comment_types'] = array();
			}

			// Nothing stops someone ticking the same type in both lists. Store
			// the resolution rather than the contradiction, so an export, an
			// import and a status report cannot describe a state the plugin
			// does not actually implement. get_allowed_comment_types() applies
			// the same precedence at read time, for settings written before
			// this ran.
			if (!empty($this->options['blocked_comment_types'])) {
				$this->options['allowed_comment_types'] = array_values(
					array_diff((array) $this->options['allowed_comment_types'], $this->options['blocked_comment_types'])
				);
			}

			$this->options['db_version'] = self::DB_VERSION;
			$this->options['settings_saved'] = true;
			// save settings
			$this->update_options($is_network_ctx);

			// A cached page keeps serving the old comment form otherwise, so the
			// setting looks ignored to every visitor until the cache expires.
			// A network save invalidates every site, not just this one.
			$this->purge_page_caches($this->get_purge_blog_ids($is_network_ctx));
		}
	}

	public function is_configured() {
		$disabled_post_types = $this->get_disabled_post_types();

		if (empty($disabled_post_types) && empty($this->options['remove_everywhere']) && empty($this->options['remove_rest_API_comments']) && empty($this->options['remove_xmlrpc_comments']) && !$this->has_conditional_rules() && !$this->has_blocked_comment_types()) {
			return false;
		}
		return true;
	}

	public function delete_comments_settings($_args = array(), $ceilings = null) {
		global $deletedPostTypeNames;
		$log = '';
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (($this->is_CLI && !empty($_args)) || wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			// Resolve context ONCE — used for both cap check and deletion routing.
			$is_network_ctx = $this->is_network_admin_ajax_context();

			if (!$this->is_CLI) {
				if (!current_user_can($this->get_required_delete_cap($is_network_ctx))) {
					wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
				}
			}

			$log = $this->apply_delete_comments($this->get_form_array_escaped($_args), $is_network_ctx, $_args, $ceilings);
		}
		// message
		$deletedPostTypeNames = array_unique((array) $deletedPostTypeNames);
		$message = (count($deletedPostTypeNames) == 0 ? $log . '.' : $log . ' for ' . implode(", ", $deletedPostTypeNames) . '.');
		if (!$this->is_CLI) {
			// `deleted` so the screen can tell "removed nothing" from "removed
			// something" without parsing the prose in `message`. A run that
			// matched nothing has no aftermath to re-render.
			wp_send_json_success(array(
				'message' => $message,
				'deleted' => $this->get_last_deleted_count(),
			));
			wp_die();
		} else {
			return $log;
		}
	}

	/**
	 * Run a deletion. Does not verify permission and does not respond.
	 *
	 * Same split as apply_settings(): delete_comments_settings() is the AJAX
	 * endpoint, this is the work it does once the caller is authenticated.
	 *
	 * @param array $formArray      Parsed delete payload.
	 * @param bool  $is_network_ctx Whether to route deletion across the network.
	 * @param array $_args          Original args, threaded to the inner helper.
	 * @return string Log message.
	 */
	/**
	 * How many comments the last delete in this request removed.
	 *
	 * Zero both when nothing matched and when no delete has run, which is the
	 * same thing to every caller: nothing was removed.
	 *
	 * @since 2.9.1
	 * @return int
	 */
	public function get_last_deleted_count() {
		return (int) $this->last_deleted_count;
	}

	public function apply_delete_comments($formArray, $is_network_ctx = false, $_args = array(), $ceilings = null) {
		$log = '';

		// Counted before and after rather than inferred from the log string,
		// so the review prompt can name a real number. It lives here rather
		// than in the AJAX wrapper because this is the function that actually
		// deletes — which means a delete driven through the Abilities API is
		// counted too, and it is just as much a completed bulk delete.
		$deleted_count = 0;

		{
			if ($is_network_ctx && function_exists('get_sites') && class_exists('WP_Site_Query')) {
				$sites = get_sites([
					'number' => 0,
					'fields' => 'ids',
				]);
				foreach ($sites as $blog_id) {
					// $formArray['disabled_sites'] ids don't include "site_" prefix.
					if (!empty($formArray['disabled_sites']) && !empty($formArray['disabled_sites']["site_$blog_id"])) {
						switch_to_blog($blog_id);
						if (!$this->can_manage_current_site()) {
							restore_current_blog();
							continue;
						}
						// Measured inside the switch, like the purge below it:
						// each subsite has its own comments table, so counting
						// around the whole loop would only ever see the site
						// the request landed on — reporting zero for a
						// deletion that emptied every subsite.
						$before = $this->count_all_comments();

						$log = $this->delete_comments($_args, $is_network_ctx, $ceilings);

						$deleted_count += max(0, $before - $this->count_all_comments());

						// Purge while this site is still switched in: per-site
						// integrations only clear the site they run in, so a
						// purge after the loop would miss every subsite.
						$this->purge_page_caches();
						restore_current_blog();
					}
				}
			} else {
				$before = $this->count_all_comments();

				$log = $this->delete_comments($_args, $is_network_ctx, $ceilings);

				$deleted_count = max(0, $before - $this->count_all_comments());

				// Deleted comments stay visible in cached pages, and so do
				// their counts, so the same purge applies here.
				$this->purge_page_caches();
			}
		}

		$this->last_deleted_count = (int) $deleted_count;

		// A completed bulk delete is the one moment the plugin has visibly
		// earned something. Recorded here, shown later on our own screens.
		if (!empty($deleted_count)) {
			$this->record_review_trigger($deleted_count);
		}

		return $log;
	}

	/**
	 * Capability required to delete comments in the current context.
	 *
	 * Shared by the delete handler, the dry-run preview and the CSV export so
	 * a caller can never reach the data through a weaker gate than the one
	 * guarding the deletion itself.
	 *
	 * @param bool $is_network_ctx Whether the request came from a network admin screen.
	 * @return string Capability name.
	 */
	private function get_required_delete_cap($is_network_ctx) {
		if ($is_network_ctx) {
			// Network admin context -> must be super admin.
			return 'manage_network_plugins';
		}
		if ($this->networkactive && $this->sitewide_settings === '1') {
			// Sitewide lock is on -> only super admin may write.
			return 'manage_network_plugins';
		}
		return 'manage_options';
	}

	/**
	 * Resolve a delete request into the set of rows it matches.
	 *
	 * One place decides what each delete mode actually selects. The preview
	 * count, the CSV export and the deletion all read their WHERE clause from
	 * here, so the number shown to the user is by construction the number of
	 * rows the delete will remove - not an estimate from a similar query.
	 *
	 * Each target is:
	 *   label     - human-readable name, used in the summary and breakdown
	 *   join      - SQL joining $wpdb->comments (aliased `comments`) to posts
	 *   where     - SQL predicate carrying %s placeholders
	 *   params    - values for those placeholders
	 *   post_type - post type slug when the target is scoped to one, else ''
	 *
	 * @param array $formArray      Parsed form data.
	 * @param bool  $is_network_ctx Whether this runs in a network admin context.
	 * @return array List of targets; empty when the mode matches nothing.
	 */
	private function get_delete_targets($formArray, $is_network_ctx = false) {
		global $wpdb;

		$targets = array();

		if (!isset($formArray['delete_mode'])) {
			return $targets;
		}

		$mode          = $formArray['delete_mode'];
		$types         = $this->get_all_post_types($is_network_ctx);
		$commenttypes  = $this->get_all_comment_types();
		$allowed_types = $this->get_allowed_comment_types();

		// Allowed comment types (WP 6.9+ notes, and anything a plugin adds to
		// the allowlist) are opt-in-preserved. They must survive every mode
		// that is not explicitly naming them.
		$exclude_allowed = '';
		$exclude_params  = array();
		if (!empty($allowed_types)) {
			$placeholders    = implode(', ', array_fill(0, count($allowed_types), '%s'));
			$exclude_allowed = " AND comments.comment_type NOT IN ($placeholders)";
			$exclude_params  = $allowed_types;
		}

		$post_join = " INNER JOIN $wpdb->posts posts ON comments.comment_post_ID=posts.ID";

		if ($mode == 'delete_everywhere') {
			$targets[] = array(
				'label'     => __('All comments', 'disable-comments'),
				'join'      => '',
				'where'     => '1=1' . $exclude_allowed,
				'params'    => $exclude_params,
				'post_type' => '',
			);
		} elseif ($mode == 'selected_delete_types') {
			$delete_post_types = empty($formArray['delete_types']) ? array() : (array) $formArray['delete_types'];
			$delete_post_types = array_intersect($delete_post_types, array_keys($types));

			// Extra custom post types.
			if ($this->networkactive && !empty($formArray['delete_extra_post_types'])) {
				$delete_extra_post_types = array_filter(array_map('sanitize_key', explode(',', $formArray['delete_extra_post_types'])));
				$delete_extra_post_types = array_diff($delete_extra_post_types, array_keys($types));    // Make sure we don't double up builtins.
				$delete_post_types = array_merge($delete_post_types, $delete_extra_post_types);
			}

			// Unique last, so it covers the free-text field too: a repeated
			// slug - --types=post,post, or book,book typed into the network
			// screen's extra-post-types box - would otherwise build two
			// identical targets, and the preview and export would count every
			// matching comment twice while the delete removes it once.
			$delete_post_types = array_values(array_unique($delete_post_types));

			foreach ($delete_post_types as $delete_post_type) {
				$post_type_object = get_post_type_object($delete_post_type);
				$post_type_label  = $post_type_object ? $post_type_object->labels->name : $delete_post_type;

				$targets[] = array(
					'label'     => $post_type_label,
					'join'      => $post_join,
					'where'     => 'posts.post_type = %s' . $exclude_allowed,
					'params'    => array_merge(array($delete_post_type), $exclude_params),
					'post_type' => $delete_post_type,
				);
			}
		} elseif ($mode == 'selected_delete_comment_types') {
			$delete_comment_types = empty($formArray['delete_comment_types']) ? array() : (array) $formArray['delete_comment_types'];
			// get_all_comment_types() already drops allowed types, so an
			// allowlisted type cannot be selected here in the first place.
			// Unique for the same reason as the post types above.
			$delete_comment_types = array_values(array_unique(array_intersect($delete_comment_types, array_keys($commenttypes))));

			foreach ($delete_comment_types as $delete_comment_type) {
				$targets[] = array(
					'label'     => $commenttypes[$delete_comment_type],
					'join'      => '',
					'where'     => 'comments.comment_type = %s',
					'params'    => array($delete_comment_type),
					'post_type' => '',
				);
			}
		} elseif ($mode == 'delete_spam') {
			$targets[] = array(
				'label'     => __('Spam comments', 'disable-comments'),
				'join'      => '',
				'where'     => 'comments.comment_approved = %s' . $exclude_allowed,
				'params'    => array_merge(array('spam'), $exclude_params),
				'post_type' => '',
			);
		}

		return $targets;
	}

	/**
	 * Build a statement for one target, prepared when it carries parameters.
	 *
	 * @param string $prefix Everything up to and including the FROM clause.
	 * @param array  $target A target from get_delete_targets().
	 * @param string $suffix Optional trailing SQL (ORDER BY, LIMIT).
	 * @param array  $extra  Extra placeholder values appended after the target's.
	 * @return string SQL, prepared where necessary.
	 */
	private function target_signature($target) {
		// Identity of one target, stable across the two separate
		// get_delete_targets() calls the export and the delete each make.
		//
		// Deliberately not the array index: the two calls would have to agree
		// on ordering forever for that to hold, and if they ever stopped
		// agreeing the mistake would be silent and would delete the wrong
		// rows. A signature that does not match simply is not found, and an
		// unfound target deletes nothing.
		return md5($target['where'] . '|' . wp_json_encode($target['params']));
	}

	private function build_target_query($prefix, $target, $suffix = '', $extra = array()) {
		global $wpdb;

		$sql    = $prefix . $target['join'] . ' WHERE ' . $target['where'] . $suffix;
		$params = array_merge($target['params'], $extra);

		if (!empty($params)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare($sql, $params);
		}

		return $sql;
	}

	/**
	 * Count the comments a delete request would remove, without removing them.
	 *
	 * @param array $_args          Form data (WP-CLI / programmatic callers).
	 * @param bool  $is_network_ctx Whether this runs in a network admin context.
	 * @return array {
	 *     @type int   $total     Total rows matched.
	 *     @type array $breakdown Label => count, one entry per target.
	 * }
	 */
	public function count_comments_for_delete($_args = array(), $is_network_ctx = false, $sample_size = 0) {
		$formArray = $this->get_form_array_escaped($_args);

		// Opt-in, and off by default: the WP-CLI dry run and the abilities'
		// matched count read the total and nothing else, and neither should
		// pay for a query whose rows they discard.
		$sample_size = max(0, (int) $sample_size);

		// The deletion switches into each selected subsite. A preview that
		// counted only the current blog would promise a number the delete does
		// not honour - which defeats the point of having a preview.
		if ($this->is_network_delete($is_network_ctx)) {
			$blog_ids  = $this->get_selected_delete_blog_ids($formArray, $is_network_ctx);
			$breakdown = array();
			$sample    = array();
			$total     = 0;

			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);

				// Same check the export and the delete make. Counting a site
				// the delete will skip tells the operator more will be removed
				// than can be, and discloses a total from a site they hold no
				// rights on.
				if (!$this->can_manage_current_site()) {
					restore_current_blog();
					continue;
				}

				$site = $this->count_comments_on_current_site($formArray, $is_network_ctx);

				// Inside the capability check above, deliberately: a row is a
				// comment's author and text, which is a great deal more than
				// the count this guard already withholds.
				$site_rows = array();
				if ($sample_size > count($sample)) {
					$site_name = get_bloginfo('name');

					foreach ($this->sample_comments_on_current_site($formArray, $is_network_ctx, $sample_size - count($sample)) as $row) {
						// Comment and post ids are site-local, so a network
						// preview that did not name the site would list rows
						// nobody could place.
						$row['site'] = $site_name;
						$site_rows[] = $row;
					}
				}

				restore_current_blog();

				$total += $site['total'];
				$sample = array_merge($sample, $site_rows);

				foreach ($site['breakdown'] as $label => $count) {
					$breakdown[$label] = (isset($breakdown[$label]) ? $breakdown[$label] : 0) + $count;
				}
			}

			return array(
				'total'     => $total,
				'breakdown' => $breakdown,
				'sample'    => $sample,
			);
		}

		$preview           = $this->count_comments_on_current_site($formArray, $is_network_ctx);
		$preview['sample'] = $this->sample_comments_on_current_site($formArray, $is_network_ctx, $sample_size);

		return $preview;
	}

	/**
	 * A few of the comments a delete would actually remove.
	 *
	 * The button says "Preview what will be deleted" and the answer was a
	 * number. A count is not a review: somebody who reads one and confirms has
	 * checked how many rows will go, not which, and this operation has no undo
	 * and no trash to recover from. So the preview shows the comments
	 * themselves - who wrote them, when, on what, and enough of the text to
	 * recognise.
	 *
	 * Newest first, because a delete that is about to take something
	 * unintended is most often taking something recent.
	 *
	 * This discloses nothing new: the caller already holds the capability the
	 * delete itself requires, and the CSV backup on the same screen hands them
	 * every column, addresses and IP addresses included.
	 *
	 * @param array $formArray      Parsed delete payload.
	 * @param bool  $is_network_ctx Whether this is a network admin request.
	 * @param int   $limit          How many rows to collect at most.
	 * @return array List of array('author', 'date', 'post', 'excerpt').
	 */
	private function sample_comments_on_current_site($formArray, $is_network_ctx, $limit) {
		global $wpdb;

		$limit = (int) $limit;

		if ($limit < 1) {
			return array();
		}

		$targets = $this->get_delete_targets($formArray, $is_network_ctx);
		$sample  = array();

		foreach ($targets as $target) {
			$remaining = $limit - count($sample);

			if ($remaining < 1) {
				break;
			}

			$select = "SELECT comments.comment_author, comments.comment_date, comments.comment_content, comments.comment_post_ID FROM $wpdb->comments comments";
			$sql    = $this->build_target_query(
				$select,
				$target,
				' ORDER BY comments.comment_date DESC LIMIT ' . (int) $remaining
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results($sql, ARRAY_A);

			// A failed read here would quietly shorten the list, and a short
			// list reads as "this is all of it".
			$this->assert_query_succeeded();

			foreach ((array) $rows as $row) {
				$author = trim((string) $row['comment_author']);

				$sample[] = array(
					'author'  => ('' === $author) ? __('Anonymous', 'disable-comments') : $author,
					'date'    => mysql2date(get_option('date_format'), $row['comment_date']),
					'post'    => get_the_title((int) $row['comment_post_ID']),
					// Tags stripped before truncating: wp_html_excerpt counts
					// markup toward its budget, so a comment wrapped in a link
					// would come back cut to nothing worth reading.
					'excerpt' => wp_html_excerpt(wp_strip_all_tags((string) $row['comment_content']), 100, '…'),
				);
			}
		}

		return $sample;
	}

	/**
	 * The subsites a network delete would actually touch.
	 *
	 * Mirrors the loop in delete_comments_settings(). Returns an empty array
	 * whenever the deletion would run against the current site only, so the
	 * caller can take the simple path.
	 *
	 * @param array $formArray      Parsed delete payload.
	 * @param bool  $is_network_ctx Whether this is a network admin request.
	 * @return array Blog ids.
	 */
	private function get_selected_delete_blog_ids($formArray, $is_network_ctx) {
		if (!$is_network_ctx || !function_exists('get_sites') || !class_exists('WP_Site_Query')) {
			return array();
		}

		$selected = array();
		foreach (get_sites(array('number' => 0, 'fields' => 'ids')) as $blog_id) {
			if (!empty($formArray['disabled_sites']["site_$blog_id"])) {
				$selected[] = (int) $blog_id;
			}
		}

		// Returns an empty array only for a non-network request. A network
		// request with nothing ticked returns an empty list too, but the caller
		// distinguishes them via is_network_delete() - the deletion loops over
		// the selection and touches nothing, so preview and export must scope
		// to nothing as well rather than silently reporting the current site.
		return $selected;
	}

	private function can_manage_current_site() {
		// Whether the current user may act on whichever blog is switched in.
		//
		// One helper for all three network loops - preview, export, delete -
		// because they have to agree. They are the same operation seen at
		// three moments, and each time they have drifted apart the preview or
		// the export has ended up covering a site the delete refuses to touch.
		return (is_super_admin() || current_user_can('manage_options'));
	}

	/**
	 * Is this a network-scoped delete request?
	 *
	 * @param bool $is_network_ctx Whether this is a network admin request.
	 * @return bool
	 */
	private function is_network_delete($is_network_ctx) {
		return (bool) ($is_network_ctx && function_exists('get_sites') && class_exists('WP_Site_Query'));
	}

	/**
	 * Highest comment id written per blog by the last export, or null.
	 *
	 * @var array|null
	 */
	private $last_export_ceilings = null;

	/**
	 * The ceiling the last export reached, for the delete that follows it.
	 *
	 * Null when no export has run in this request — meaning the delete is not
	 * working from a backup and has nothing to constrain itself to.
	 *
	 * @return array|null blog_id => highest comment id exported.
	 */
	public function get_last_export_ceilings() {
		return $this->last_export_ceilings;
	}

	private function assert_query_succeeded() {
		global $wpdb;

		// wpdb answers a failed read the same way it answers an empty table:
		// get_results() gives an empty array, get_var() gives null. Every
		// caller here is deciding either what to tell the operator will be
		// deleted, or whether a backup is complete enough to delete against —
		// so "the replica went away" must never arrive as "there is nothing
		// there". last_error is the only thing that tells them apart.
		//
		// wpdb::query() calls flush() before it runs, which clears last_error,
		// so this cannot pick up a stale failure from an earlier query.
		if ('' !== $wpdb->last_error) {
			throw new RuntimeException('Database error while reading comments: ' . $wpdb->last_error);
		}
	}

	/**
	 * Count a delete request's matches on whichever site is current.
	 *
	 * @param array $formArray      Parsed delete payload.
	 * @param bool  $is_network_ctx Whether this is a network admin request.
	 * @return array total + breakdown.
	 */
	private function count_comments_on_current_site($formArray, $is_network_ctx) {
		global $wpdb;

		$targets = $this->get_delete_targets($formArray, $is_network_ctx);

		$breakdown = array();
		$total     = 0;

		foreach ($targets as $target) {
			$sql = $this->build_target_query("SELECT COUNT(*) FROM $wpdb->comments comments", $target);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$count = (int) $wpdb->get_var($sql);
			// A failed count reads as zero, and zero is the number the
			// operator confirms the delete against.
			$this->assert_query_succeeded();

			$label             = $target['label'];
			$breakdown[$label] = (isset($breakdown[$label]) ? $breakdown[$label] : 0) + $count;
			$total            += $count;
		}

		return array(
			'total'     => $total,
			'breakdown' => $breakdown,
		);
	}

	/**
	 * Make a value safe to hand to a spreadsheet.
	 *
	 * A comment body is attacker-controlled text. Excel and Sheets execute a
	 * cell that opens with =, +, - or @, so exporting one verbatim turns a
	 * backup into a payload delivery mechanism. The usual guard is a leading
	 * apostrophe, which those programs treat as "the rest is text".
	 *
	 * But this file is also offered as a backup to restore from, and a guard
	 * that cannot be undone corrupts what it protects. Prefixing alone is
	 * ambiguous: a body of +1 and a body of '+1 both come out as '+1, so a
	 * restore cannot tell which it started from.
	 *
	 * So an apostrophe is escaped by doubling it, exactly as the format
	 * already does with quotes. The rule to reverse it is: if the value starts
	 * with an apostrophe, drop that one character. +1 becomes '+1 and restores
	 * to +1; '+1 becomes ''+1 and restores to '+1. Every value round-trips.
	 *
	 * @param string $value Raw cell value.
	 * @return string Value safe to write, and reversible by the rule above.
	 */
	public function csv_escape($value) {
		$value = (string) $value;

		if ('' === $value) {
			return $value;
		}

		$first = substr($value, 0, 1);

		// "'" is here for the round trip, not for the spreadsheet: without it
		// the guard is a one-way transformation.
		if (false !== strpos("=+-@'\t\r", $first)) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Undo csv_escape(), for anyone restoring from one of these files.
	 *
	 * Public so a restore script does not have to reimplement the rule and get
	 * it subtly wrong.
	 *
	 * @param string $value Value as it appears in the CSV.
	 * @return string The original value.
	 */
	public function csv_unescape($value) {
		$value = (string) $value;

		if ('' !== $value && "'" === substr($value, 0, 1)) {
			return substr($value, 1);
		}

		return $value;
	}

	/**
	 * Format one CSV record.
	 *
	 * Written by hand rather than with fputcsv(), whose backslash escape
	 * character RFC 4180 has no concept of. fputcsv() doubles a quote for the
	 * enclosure but leaves a backslash in front of it alone, so a comment
	 * containing \" - a regex, a pasted code snippet - is written as \"",
	 * where a conforming reader takes the backslash for ordinary text and the
	 * quote after it for the end of the field. Every column past that one
	 * shifts, and the CLI can go on to delete the comments against a backup
	 * that no longer lines up. Passing '' disables the escape, but only on
	 * PHP 7.4 and up, and this file has to run back to 5.6.
	 *
	 * So: quote every field, double any quote inside it, and nothing else is
	 * special. That is the whole of the format.
	 *
	 * One caveat for anyone reading this file back: PHP's own fgetcsv() and
	 * str_getcsv() default to that same non-standard backslash escape, so
	 * they need an explicit '' escape to read conforming CSV. Spreadsheet
	 * software and other languages' parsers need nothing.
	 *
	 * @param array $fields Values for one record.
	 * @return string The record, terminated.
	 */
	private function csv_write($handle, $line) {
		$expected = strlen($line);
		$written  = fwrite($handle, $line);

		// fwrite() reports a short count as an integer, not false: a disk that
		// fills mid-row returns the bytes it managed. Treating that as success
		// leaves a truncated backup the CLI then deletes against, so anything
		// short of the whole line is a failure.
		return (false !== $written && $written === $expected);
	}

	/**
	 * Format one CSV record.
	 *
	 * See csv_line()'s companion csv_write() for why writes are length-checked.
	 *
	 * @param array $fields Values for one record.
	 * @return string The record, terminated.
	 */
	private function csv_line($fields) {
		$out = array();

		foreach ($fields as $field) {
			$out[] = '"' . str_replace('"', '""', (string) $field) . '"';
		}

		return implode(',', $out) . "\n";
	}

	/**
	 * Comment meta for a batch of comments, keyed by comment id.
	 *
	 * Fetched per batch rather than per row: one query for five hundred
	 * comments instead of five hundred.
	 *
	 * @param array $comment_ids Comment ids in this batch.
	 * @return array comment_ID => array of meta_key => list of values.
	 */
	private function get_comment_meta_for_export($comment_ids) {
		global $wpdb;

		$comment_ids = array_values(array_filter(array_map('intval', (array) $comment_ids)));

		if (empty($comment_ids)) {
			return array();
		}

		$placeholders = implode(', ', array_fill(0, count($comment_ids), '%d'));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_id, meta_key, meta_value FROM $wpdb->commentmeta WHERE comment_id IN ($placeholders)",
				$comment_ids
			),
			ARRAY_A
		);

		// delete_comments_for_target() removes these rows too. Exporting a
		// batch with its metadata silently missing, and counting it as backed
		// up, loses exactly what the file exists to preserve.
		$this->assert_query_succeeded();

		$out = array();
		foreach ((array) $rows as $row) {
			$id = (int) $row['comment_id'];

			if (!isset($out[$id])) {
				$out[$id] = array();
			}

			// A key can legitimately repeat, so values are lists.
			$out[$id][$row['meta_key']][] = $row['meta_value'];
		}

		return $out;
	}

	/**
	 * Core comment columns carried by the export.
	 *
	 * @return array Column names, in wp_comments order.
	 */
	private function get_exported_comment_columns() {
		return array(
			'comment_ID',
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_author_IP',
			'comment_date',
			'comment_date_gmt',
			'comment_content',
			'comment_karma',
			'comment_approved',
			'comment_agent',
			'comment_type',
			'comment_parent',
			'user_id',
		);
	}

	/**
	 * Export columns written without the spreadsheet guard.
	 *
	 * Deliberately the inverse of a list of "free-text" columns to escape.
	 * That is how comment_author_IP came to be written raw: an allowlist
	 * protects only what somebody remembered to put in it, and every column
	 * this export gains later is unprotected until somebody remembers again.
	 * Naming the exceptions instead makes a new column safe by default, and
	 * wrong only in the harmless direction.
	 *
	 * The exceptions are the columns wp_comments types as integers and
	 * datetimes. MySQL will not store a leading =, +, - or @ in any of them, so
	 * the guard could never fire; leaving them raw keeps a restore that does
	 * not call csv_unescape() byte-exact. Every other column is a varchar or
	 * text field holding whatever wrote it - including comment_author_IP, which
	 * is only ever an address by convention.
	 *
	 * @since 2.9.1
	 * @return array Column names to write unescaped.
	 */
	private function get_unescaped_export_columns() {
		return array(
			'comment_ID',
			'comment_post_ID',
			'comment_date',
			'comment_date_gmt',
			'comment_karma',
			'comment_parent',
			'user_id',
		);
	}

	/**
	 * Write the comments a delete request matches to an open stream as CSV.
	 *
	 * Rows are fetched in batches and keyed off the last id seen rather than
	 * an offset: the sites that reach for this tool are the ones holding
	 * hundreds of thousands of spam rows, and loading that into memory to
	 * "back it up" would take the site down instead.
	 *
	 * @param resource $handle         Open, writable stream.
	 * @param array    $_args          Form data.
	 * @param bool     $is_network_ctx Whether this runs in a network admin context.
	 * @return int Number of rows written.
	 */
	public function stream_comments_csv($handle, $_args = array(), $is_network_ctx = false) {
		$formArray = $this->get_form_array_escaped($_args);

		// Every core comment column, not a display-friendly subset. This file
		// is offered as a backup taken before an irreversible delete, so it has
		// to be able to reconstruct the rows: comment_parent carries thread
		// structure and user_id carries registered-user attribution, and
		// without them a "backup" restores a flat list of anonymous comments.
		//
		// blog_id leads because comment and post ids are site-local and collide
		// across subsites - a network export without it cannot be attributed
		// back to a site once the comments are gone.
		// A fresh export starts a fresh ceiling; a delete must never be
		// constrained by what some earlier export in this request reached.
		$this->last_export_ceilings = array();

		$columns = array_merge(
			array('blog_id'),
			$this->get_exported_comment_columns(),
			// delete_comments_for_target() removes the matching commentmeta
			// rows as well, so a file that omits them cannot restore
			// everything the operation destroyed - ratings, moderation state
			// and any plugin's own fields. JSON keeps it to one column while
			// staying machine-readable.
			array('comment_meta', 'post_title')
		);

		if (!$this->csv_write($handle, $this->csv_line($columns))) {
			throw new RuntimeException('Could not write the comment export.');
		}

		// A backup that omits the subsites the delete is about to empty is not
		// a backup, so the export follows the same site routing.
		if ($this->is_network_delete($is_network_ctx)) {
			$written  = 0;
			$blog_ids = $this->get_selected_delete_blog_ids($formArray, $is_network_ctx);

			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);

				// The same per-site check delete_comments_settings() makes
				// before emptying a subsite. Without it the two disagree, and
				// the direction they disagree in is the bad one: a user who
				// holds manage_network_plugins but not manage_options on this
				// subsite cannot delete its comments, yet could export them —
				// author names, email addresses and IPs — straight out of a
				// site they have no rights on.
				//
				// Skipping here also keeps the pair consistent: a site the
				// export passed over records no ceiling, and a target with no
				// ceiling deletes nothing.
				//
				// No WP-CLI carve-out, deliberately, because the delete loop
				// has none either: both branches only run when the caller
				// passed a network context, and the CLI never does. A carve-out
				// here would only be a hole the delete does not have.
				if (!$this->can_manage_current_site()) {
					restore_current_blog();
					continue;
				}

				$written += $this->stream_comments_for_current_site($handle, $formArray, $is_network_ctx);
				restore_current_blog();
			}

			return $written;
		}

		return $this->stream_comments_for_current_site($handle, $formArray, $is_network_ctx);
	}

	/**
	 * Write the matching comments on whichever site is current.
	 *
	 * @param resource $handle         Open, writable stream.
	 * @param array    $formArray      Parsed delete payload.
	 * @param bool     $is_network_ctx Whether this is a network admin request.
	 * @return int Rows written.
	 */
	private function stream_comments_for_current_site($handle, $formArray, $is_network_ctx) {
		global $wpdb;

		$targets    = $this->get_delete_targets($formArray, $is_network_ctx);
		$batch_size = 500;
		$written    = 0;
		$blog_id    = (int) get_current_blog_id();

		// Resolved once: the innermost loop below runs per comment, and the
		// sites that reach for this tool hold hundreds of thousands of them.
		$raw_columns = $this->get_unescaped_export_columns();

		foreach ($targets as $target) {
			$last_id = 0;
			$ceiling = 0;

			do {
				$select = "SELECT comments.* FROM $wpdb->comments comments";
				$sql    = $this->build_target_query(
					$select,
					$target,
					' AND comments.comment_ID > %d ORDER BY comments.comment_ID ASC LIMIT ' . (int) $batch_size,
					array($last_id)
				);

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
				$rows = $wpdb->get_results($sql, ARRAY_A);

				// Before the empty check, not after: a failed read is
				// indistinguishable from the end of the batches, and treating
				// it as the end produces a short or header-only file that the
				// CLI then reports as a complete backup and deletes against.
				$this->assert_query_succeeded();

				if (empty($rows)) {
					break;
				}

				$meta = $this->get_comment_meta_for_export(wp_list_pluck($rows, 'comment_ID'));

				foreach ($rows as $row) {
					$last_id = (int) $row['comment_ID'];

					$title = get_the_title($row['comment_post_ID']);

					$line = array(get_current_blog_id());

					foreach ($this->get_exported_comment_columns() as $column) {
						$value = isset($row[$column]) ? $row[$column] : '';
						// Guarded unless the column is one of the few that
						// cannot carry a payload - see
						// get_unescaped_export_columns() for why the test runs
						// this way round.
						$line[] = in_array($column, $raw_columns, true)
							? $value
							: $this->csv_escape($value);
					}

					$comment_meta = isset($meta[(int) $row['comment_ID']]) ? $meta[(int) $row['comment_ID']] : array();
					$line[] = $this->csv_escape(empty($comment_meta) ? '' : wp_json_encode($comment_meta));
					$line[] = $this->csv_escape($title);

					$ok = $this->csv_write($handle, $this->csv_line($line));

					// A disk that fills up mid-export must not produce a
					// truncated file the caller then treats as a complete
					// backup and deletes against.
					if (!$ok) {
						throw new RuntimeException('Could not write the comment export.');
					}

					$written++;

					if ($last_id > $ceiling) {
						$ceiling = $last_id;
					}
				}
			} while (count($rows) === $batch_size);

			// comment_ID is AUTO_INCREMENT, so anything inserted after this
			// target finished sorts above its ceiling, and capping its delete
			// there keeps it to rows the file contains.
			//
			// Per target, not per blog. Targets stream one after another, so a
			// blog-wide maximum lets a later target raise an earlier one's cap:
			// finish exporting posts at id 1000, a new post comment arrives as
			// 1001 unexported, then a page comment at 1002 is exported and
			// lifts the blog ceiling to 1002 — and the delete takes 1001 with
			// it. Each target is bounded by what that target actually read.
			$signature = $this->target_signature($target);
			$current   = isset($this->last_export_ceilings[$blog_id][$signature])
				? $this->last_export_ceilings[$blog_id][$signature]
				: 0;
			$this->last_export_ceilings[$blog_id][$signature] = max($current, $ceiling);
		}

		return $written;
	}

	/**
	 * CSV for the comments a delete request matches, as a string.
	 *
	 * Convenience wrapper around stream_comments_csv() for WP-CLI and tests.
	 * The download handler streams instead of calling this.
	 *
	 * @param array $_args          Form data.
	 * @param bool  $is_network_ctx Whether this runs in a network admin context.
	 * @return string CSV payload.
	 */
	public function export_comments_csv($_args = array(), $is_network_ctx = false) {
		$handle = fopen('php://temp', 'r+');
		if (false === $handle) {
			return '';
		}

		$this->stream_comments_csv($handle, $_args, $is_network_ctx);

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return $csv;
	}

	/**
	 * AJAX: how many comments would this delete remove?
	 */
	public function preview_delete_comments() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		$is_network_ctx = $this->is_network_admin_ajax_context();

		if (!current_user_can($this->get_required_delete_cap($is_network_ctx))) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		$preview = $this->count_comments_for_delete(array(), $is_network_ctx, self::DELETE_PREVIEW_SAMPLE_SIZE);

		wp_send_json_success($preview);
	}

	/**
	 * AJAX: download the matching comments as CSV before deleting them.
	 */
	public function export_comments_download() {
		$nonce = (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');

		if (!wp_verify_nonce($nonce, 'disable_comments_save_settings')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'disable-comments')), 403);
		}

		$is_network_ctx = $this->is_network_admin_ajax_context();

		if (!current_user_can($this->get_required_delete_cap($is_network_ctx))) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'disable-comments')), 403);
		}

		nocache_headers();
		header('Content-Type: text/csv; charset=' . get_option('blog_charset'));
		header('Content-Disposition: attachment; filename=disable-comments-export-' . gmdate('Y-m-d-His') . '.csv');

		$handle = fopen('php://output', 'w');
		if (false !== $handle) {
			$this->stream_comments_csv($handle, array(), $is_network_ctx);
			fclose($handle);
		}

		exit;
	}

	/**
	 * Delete the rows one target matches, meta first.
	 *
	 * @param array $target A target from get_delete_targets().
	 */
	private function recalculate_comment_counts($post_type = null) {
		global $wpdb;

		// Only reached when an export ceiling was in effect. The usual path
		// sets comment_count to zero because the delete emptied the target
		// outright; a ceiling deliberately leaves the comments that arrived
		// after the export behind, and zeroing anyway hides them from every
		// count-based display on the site.
		//
		// Counts approved comments only, which is what core's own
		// wp_update_comment_count_now() means by comment_count.
		$count = "(SELECT COUNT(*) FROM $wpdb->comments comments WHERE comments.comment_post_ID = posts.ID AND comments.comment_approved = '1')";

		if (null === $post_type) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query("UPDATE $wpdb->posts posts SET comment_count = $count");

			return;
		}

		// Deliberately without the post_author != 0 filter the zeroing query
		// alongside this one carries. That filter decides which posts the
		// delete bothers to blank; this query is fixing a number that is now
		// wrong, and a post with no author needs an accurate count just as
		// much as any other. Inheriting it left authorless posts reporting a
		// count for comments that are gone.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->posts posts SET comment_count = $count WHERE posts.post_type = %s", $post_type));
	}

	private function delete_comments_for_target($target, $max_id = null) {
		global $wpdb;

		// An export, if one ran, has already read every row it is going to.
		// A comment arriving between that last read and this delete is not in
		// the file, so it must survive: comment_ID is AUTO_INCREMENT, which
		// makes a ceiling on the id an exact description of "what the backup
		// saw". Without an export there is no ceiling and this is a no-op.
		//
		// The window this does NOT close: an older comment that changes into
		// the matching set during it — an existing comment marked as spam
		// after the export read past it — keeps its low id and is still
		// removed without being in the file. Closing that needs a lock or a
		// transaction across both operations, which is a bigger change than
		// this one and not obviously worth it for a maintenance tool.
		$ceiling_sql    = '';
		$ceiling_params = array();

		if (null !== $max_id) {
			$ceiling_sql    = ' AND comments.comment_ID <= %d';
			$ceiling_params = array((int) $max_id);
		}

		// Meta first: once the comments are gone, the join that identifies
		// their meta rows no longer matches anything.
		$meta_sql = $this->build_target_query(
			"DELETE cmeta FROM $wpdb->commentmeta cmeta INNER JOIN $wpdb->comments comments ON cmeta.comment_id=comments.comment_ID",
			$target,
			$ceiling_sql,
			$ceiling_params
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query($meta_sql);

		$sql = $this->build_target_query("DELETE comments FROM $wpdb->comments comments", $target, $ceiling_sql, $ceiling_params);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query($sql);
	}

	private function delete_comments($_args, $is_network_ctx = false, $ceilings = null) {
		global $wpdb;
		global $deletedPostTypeNames;

		// When an export ran first, this delete is the second half of a
		// "back it up, then remove it" pair, and it must not remove anything
		// the file does not contain. A blog absent from the map exported
		// nothing, so its ceiling is zero and it deletes nothing.
		//
		// Null means no export ran: an ordinary delete, uncapped.
		$blog_ceilings = null;
		if (is_array($ceilings)) {
			$blog_id       = (int) get_current_blog_id();
			$blog_ceilings = isset($ceilings[$blog_id]) ? (array) $ceilings[$blog_id] : array();
		}

		$formArray = $this->get_form_array_escaped($_args);
		$targets   = $this->get_delete_targets($formArray, $is_network_ctx);

		if (empty($targets)) {
			return '';
		}

		$mode  = $formArray['delete_mode'];
		$types = $this->get_all_post_types($is_network_ctx);

		foreach ($targets as $target) {
			$max_id = null;
			if (null !== $blog_ceilings) {
				$signature = $this->target_signature($target);
				// A target the export never recorded exported nothing, so it
				// deletes nothing.
				$max_id = isset($blog_ceilings[$signature]) ? (int) $blog_ceilings[$signature] : 0;
			}

			$this->delete_comments_for_target($target, $max_id);

			if ('selected_delete_types' === $mode) {
				if (null === $max_id) {
					// Nothing was spared, so every post of this type is empty.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query($wpdb->prepare("UPDATE $wpdb->posts SET comment_count = 0 WHERE post_author != 0 AND post_type = %s", $target['post_type']));
				} else {
					$this->recalculate_comment_counts($target['post_type']);
				}
			}

			if ('selected_delete_types' === $mode || 'selected_delete_comment_types' === $mode) {
				$deletedPostTypeNames[] = $target['label'];
			}
		}

		if ('delete_everywhere' === $mode) {
			// $blog_ceilings, not $max_id: that one is per target and scoped to
			// the loop above, and reading it out here would just be whatever
			// the last iteration happened to leave behind. The question here is
			// only whether an export ran at all.
			if (null === $blog_ceilings) {
				// Update comment counts
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query("UPDATE $wpdb->posts SET comment_count = 0");
			} else {
				$this->recalculate_comment_counts();
			}
		} elseif ('selected_delete_comment_types' === $mode) {
			// Update comment_count on post_types
			foreach ($types as $key => $value) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(comments.comment_ID) FROM $wpdb->comments comments INNER JOIN $wpdb->posts posts ON comments.comment_post_ID=posts.ID WHERE posts.post_type = %s", $key));
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query($wpdb->prepare("UPDATE $wpdb->posts SET comment_count = %d WHERE post_author != 0 AND post_type = %s", $comment_count, $key));
			}
		}

		$this->optimize_table($wpdb->commentmeta);
		$this->optimize_table($wpdb->comments);

		$log = ('delete_spam' === $mode)
			? __('All spam comments have been deleted.', 'disable-comments')
			: __('All comments have been deleted', 'disable-comments');

		delete_transient('wc_count_comments');
		return $log;
	}

	/**
	 * Total rows in the comments table.
	 *
	 * @return int
	 */
	private function count_all_comments() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments");
	}

	private function discussion_settings_allowed() {
		if (defined('DISABLE_COMMENTS_ALLOW_DISCUSSION_SETTINGS') && DISABLE_COMMENTS_ALLOW_DISCUSSION_SETTINGS == true) {
			return true;
		}
	}

	public function single_site_deactivate() {
		// for single sites, delete the options upon deactivation, not uninstall.
		delete_option('disable_comments_options');
		$this->delete_blocked_stats_options();

		// The settings this trigger was recorded against are gone, so a
		// reactivation would greet the user with "we just cleared N comments"
		// about a delete belonging to the previous configuration.
		//
		// The per-user dismissal deliberately does not go with it: somebody
		// who said "don't ask again" must not be asked again because an admin
		// toggled the plugin off and on.
		delete_option(self::REVIEW_TRIGGER_OPTION);
	}

	/**
	 * Remove every option the blocked-attempt counters created.
	 *
	 * These are autoloaded and per-site. Left behind they are both stale
	 * plugin data and, on a reinstall, someone else's counts presented as
	 * current telemetry.
	 *
	 * @return void
	 */
	public function delete_blocked_stats_options() {
		foreach (array_keys($this->get_blocked_vectors()) as $vector) {
			delete_option($this->get_blocked_vector_option($vector));
		}

		delete_option(self::BLOCKED_SINCE_OPTION);
	}

	/**
	 * We need fresh data in every call. Called after switching to blog in loop.
	 *
	 * @return int The number of comments.
	 */
	protected function __get_comment_count() {
		global $wpdb;

		// Exclude allowed comment types from the count since they cannot be deleted
		// and should not be displayed in the "Total Comments" count in the Delete Comments tab
		$allowed_types = $this->get_allowed_comment_types();

		if (empty($allowed_types)) {
			// No allowed types, count all comments
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $wpdb->get_var("SELECT COUNT(comment_id) FROM $wpdb->comments");
		}

		// Build exclusion query for allowed comment types
		$placeholders = implode(', ', array_fill(0, count($allowed_types), '%s'));
		$query = $wpdb->prepare(
			"SELECT COUNT(comment_id) FROM $wpdb->comments WHERE comment_type NOT IN ($placeholders)",
			$allowed_types
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_var($query);
	}

	/**
	 * Optimize a given table in the WordPress database.
	 *
	 * @param string $table_name The name of the table to optimize.
	 */
	protected function optimize_table($table_name) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->query("OPTIMIZE TABLE " . esc_sql($table_name));
	}

	/**
	 * Truncate a given table in the WordPress database.
	 *
	 * @param string $table_name The name of the table to truncate.
	 */
	protected function truncate_table($table_name) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->query("TRUNCATE TABLE " . esc_sql($table_name));
	}

	/**
	 * Get the current site-wide comment status as a descriptive string.
	 *
	 * This function analyzes the current Disable Comments plugin configuration
	 * and returns a string describing which content types have comments disabled.
	 *
	 * @return string The current comment status:
	 *                - 'all' if comments are disabled site-wide for all content types
	 *                - 'posts' if comments are disabled only for posts
	 *                - 'pages' if comments are disabled only for pages
	 *                - 'posts,pages' if comments are disabled for both posts and pages
	 *                - 'custom_type_name' for other specific content types
	 *                - 'multiple' if multiple specific types are disabled (not all)
	 *                - 'none' if comments are not disabled anywhere
	 *
	 * @since 2.5.2
	 */
	public function get_current_comment_status() {
		try {
			// Handle case where plugin is not properly initialized
			if (empty($this->options)) {
				return 'none';
			}

			// Check if comments are disabled everywhere
			if ($this->is_remove_everywhere()) {
				return 'all';
			}

			// Get disabled post types. Reporting-only, so unregistered slugs left
			// behind by a deactivated CPT plugin are excluded — they would
			// otherwise be summarised as though the type still existed.
			$disabled_post_types = $this->get_disabled_post_types_registered();

			// If no post types are disabled, comments are enabled everywhere
			if (empty($disabled_post_types)) {
				return 'none';
			}

			// Get all available post types that support comments
			$all_post_types = $this->get_all_post_types();
			$all_post_type_keys = array_keys($all_post_types);

			// Check if all available post types are disabled
			if (count($disabled_post_types) >= count($all_post_type_keys)) {
				$missing_types = array_diff($all_post_type_keys, $disabled_post_types);
				if (empty($missing_types)) {
					return 'all';
				}
			}

			// Handle specific common cases
			if (count($disabled_post_types) === 1) {
				$disabled_type = $disabled_post_types[0];

				// Return the specific post type name for single disabled types
				switch ($disabled_type) {
					case 'post':
						return 'posts';
					case 'page':
						return 'pages';
					default:
						// For custom post types, return the post type slug
						return $disabled_type;
				}
			}

			// Handle multiple specific post types
			if (count($disabled_post_types) === 2 &&
				in_array('post', $disabled_post_types) &&
				in_array('page', $disabled_post_types)) {
				return 'posts,pages';
			}

			// For other combinations, return 'multiple' to indicate partial disabling
			return 'multiple';
		} catch (Exception $e) {
			// Error handling - return safe default
			if (defined('WP_DEBUG') && WP_DEBUG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for WP_DEBUG mode
				error_log('Disable Comments: Error in get_current_comment_status() - ' . $e->getMessage());
			}
			return 'none';
		}
	}

	/**
	 * Get detailed comment status information including API restrictions.
	 *
	 * This function provides comprehensive information about comment restrictions
	 * including post type restrictions, API-level restrictions, network settings,
	 * role exclusions, and comment counts.
	 *
	 * @return array Associative array with detailed status information:
	 *               - 'status' => Main status (same as get_current_comment_status())
	 *               - 'disabled_post_types' => Array of disabled post type slugs
	 *               - 'disabled_post_type_labels' => Array of disabled post type labels
	 *               - 'remove_everywhere' => Boolean indicating global disable
	 *               - 'xmlrpc_disabled' => Boolean indicating XML-RPC comments disabled
	 *               - 'rest_api_disabled' => Boolean indicating REST API comments disabled
	 *               - 'total_post_types' => Total number of available post types
	 *               - 'is_configured' => Boolean indicating if plugin is configured
	 *               - 'total_comments' => Total number of comments in database
	 *               - 'network_active' => Boolean indicating if plugin is network activated
	 *               - 'sitewide_settings' => Site-wide settings status
	 *               - 'role_exclusion_enabled' => Boolean indicating if role exclusions are enabled
	 *               - 'excluded_roles' => Array of excluded role slugs
	 *               - 'excluded_role_labels' => Array of human-readable excluded role names
	 *
	 * @since 2.5.2
	 */
	public function get_detailed_comment_status() {
		try {
			$status = $this->get_current_comment_status();
			$disabled_post_types = $this->get_disabled_post_types_registered();
			$all_post_types = $this->get_all_post_types();

			// Get human-readable labels for disabled post types
			$disabled_labels = array();
			foreach ($disabled_post_types as $post_type) {
				if (isset($all_post_types[$post_type])) {
					$disabled_labels[] = $all_post_types[$post_type]->labels->name;
				} else {
					// Fallback for custom post types not in the main list
					$post_type_obj = get_post_type_object($post_type);
					$disabled_labels[] = $post_type_obj ? $post_type_obj->labels->name : $post_type;
				}
			}

			// Get total comments count
			$total_comments = $this->get_all_comments_number();

			// Determine site-wide settings status
			$sitewide_settings = 'not_applicable';
			if ($this->networkactive) {
				$sitewide_settings = isset($this->options['sitewide_settings']) && $this->options['sitewide_settings'] ?
					'enabled' : 'disabled';
			}

			// Process role-based exclusion information
			$role_exclusion_enabled = isset($this->options['enable_exclude_by_role']) && $this->options['enable_exclude_by_role'];
			$excluded_roles = isset($this->options['exclude_by_role']) ? $this->options['exclude_by_role'] : array();

			// Get human-readable role names
			$excluded_role_labels = array();
			if ($role_exclusion_enabled && !empty($excluded_roles)) {
				$editable_roles = get_editable_roles();

				foreach ($excluded_roles as $role) {
					if ($role === 'logged-out-users') {
						$excluded_role_labels[] = __('Logged out users', 'disable-comments');
					} elseif (isset($editable_roles[$role])) {
						$excluded_role_labels[] = translate_user_role($editable_roles[$role]['name']);
					} else {
						$excluded_role_labels[] = $role;
					}
				}
			}

			return array(
				'status' => $status,
				'disabled_post_types' => $disabled_post_types,
				'disabled_post_type_labels' => $disabled_labels,
				// Same vocabulary as the get-status ability, deliberately: the
				// two describe one site, and a Site Health panel disagreeing
				// with the API about it is worse than either being silent.
				// Counts follow get_conditional_rules(), so a rule stored while
				// the feature is off reports as zero - nothing is enforcing it.
				'conditional_rules_enabled' => $this->has_conditional_rules(),
				'conditional_rules_count' => count($this->get_conditional_rules()),
				'auto_close_days' => $this->get_auto_close_days(),
				'remove_everywhere' => $this->is_remove_everywhere(),
				'xmlrpc_disabled' => !empty($this->options['remove_xmlrpc_comments']),
				'rest_api_disabled' => !empty($this->options['remove_rest_API_comments']),
				'show_existing_comments' => !empty($this->options['show_existing_comments']),
				'total_post_types' => count($all_post_types),
				'is_configured' => $this->is_configured(),
				'total_comments' => $total_comments,
				'network_active' => $this->networkactive,
				'sitewide_settings' => $sitewide_settings,
				'role_exclusion_enabled' => $role_exclusion_enabled,
				'excluded_roles' => $excluded_roles,
				'excluded_role_labels' => $excluded_role_labels
			);
		} catch (Exception $e) {
			// Error handling - return safe defaults
			if (defined('WP_DEBUG') && WP_DEBUG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for WP_DEBUG mode
				error_log('Disable Comments: Error in get_detailed_comment_status() - ' . $e->getMessage());
			}
			return array(
				'status' => 'none',
				'disabled_post_types' => array(),
				'disabled_post_type_labels' => array(),
				'conditional_rules_enabled' => false,
				'conditional_rules_count' => 0,
				'auto_close_days' => 0,
				'remove_everywhere' => false,
				'xmlrpc_disabled' => false,
				'rest_api_disabled' => false,
				'show_existing_comments' => false,
				'total_post_types' => 0,
				'is_configured' => false,
				'total_comments' => 0,
				'network_active' => false,
				'sitewide_settings' => 'not_applicable',
				'role_exclusion_enabled' => false,
				'excluded_roles' => array(),
				'excluded_role_labels' => array()
			);
		}
	}
	/**
	 * Add Disable Comments information to WordPress Site Health Info panel.
	 *
	 * This method integrates the plugin's status information into WordPress's
	 * built-in Site Health system for easy debugging and site overview.
	 *
	 * @param array $debug_info The debug information array.
	 * @return array Modified debug information array.
	 *
	 * @since 2.5.2
	 */
	public function add_site_health_info($debug_info) {
		$data = $this->get_detailed_comment_status();

		// Create the main status description
		$status_descriptions = array(
			'all' => __('Comments are disabled site-wide for all content types', 'disable-comments'),
			'posts' => __('Comments are disabled only for blog posts', 'disable-comments'),
			'pages' => __('Comments are disabled only for pages', 'disable-comments'),
			'posts,pages' => __('Comments are disabled for both posts and pages', 'disable-comments'),
			'multiple' => __('Comments are disabled for multiple specific content types', 'disable-comments'),
			'none' => __('Comments are enabled everywhere', 'disable-comments'),
		);

		// translators: %s: disabled post types.
		$other_status_description = sprintf(__('Comments are disabled for: %s', 'disable-comments'), $data['status']);
		$status_description = isset($status_descriptions[$data['status']]) ?
			$status_descriptions[$data['status']] :
			$other_status_description;

		// Every sentence above describes the global and per-post-type settings
		// only. With rules live they are the starting point rather than the
		// answer - a site with nothing disabled globally still closes comments
		// on every post a rule matches, and this section read "Comments are
		// enabled everywhere" while that was happening.
		if (!empty($data['conditional_rules_enabled'])) {
			$status_description = sprintf(
				/* translators: %s: sentence describing the global and post-type settings. */
				__('%s. Conditional rules then close or reopen comments on individual posts', 'disable-comments'),
				$status_description
			);
		}

		// Format site-wide settings value
		$sitewide_settings_labels = array(
			'enabled' => __('Enabled', 'disable-comments'),
			'disabled' => __('Disabled', 'disable-comments'),
			'not_applicable' => __('Not applicable', 'disable-comments'),
		);

		// Build the fields array using data from get_detailed_comment_status()
		$fields = array(
			'status' => array(
				'label' => __('Comment Status', 'disable-comments'),
				'value' => $status_description,
			),
			'plugin_configured' => array(
				'label' => __('Plugin Configured', 'disable-comments'),
				'value' => $data['is_configured'] ? __('Yes', 'disable-comments') : __('No', 'disable-comments'),
			),
			'total_comments' => array(
				'label' => __('Total Comments', 'disable-comments'),
				'value' => number_format_i18n($data['total_comments']),
			),
			'global_disable' => array(
				'label' => __('Global Disable Active', 'disable-comments'),
				'value' => $data['remove_everywhere'] ? __('Yes', 'disable-comments') : __('No', 'disable-comments'),
			),
			'disabled_post_type_count' => array(
				'label' => __('Disabled Post Types Count', 'disable-comments'),
				'value' => sprintf('%d of %d', count($data['disabled_post_types']), $data['total_post_types']),
			),
			'disabled_post_types' => array(
				'label' => __('Disabled Post Types', 'disable-comments'),
				'value' => !empty($data['disabled_post_type_labels']) ?
					implode(', ', $data['disabled_post_type_labels']) :
					__('None', 'disable-comments'),
			),
			'xmlrpc_comments' => array(
				'label' => __('XML-RPC Comments', 'disable-comments'),
				'value' => $data['xmlrpc_disabled'] ? __('Disabled', 'disable-comments') : __('Enabled', 'disable-comments'),
			),
			'rest_api_comments' => array(
				'label' => __('REST API Comments', 'disable-comments'),
				'value' => $data['rest_api_disabled'] ? __('Disabled', 'disable-comments') : __('Enabled', 'disable-comments'),
			),
			'show_existing_comments' => array(
				'label' => __('Show Existing Comments', 'disable-comments'),
				'value' => $data['show_existing_comments'] ? __('Yes', 'disable-comments') : __('No', 'disable-comments'),
			),
			'network_active' => array(
				'label' => __('Network Active', 'disable-comments'),
				'value' => $data['network_active'] ? __('Yes', 'disable-comments') : __('No', 'disable-comments'),
			),
			'sitewide_settings' => array(
				'label' => __('Site-wide Settings', 'disable-comments'),
				'value' => $sitewide_settings_labels[$data['sitewide_settings']],
			),
			'role_exclusion_enabled' => array(
				'label' => __('Role-based Exclusions', 'disable-comments'),
				'value' => $data['role_exclusion_enabled'] ? __('Enabled', 'disable-comments') : __('Disabled', 'disable-comments'),
			),
			'excluded_roles' => array(
				'label' => __('Excluded Roles', 'disable-comments'),
				'value' => !empty($data['excluded_role_labels']) ?
					implode(', ', $data['excluded_role_labels']) :
					__('None', 'disable-comments'),
			),
			// Reported whether or not they are on. Somebody comparing two
			// sites, or reading a support ticket's Site Health paste, needs
			// "rules: off" said out loud rather than inferred from a missing
			// line.
			'conditional_rules' => array(
				'label' => __('Conditional Rules', 'disable-comments'),
				'value' => $data['conditional_rules_enabled'] ?
					__('Enabled', 'disable-comments') :
					__('Disabled', 'disable-comments'),
			),
			'conditional_rules_count' => array(
				'label' => __('Conditional Rules Configured', 'disable-comments'),
				'value' => number_format_i18n($data['conditional_rules_count']),
			),
			'auto_close_days' => array(
				'label' => __('Auto-close Comments After', 'disable-comments'),
				'value' => $data['auto_close_days'] > 0 ?
					sprintf(
						/* translators: %s: number of days. */
						_n('%s day', '%s days', $data['auto_close_days'], 'disable-comments'),
						number_format_i18n($data['auto_close_days'])
					) :
					__('No age limit', 'disable-comments'),
			),
		);

		// Blocked attempts. Site Health is where an admin auditing a site
		// looks, and it is the one screen that shows the plugin is still
		// doing something rather than sitting idle.
		if ($this->blocked_stats_enabled()) {
			$blocked = $this->get_blocked_stats();

			$fields['blocked_since'] = array(
				'label' => __('Counting Blocked Attempts Since', 'disable-comments'),
				'value' => date_i18n(get_option('date_format'), $blocked['since']),
			);

			foreach ($this->get_blocked_vectors() as $vector => $label) {
				$fields['blocked_' . $vector] = array(
					// translators: %s: name of the blocked request type.
					'label' => sprintf(__('Blocked: %s', 'disable-comments'), $label),
					'value' => number_format_i18n($blocked['counts'][$vector]),
				);
			}
		}

		// Add the section to Site Health
		$debug_info['disable-comments'] = array(
			'label' => __('Disable Comments', 'disable-comments'),
			'description' => __('Complete overview of comment disable settings and configuration.', 'disable-comments'),
			'fields' => $fields,
		);

		return $debug_info;
	}
}

Disable_Comments::get_instance();
