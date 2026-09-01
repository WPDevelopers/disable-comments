<?php
/**
 * WordPress Abilities API integration.
 *
 * Exposes Disable Comments' state to the Abilities API (WordPress 6.9+), so AI
 * agents, the command palette, and MCP clients can ask a site how comments are
 * configured instead of scraping the settings screen.
 *
 * This file is only loaded when the Abilities API is present — see
 * Disable_Comments::register_abilities(). Every callback here relies solely on
 * the plugin's public API, so it stays decoupled from internals.
 *
 * NOTE: This file must remain parseable on PHP 5.6 (see the PHP Compatibility
 * section of CLAUDE.md). Core's Abilities API itself requires a newer PHP, but
 * that is core's concern — our syntax must not break the 5.6 lint.
 *
 * @package Disable_Comments
 * @since 2.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers the ability category that groups this plugin's abilities.
 *
 * Runs on `wp_abilities_api_categories_init`, which core fires before
 * `wp_abilities_api_init` so the category exists when abilities register.
 *
 * @since 2.8.0
 * @return void
 */
function disable_comments_register_ability_categories() {
	wp_register_ability_category(
		'disable-comments',
		array(
			'label'       => __('Disable Comments', 'disable-comments'),
			'description' => __('Abilities for inspecting how comments are disabled on this site.', 'disable-comments'),
		)
	);
}

/**
 * Registers this plugin's abilities.
 *
 * Abilities must be registered on `wp_abilities_api_init` — calling
 * wp_register_ability() outside that action is a no-op with a _doing_it_wrong()
 * notice.
 *
 * @since 2.8.0
 * @return void
 */
function disable_comments_register_abilities() {
	wp_register_ability(
		'disable-comments/get-status',
		array(
			'label'               => __('Get comment status', 'disable-comments'),
			'description'         => __('Reports where comments are currently disabled on this site: the overall status, which post types have comments turned off, and whether the setting applies across a multisite network. Use this to answer questions about whether comments are enabled before changing content or settings.', 'disable-comments'),
			'category'            => 'disable-comments',
			'execute_callback'    => 'disable_comments_ability_get_status',
			'permission_callback' => 'disable_comments_ability_get_status_permission',
			'output_schema'       => disable_comments_ability_get_status_schema(),
			'meta'                => array(
				// Reading settings never changes them, so this is safe for an
				// agent to call speculatively and repeatedly.
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				// show_in_rest only opens the wp-abilities/v1 REST routes. MCP
				// servers gate separately on meta.mcp.public and silently skip
				// anything without it — no warning, no _doing_it_wrong(), the
				// ability just never appears in discovery. Without this key the
				// whole point of registering ("an MCP client can ask a site how
				// comments are configured") does not work.
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);

	wp_register_ability(
		'disable-comments/set-status',
		array(
			'label'               => __('Set comment status', 'disable-comments'),
			'description'         => __('Changes where comments are disabled on this site. Either turns comments off everywhere, or replaces the list of post types that have comments disabled. This writes the site configuration and takes effect immediately for every visitor. Call disable-comments/get-status first to see the current state, and pass confirm=true to proceed.', 'disable-comments'),
			'category'            => 'disable-comments',
			'execute_callback'    => 'disable_comments_ability_set_status',
			'permission_callback' => 'disable_comments_ability_write_permission',
			'input_schema'        => disable_comments_ability_set_status_input_schema(),
			'output_schema'       => disable_comments_ability_write_output_schema(),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					// Reversible: the previous configuration is returned so a
					// caller can put it back.
					'destructive' => false,
					// Applying the same input twice lands on the same state.
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);

	wp_register_ability(
		'disable-comments/delete-comments',
		array(
			'label'               => __('Delete comments', 'disable-comments'),
			'description'         => __('Permanently deletes comments from the database. This cannot be undone and there is no trash to recover from. Always call this with dry_run=true first to see how many comments match, show that number to the person you are helping, and only then call it again with dry_run=false and confirm=true. On a multisite network this only ever affects the site the request is made against, never other sites in the network.', 'disable-comments'),
			'category'            => 'disable-comments',
			'execute_callback'    => 'disable_comments_ability_delete_comments',
			'permission_callback' => 'disable_comments_ability_write_permission',
			'input_schema'        => disable_comments_ability_delete_input_schema(),
			'output_schema'       => disable_comments_ability_delete_output_schema(),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					// Irreversible data loss. Hosts that surface this
					// annotation should require a human to confirm.
					'destructive' => true,
					'idempotent'  => false,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		)
	);
}

/**
 * Permission callback for both write abilities.
 *
 * Deliberately stricter than the read ability on multisite: anything that can
 * rewrite configuration for every site in a network needs the network
 * capability, not just manage_options on the site the request landed on.
 *
 * @since 2.9.0
 * @param mixed $input Ability input.
 * @return bool
 */
function disable_comments_ability_write_permission($input = null) {
	$instance = Disable_Comments::get_instance();

	if ($instance->networkactive && !empty($instance->sitewide_settings)) {
		return current_user_can('manage_network_plugins');
	}

	return current_user_can('manage_options');
}

/**
 * Should an ability write route to the network option?
 *
 * No — and it is worth writing down why, because the first version of this
 * returned true for a network-active install with sitewide settings on, which
 * is precisely the case where it is wrong.
 *
 * Line up what the constructor READS against what an ability may WRITE:
 *
 *   not network-active .................. reads the blog option
 *   network-active, sitewide on ......... reads the blog option on a subsite,
 *                                         because an ability request is never
 *                                         a network-admin request
 *   network-active, sitewide off ........ reads the network option, and
 *                                         disable_comments_ability_can_write()
 *                                         refuses this configuration outright
 *
 * Every configuration an ability is allowed to write therefore reads from the
 * current blog. Routing the write to the network option meant the ability
 * reported success from its updated in-memory state while the next request
 * reloaded the untouched local option — and it could disturb the fallback
 * configuration other sites depend on. Verified on a real two-site network.
 *
 * Kept as a named function rather than inlining `false` so the reasoning has
 * somewhere to live and the call sites stay readable.
 *
 * @since 2.9.0
 * @param Disable_Comments $instance Plugin instance.
 * @return bool Always false; see above.
 */
function disable_comments_ability_network_ctx($instance) {
	unset($instance);

	return false;
}

/**
 * Can this ability safely write the site's configuration?
 *
 * Network-activated with sitewide settings OFF is the one configuration this
 * ability cannot serve. There the effective settings come from the network
 * option and are selected per-site through `disabled_sites`, which an ability
 * call carries no way to express - so a write would land in a blog-local
 * option that nothing ever reads, and report success for a change that
 * disappears on the next request. Refusing is the honest answer.
 *
 * @since 2.9.0
 * @param Disable_Comments $instance Plugin instance.
 * @return true|WP_Error
 */
function disable_comments_ability_can_write($instance) {
	if ($instance->networkactive && empty($instance->sitewide_settings)) {
		return new WP_Error(
			'disable_comments_network_per_site',
			__('This site is part of a network where each site is configured from the network admin screen. Changing comment settings through this ability is not supported here - use the network admin screen or WP-CLI instead.', 'disable-comments'),
			array('status' => 409)
		);
	}

	return true;
}

/**
 * Has the caller explicitly confirmed a write?
 *
 * An agent must say so in the call. Without this a mis-parsed instruction or a
 * hallucinated tool call rewrites a site's configuration, or empties its
 * comments table, with nothing standing in the way.
 *
 * @since 2.9.0
 * @param array $input Ability input.
 * @return bool
 */
function disable_comments_ability_confirmed($input) {
	return is_array($input) && !empty($input['confirm']);
}

/**
 * The error returned when a write was attempted without confirmation.
 *
 * @since 2.9.0
 * @return WP_Error
 */
function disable_comments_ability_unconfirmed_error() {
	return new WP_Error(
		'disable_comments_not_confirmed',
		__('This changes the site and was not confirmed. Re-send the same call with confirm set to true.', 'disable-comments'),
		array('status' => 400)
	);
}

/**
 * The status summary string, read role-independently.
 *
 * get_current_comment_status() resolves its "all" branch through
 * is_remove_everywhere(), which returns false for a caller in an excluded
 * role. That is correct for filtering — those users really do still see
 * comment forms — but every ability payload describes the *site's*
 * configuration, so the global setting has to win here. For a caller who is
 * not exempt this is identical to what the method returns on its own.
 *
 * Shared rather than repeated: get-status guarded this and set-status did not,
 * so one site answered "all" to a read and summarised the identical
 * configuration as "posts" through the write ability one call later.
 *
 * @since 2.9.0
 * @param Disable_Comments $instance Plugin instance.
 * @return string
 */
function disable_comments_ability_status_summary($instance) {
	return (string) ($instance->is_remove_everywhere_configured()
		? 'all'
		: $instance->get_current_comment_status());
}

/**
 * Input schema for `disable-comments/set-status`.
 *
 * @since 2.9.0
 * @return array
 */
function disable_comments_ability_set_status_input_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'mode'        => array(
				'type'        => 'string',
				'enum'        => array('everywhere', 'post_types', 'off'),
				'description' => __('"everywhere" disables comments on every post type. "post_types" disables them only on the slugs given in post_types. "off" re-enables comments everywhere.', 'disable-comments'),
			),
			'post_types'  => array(
				'type'        => 'array',
				'items'       => array('type' => 'string'),
				'description' => __('Post type slugs to disable comments on. Only used when mode is "post_types". Slugs this site does not register are reported back in unknown_post_types and ignored.', 'disable-comments'),
			),
			'confirm'     => array(
				'type'        => 'boolean',
				'description' => __('Must be true. Guards against an unintended call changing the site configuration.', 'disable-comments'),
			),
		),
		'required'   => array('mode', 'confirm'),
	);
}

/**
 * Output schema shared by the configuration-writing abilities.
 *
 * @since 2.9.0
 * @return array
 */
function disable_comments_ability_write_output_schema() {
	$properties = array(
		'changed'            => array(
			'type'        => 'boolean',
			'description' => __('True when the stored configuration actually changed. False means the site was already in the requested state.', 'disable-comments'),
		),
		'previous_status'    => array(
			'type'        => 'string',
			'description' => __('The status string before this call, in the same vocabulary as disable-comments/get-status. This is a human-readable summary and is NOT a valid "mode" - to undo, use "previous_mode" and "previous_disabled_post_types" instead.', 'disable-comments'),
		),
		'previous_mode'      => array(
			'type'        => 'string',
			'enum'        => array('everywhere', 'post_types', 'off'),
			'description' => __('The mode this site was in before the call. Send it back with "previous_disabled_post_types" as "post_types" to restore exactly what was there.', 'disable-comments'),
		),
		'previous_disabled_post_types' => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('The post types that had comments disabled before the call. Together with "previous_mode" this is everything needed to undo it.', 'disable-comments'),
		),
		'status'             => array(
			'type'        => 'string',
			'description' => __('The status string after this call.', 'disable-comments'),
		),
		'disabled_post_types' => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Post type slugs with comments disabled after this call.', 'disable-comments'),
		),
		'unknown_post_types' => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Slugs that were requested but are not registered on this site, so they were ignored. An empty list means everything requested was applied.', 'disable-comments'),
		),
	);

	return array(
		'type'       => 'object',
		'properties' => $properties,
		'required'   => array_keys($properties),
	);
}

/**
 * Input schema for `disable-comments/delete-comments`.
 *
 * @since 2.9.0
 * @return array
 */
function disable_comments_ability_delete_input_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'mode'          => array(
				'type'        => 'string',
				'enum'        => array('everywhere', 'post_types', 'comment_types', 'spam'),
				'description' => __('What to delete: every comment, everything on given post types, everything of given comment types, or only spam.', 'disable-comments'),
			),
			'post_types'    => array(
				'type'        => 'array',
				'items'       => array('type' => 'string'),
				'description' => __('Post type slugs, when mode is "post_types".', 'disable-comments'),
			),
			'comment_types' => array(
				'type'        => 'array',
				'items'       => array('type' => 'string'),
				'description' => __('Comment type slugs, when mode is "comment_types". Types on the site\'s allowlist cannot be deleted this way.', 'disable-comments'),
			),
			'dry_run'       => array(
				'type'        => 'boolean',
				'description' => __('When true, report how many comments match and delete nothing. Call this way first, every time.', 'disable-comments'),
			),
			'confirm'       => array(
				'type'        => 'boolean',
				'description' => __('Must be true for a real deletion. Ignored when dry_run is true.', 'disable-comments'),
			),
		),
		'required'   => array('mode'),
	);
}

/**
 * Output schema for `disable-comments/delete-comments`.
 *
 * @since 2.9.0
 * @return array
 */
function disable_comments_ability_delete_output_schema() {
	$properties = array(
		'dry_run' => array(
			'type'        => 'boolean',
			'description' => __('True when nothing was deleted and this is a count only.', 'disable-comments'),
		),
		'deleted' => array(
			'type'        => 'integer',
			'description' => __('How many comments were deleted. Zero on a dry run.', 'disable-comments'),
		),
		'matched' => array(
			'type'        => 'integer',
			'description' => __('How many comments the request matches. On a dry run this is what would be deleted.', 'disable-comments'),
		),
	);

	return array(
		'type'       => 'object',
		'properties' => $properties,
		'required'   => array_keys($properties),
	);
}

/**
 * Execute callback for `disable-comments/set-status`.
 *
 * @since 2.9.0
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function disable_comments_ability_set_status($input = array()) {
	if (!disable_comments_ability_confirmed($input)) {
		return disable_comments_ability_unconfirmed_error();
	}

	$instance = Disable_Comments::get_instance();

	$writable = disable_comments_ability_can_write($instance);
	if (is_wp_error($writable)) {
		return $writable;
	}

	$mode = isset($input['mode']) ? $input['mode'] : '';

	$previous_status = disable_comments_ability_status_summary($instance);
	$previous_types  = array_values(array_map('strval', (array) $instance->get_disabled_post_types()));

	// The mode, not just the summary: 'multiple' and 'pages' are not modes, so
	// a caller handed only previous_status cannot actually undo anything.
	if ($instance->is_remove_everywhere_configured()) {
		$previous_mode = 'everywhere';
	} elseif (empty($previous_types)) {
		$previous_mode = 'off';
	} else {
		$previous_mode = 'post_types';
	}

	$available = array_keys($instance->get_all_post_types());
	$requested = isset($input['post_types']) ? array_map('strval', (array) $input['post_types']) : array();
	$unknown   = array_values(array_diff($requested, $available));

	if ('everywhere' === $mode) {
		$args = array('mode' => 'remove_everywhere');
	} elseif ('off' === $mode) {
		$args = array(
			'mode'           => 'selected_types',
			'disabled_types' => array(),
		);
	} elseif ('post_types' === $mode) {
		$args = array(
			'mode'           => 'selected_types',
			'disabled_types' => array_values(array_intersect($requested, $available)),
		);
	} else {
		return new WP_Error(
			'disable_comments_bad_mode',
			__('Unknown mode. Use "everywhere", "post_types" or "off".', 'disable-comments'),
			array('status' => 400)
		);
	}

	// apply_settings(), not disable_comments_settings(). The latter is the AJAX
	// endpoint: over this ability's own REST/MCP transport it finds no WP-CLI
	// context and no AJAX nonce, so it would skip the write entirely and then
	// wp_die() before this callback could return. Permission was already checked
	// by this ability's permission_callback.
	//
	// preserve_existing: true, because an ability call is a partial update -
	// omitting `post_types` must not clear the allowlist or the role exclusion.
	//
	// extra_post_types is the exception: get_disabled_post_types() appends it
	// on a network install, so preserving it would leave "off" still disabling
	// a custom type and make a post_types replacement unable to remove one.
	// The mode is a statement about the whole disabled set, so this field is
	// rebuilt with it rather than carried over.
	if ('post_types' === $mode) {
		// A network write stores disabled_post_types through the network form
		// of get_all_post_types(), which is built-ins only - so a registered
		// custom type has to travel in extra_post_types or it falls out of
		// both lists and quietly stays enabled. Genuinely unknown slugs are
		// reported in unknown_post_types and stored nowhere.
		$storable = disable_comments_ability_network_ctx($instance)
			? array_keys($instance->get_all_post_types(true))
			: $available;

		$extra = array_values(array_intersect(array_diff($requested, $storable), $available));

		$args['extra_post_types'] = implode(',', $extra);
	} else {
		$args['extra_post_types'] = '';
	}

	$instance->apply_settings($args, disable_comments_ability_network_ctx($instance), true);

	$status = disable_comments_ability_status_summary($instance);
	$types  = array_values(array_map('strval', (array) $instance->get_disabled_post_types()));

	return array(
		'changed'                      => ($previous_status !== $status || $previous_types !== $types),
		'previous_status'              => $previous_status,
		'previous_mode'                => $previous_mode,
		'previous_disabled_post_types' => $previous_types,
		'status'              => $status,
		'disabled_post_types' => $types,
		'unknown_post_types'  => array_values(array_map('strval', $unknown)),
	);
}

/**
 * Execute callback for `disable-comments/delete-comments`.
 *
 * @since 2.9.0
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function disable_comments_ability_delete_comments($input = array()) {
	$instance = Disable_Comments::get_instance();

	$mode    = isset($input['mode']) ? $input['mode'] : '';
	$dry_run = !is_array($input) || !isset($input['dry_run']) || !empty($input['dry_run']);

	$map = array(
		'everywhere'    => 'delete_everywhere',
		'post_types'    => 'selected_delete_types',
		'comment_types' => 'selected_delete_comment_types',
		'spam'          => 'delete_spam',
	);

	if (!isset($map[$mode])) {
		return new WP_Error(
			'disable_comments_bad_mode',
			__('Unknown mode. Use "everywhere", "post_types", "comment_types" or "spam".', 'disable-comments'),
			array('status' => 400)
		);
	}

	$args = array(
		'delete'      => true,
		'delete_mode' => $map[$mode],
	);

	if ('post_types' === $mode) {
		// Intersected with the same set the deletion path uses. Counting a
		// private CPT the delete will then ignore would make the dry run
		// promise a number the confirmed call does not honour - which is the
		// one thing a safety preview must never do.
		$requested            = isset($input['post_types']) ? array_map('strval', (array) $input['post_types']) : array();
		$args['delete_types'] = array_values(array_intersect($requested, array_keys($instance->get_all_post_types())));
	}

	if ('comment_types' === $mode) {
		$args['delete_comment_types'] = isset($input['comment_types']) ? array_map('strval', (array) $input['comment_types']) : array();
	}

	$matched = disable_comments_ability_count_matching($instance, $args);

	if ($dry_run) {
		return array(
			'dry_run' => true,
			'deleted' => 0,
			'matched' => $matched,
		);
	}

	// A real deletion is irreversible, so it needs its own explicit yes even
	// though the caller already had to opt out of the dry run.
	if (!disable_comments_ability_confirmed($input)) {
		return disable_comments_ability_unconfirmed_error();
	}

	// Deliberately NOT the network context, unlike set-status. The network
	// branch of apply_delete_comments() only deletes on subsites named in
	// `disabled_sites`, and an ability call carries no such list - so the
	// network context would skip every site while the dry run above counted
	// the current one, reporting deleted: 0 for a confirmed request. Deletion
	// stays scoped to the site the request arrived at, which is the contract
	// stated in the ability description and in docs/ABILITIES.md.
	$before = disable_comments_ability_total_comments();
	$instance->apply_delete_comments($args, false, $args);
	$after = disable_comments_ability_total_comments();

	return array(
		'dry_run' => false,
		'deleted' => max(0, $before - $after),
		'matched' => $matched,
	);
}

/**
 * How many comments a delete request matches.
 *
 * Prefers the plugin's own preview, which shares its WHERE clause with the
 * deletion itself and so cannot disagree with it. Where that is unavailable,
 * falls back to core's comment query with the equivalent arguments - less
 * exact by construction, but honest, and far better than reporting the whole
 * table for a request that only touches spam.
 *
 * @since 2.9.0
 * @param Disable_Comments $instance Plugin instance.
 * @param array            $args     Delete arguments in the plugin's own vocabulary.
 * @return int
 */
function disable_comments_ability_count_matching($instance, $args) {
	if (method_exists($instance, 'count_comments_for_delete')) {
		$preview = $instance->count_comments_for_delete($args);

		return isset($preview['total']) ? (int) $preview['total'] : 0;
	}

	$query = array(
		'count'  => true,
		'status' => 'all',
	);

	// Allowlisted types survive every delete mode, so they must not be counted
	// as about to disappear.
	$allowed = array_values(array_map('strval', (array) $instance->get_allowed_comment_types_list()));
	if (!empty($allowed)) {
		$query['type__not_in'] = $allowed;
	}

	$mode = isset($args['delete_mode']) ? $args['delete_mode'] : '';

	if ('delete_spam' === $mode) {
		$query['status'] = 'spam';
	} elseif ('selected_delete_types' === $mode) {
		$types = isset($args['delete_types']) ? array_map('strval', (array) $args['delete_types']) : array();

		if (empty($types)) {
			return 0;
		}

		$query['post_type'] = $types;
	} elseif ('selected_delete_comment_types' === $mode) {
		$types = isset($args['delete_comment_types']) ? array_map('strval', (array) $args['delete_comment_types']) : array();
		$types = array_values(array_diff($types, $allowed));

		if (empty($types)) {
			return 0;
		}

		$query['type__in'] = $types;
		unset($query['type__not_in']);
	}

	return (int) get_comments($query);
}

/**
 * Total rows in the comments table.
 *
 * @since 2.9.0
 * @return int
 */
function disable_comments_ability_total_comments() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments");
}

/**
 * Output schema for the `disable-comments/get-status` ability.
 *
 * Kept in its own function so the shape is documented in one place and can be
 * asserted against in tests.
 *
 * @since 2.8.0
 * @return array JSON Schema describing the ability's return value.
 */
function disable_comments_ability_get_status_schema() {
	$properties = array(
		'status'                       => array(
			'type'        => 'string',
			'description' => __('Summary of where comments are disabled: "all" (everywhere), "none" (nowhere), "posts", "pages", "posts,pages", "multiple" (several specific types), or a single custom post type slug. If "excluded_for_current_user" is true, comments are still open for the requesting user regardless of this value.', 'disable-comments'),
		),
		'comments_disabled_everywhere' => array(
			'type'        => 'boolean',
			'description' => __('True when no post type on this site is left accepting comments, including non-public ones the settings screen does not list. Stricter than a "status" of "all", which only means every post type the settings screen offers is switched off — a non-public post type that supports comments can still be open in that case. Comment types listed in "allowed_comment_types" are permitted regardless, so this is not absolute when that list is non-empty. False whenever a conditional "enable" rule exists, since such a rule reopens the posts it matches.', 'disable-comments'),
		),
		'allowed_comment_types'        => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Comment types that remain enabled even where comments are disabled, such as "note" for WordPress 6.9+ editor notes. These are preserved in comment queries and permitted through the REST API, so they can still be read and created.', 'disable-comments'),
		),
		'blocked_comment_types'        => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('The mirror image of "allowed_comment_types": comment types that are closed even where comments are open, such as "review" for a WooCommerce store that closes product reviews while other comments on products stay open. Creating one of these is rejected with a 403 on both the comment form and the REST API, so check this before submitting — the post-type fields and "comments_disabled_everywhere" will not tell you. Existing comments of these types are untouched and still readable. A type appearing here is never also listed in "allowed_comment_types".', 'disable-comments'),
		),
		'disabled_post_types'          => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Post type slugs that currently have comments disabled. When comments are disabled everywhere this lists every comment-capable post type, since the global setting also covers post types registered after the settings were saved.', 'disable-comments'),
		),
		'available_post_types'         => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Post type slugs on this site that support comments and can be toggled.', 'disable-comments'),
		),
		'is_network_active'            => array(
			'type'        => 'boolean',
			'description' => __('True when the plugin is activated network-wide on a multisite install.', 'disable-comments'),
		),
		'is_network_wide_setting'      => array(
			'type'        => 'boolean',
			'description' => __('True when a super admin has chosen to apply one set of settings to every site in the network.', 'disable-comments'),
		),
		'rest_api_comments_blocked'    => array(
			'type'        => 'boolean',
			'description' => __('True when the site blocks comment endpoints in the REST API, either through the dedicated REST setting or because comments are disabled everywhere. Non-allowlisted requests to /wp/v2/comments are rejected with a 403. This is not implied by the post-type fields — comments can be open on every post type while REST is blocked — so check it before reading or creating comments over REST. Types in "allowed_comment_types" are still permitted.', 'disable-comments'),
		),
		'xmlrpc_comments_blocked'      => array(
			'type'        => 'boolean',
			'description' => __('True when the site blocks posting new comments over XML-RPC. Narrower than "rest_api_comments_blocked": only the "wp.newComment" method is removed, so reading and moderating comments over XML-RPC ("wp.getComments", "wp.editComment", "wp.deleteComment", "wp.getCommentCount" and similar) still work. Do not skip XML-RPC moderation because this is true. Independent of the post-type settings, like the REST setting.', 'disable-comments'),
		),
		'role_exclusion_enabled'       => array(
			'type'        => 'boolean',
			'description' => __('True when the site exempts one or more user roles from comment disabling.', 'disable-comments'),
		),
		'excluded_for_current_user'    => array(
			'type'        => 'boolean',
			'description' => __('True when the user making this request is exempt by role, meaning comments remain open for them even though the site disables them. Treat the other fields as the site configuration, not as what this user sees.', 'disable-comments'),
		),
		'conditional_rules_enabled'    => array(
			'type'        => 'boolean',
			'description' => __('True when per-post conditional rules are active. The post-type fields then describe only the starting point: individual posts may differ based on their taxonomy terms, page template or age. Do not infer a single post\'s comment status from "disabled_post_types" alone while this is true.', 'disable-comments'),
		),
		'conditional_rules_count'      => array(
			'type'        => 'integer',
			'description' => __('How many taxonomy or template rules are configured. Zero while "conditional_rules_enabled" is false.', 'disable-comments'),
		),
		'auto_close_days'              => array(
			'type'        => 'integer',
			'description' => __('Comments close automatically on posts older than this many days. Zero means no age limit. A post kept open by an explicit exception rule stays open past this window.', 'disable-comments'),
		),
		'woocommerce_active'           => array(
			'type'        => 'boolean',
			'description' => __('True when WooCommerce is running on this site. The review fields below only mean anything while it is.', 'disable-comments'),
		),
		'product_reviews_disabled'     => array(
			'type'        => 'boolean',
			'description' => __('True when customers cannot leave a WooCommerce product review. Reviews are usually just comments on the "product" post type, so this is mostly the answer for reviews specifically, which "disabled_post_types" does not give you in a form a store owner would recognise. It can also be true on its own: "review" appearing in "blocked_comment_types" closes reviews while comments on products stay open, and WooCommerce\'s own review setting being off closes them regardless of this plugin. False whenever WooCommerce is not active. This describes the site, not the caller: a role exemption does not change it.', 'disable-comments'),
		),
		'product_reviews_disabled_by'  => array(
			'type'        => 'string',
			'description' => __('What is blocking reviews: "disable-comments" for this plugin\'s settings, "woocommerce" for WooCommerce\'s own review switch, or an empty string when reviews are enabled. Tells an agent which setting to point the user at.', 'disable-comments'),
		),
		'plugin_version'               => array(
			'type'        => 'string',
			'description' => __('Version of the Disable Comments plugin answering this request.', 'disable-comments'),
		),
	);

	return array(
		'type'       => 'object',
		'properties' => $properties,
		// Every field is part of the contract: a successful execution always
		// returns all of them. Without this list, core's validator accepts a
		// partial object (even an empty one) and generated clients would treat
		// every documented field as optional. Derived from $properties so the
		// two cannot drift apart.
		'required'   => array_keys($properties),
	);
}

/**
 * Permission check for the `disable-comments/get-status` ability.
 *
 * Comment configuration is site-settings information, so it is gated on the
 * same capability that guards the plugin's settings screen. On multisite this
 * resolves per-site, and super admins pass everywhere.
 *
 * @since 2.8.0
 * @param mixed $input Unused. Abilities receive the same input as execute.
 * @return bool True when the current user may read the comment status.
 */
function disable_comments_ability_get_status_permission($input = null) {
	return current_user_can('manage_options');
}

/**
 * Execute callback for the `disable-comments/get-status` ability.
 *
 * Uses only the plugin's public API so it cannot drift from internals.
 *
 * @since 2.8.0
 * @param mixed $input Unused. This ability takes no input.
 * @return array|WP_Error Status payload matching the output schema, or WP_Error on failure.
 */
function disable_comments_ability_get_status($input = null) {
	if (!class_exists('Disable_Comments')) {
		return new WP_Error(
			'disable_comments_unavailable',
			__('The Disable Comments plugin is not available on this site.', 'disable-comments')
		);
	}

	$instance = Disable_Comments::get_instance();

	// Role-independent: this payload describes the site's configuration, not
	// what the calling user happens to see. See the helper for why.
	$status = disable_comments_ability_status_summary($instance);

	// get_all_post_types() is keyed by post type slug; agents want the slugs.
	$available_post_types = $instance->get_all_post_types();
	$available_post_types = is_array($available_post_types) ? array_keys($available_post_types) : array();

	// A status of 'all' is NOT enough to claim the whole site. It also comes back
	// when every *public* post type is ticked, and the settings screen only ever
	// lists public types — so a non-public post type that supports comments
	// stays open while the summary already reads 'all'. The global setting is
	// the only mode that genuinely closes everything, so require either that or
	// a check that nothing comment-capable is left open.
	$disabled_everywhere = ($instance->is_remove_everywhere_configured()
		|| $instance->is_every_comment_capable_type_disabled())
		// An "enable" rule deliberately reopens matching posts, so the claim
		// this field makes - that nothing on the site accepts comments - is
		// simply untrue when one exists. An agent reading true here would tell
		// someone their site was shut down while visitors were still commenting
		// on the exception posts.
		&& !$instance->has_enable_conditional_rules();

	if ($disabled_everywhere) {
		// The global setting closes comments on *any* post type, including ones
		// registered after the settings were last saved — while the stored list
		// only holds what was ticked at save time. Reporting the stored list
		// here would contradict comments_disabled_everywhere, so report the
		// effective set instead.
		$disabled_post_types = $available_post_types;
	} else {
		// Registered types only. A slug left behind by a deactivated CPT plugin
		// stays in the stored selection forever, and reporting it would name a
		// post type this site no longer has — while available_post_types
		// correctly omits it, so the payload would contradict itself.
		$disabled_post_types = $instance->get_disabled_post_types_registered();
		$disabled_post_types = is_array($disabled_post_types) ? array_values($disabled_post_types) : array();
	}

	// Role exclusion is a per-user override: an exempt caller still sees
	// comments even though the site disables them. Disclose it so agents do not
	// read the fields above as "what this user sees".
	$role_exclusion = $instance->get_role_exclusion_state();

	// REST and XML-RPC blocking are separate toggles from the post-type
	// settings. An agent calling over REST needs these, or it will read
	// "comments enabled" and then be rejected with a 403.
	$endpoints = $instance->get_endpoint_blocking_state();

	// WooCommerce reviews are comments on a post type, not a setting of their
	// own - resolve that here rather than making every caller work it out.
	$reviews = $instance->get_product_review_status();

	return array(
		'status'                       => (string) $status,
		'comments_disabled_everywhere' => $disabled_everywhere,
		// The allowlist survives "disable everywhere" — notes and similar types
		// are still readable and creatable. Without this an agent would treat
		// "disabled everywhere" as absolute.
		'allowed_comment_types'        => array_values(array_map('strval', $instance->get_allowed_comment_types_list())),
		// And the reverse: types closed on their own while comments stay open
		// around them. Nothing else in this payload discloses that, so an agent
		// would read "comments open on products" and post a review into a 403.
		'blocked_comment_types'        => array_values(array_map('strval', $instance->get_blocked_comment_types_list())),
		'disabled_post_types'          => array_values(array_map('strval', $disabled_post_types)),
		'available_post_types'         => array_values(array_map('strval', $available_post_types)),
		'is_network_active'            => (bool) $instance->networkactive,
		// The sitewide-settings site option survives a network deactivation, so
		// it can still be set while the plugin runs per-site. It only means
		// anything when network activated — every other consumer in the plugin
		// pairs the two, so report them coherently here too.
		'is_network_wide_setting'      => (bool) ($instance->networkactive && !empty($instance->sitewide_settings)),
		'rest_api_comments_blocked'    => !empty($endpoints['rest']),
		'xmlrpc_comments_blocked'      => !empty($endpoints['xmlrpc']),
		'role_exclusion_enabled'       => !empty($role_exclusion['enabled']),
		'excluded_for_current_user'    => !empty($role_exclusion['excluded']),
		// With rules on, the post-type fields are only a starting point: an
		// agent must not conclude a given post is closed from them alone.
		'conditional_rules_enabled'    => (bool) $instance->has_conditional_rules(),
		'conditional_rules_count'      => count($instance->get_conditional_rules()),
		'auto_close_days'              => (int) $instance->get_auto_close_days(),
		// Reviews are comments on the product post type. An agent reading
		// disabled_post_types would have to know that to answer "are reviews
		// off?", so answer it here instead.
		'woocommerce_active'           => !empty($reviews['woocommerce_active']),
		'product_reviews_disabled'     => !empty($reviews['reviews_disabled']),
		'product_reviews_disabled_by'  => isset($reviews['disabled_by']) ? (string) $reviews['disabled_by'] : '',
		'plugin_version'               => defined('DC_VERSION') ? (string) DC_VERSION : '',
	);
}
