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
			'description' => __('True when no post type on this site is left accepting comments, including non-public ones the settings screen does not list. Stricter than a "status" of "all", which only means every post type the settings screen offers is switched off — a non-public post type that supports comments can still be open in that case. Comment types listed in "allowed_comment_types" are permitted regardless, so this is not absolute when that list is non-empty.', 'disable-comments'),
		),
		'allowed_comment_types'        => array(
			'type'        => 'array',
			'items'       => array('type' => 'string'),
			'description' => __('Comment types that remain enabled even where comments are disabled, such as "note" for WordPress 6.9+ editor notes. These are preserved in comment queries and permitted through the REST API, so they can still be read and created.', 'disable-comments'),
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

	// get_current_comment_status() resolves its "all" branch through
	// is_remove_everywhere(), which returns false for a role-exempt caller. That
	// is correct for filtering, but this payload describes the *site's*
	// configuration, so read the global setting role-independently and let it
	// win. For non-exempt callers this is identical to what the method returns.
	$status = $instance->is_remove_everywhere_configured()
		? 'all'
		: $instance->get_current_comment_status();

	// get_all_post_types() is keyed by post type slug; agents want the slugs.
	$available_post_types = $instance->get_all_post_types();
	$available_post_types = is_array($available_post_types) ? array_keys($available_post_types) : array();

	// A status of 'all' is NOT enough to claim the whole site. It also comes back
	// when every *public* post type is ticked, and the settings screen only ever
	// lists public types — so a non-public post type that supports comments
	// stays open while the summary already reads 'all'. The global setting is
	// the only mode that genuinely closes everything, so require either that or
	// a check that nothing comment-capable is left open.
	$disabled_everywhere = $instance->is_remove_everywhere_configured()
		|| $instance->is_every_comment_capable_type_disabled();

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

	return array(
		'status'                       => (string) $status,
		'comments_disabled_everywhere' => $disabled_everywhere,
		// The allowlist survives "disable everywhere" — notes and similar types
		// are still readable and creatable. Without this an agent would treat
		// "disabled everywhere" as absolute.
		'allowed_comment_types'        => array_values(array_map('strval', $instance->get_allowed_comment_types_list())),
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
		'plugin_version'               => defined('DC_VERSION') ? (string) DC_VERSION : '',
	);
}
