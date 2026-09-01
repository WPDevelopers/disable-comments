<?php
/**
 * The Site Health "Disable Comments" section.
 *
 * This panel is what somebody pastes into a support ticket, so a sentence in
 * it that is not true costs an afternoon. It reported "Comments are enabled
 * everywhere" on sites where a conditional rule was closing comments on every
 * post in a category — the section described the global and post-type settings
 * and nothing else.
 *
 * The assertions below go through the real `debug_information` filter rather
 * than calling the method, because a field that is computed correctly and
 * never reaches the panel is still a field nobody can see.
 *
 * Run: bin/smoke-test.sh
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

/**
 * The plugin's own Site Health fields, as the panel receives them.
 *
 * @return array
 */
function dc_site_health_fields() {
	$info = apply_filters( 'debug_information', array() );

	return isset( $info['disable-comments']['fields'] ) ? $info['disable-comments']['fields'] : array();
}

/**
 * One field's rendered value.
 *
 * @param string $key Field key.
 * @return string
 */
function dc_site_health_value( $key ) {
	$fields = dc_site_health_fields();

	return isset( $fields[ $key ]['value'] ) ? (string) $fields[ $key ]['value'] : '';
}

$fields = dc_site_health_fields();

dc_check( 'the plugin registers a Site Health section', ! empty( $fields ) );

if ( empty( $fields ) ) {
	dc_smoke_finish( 'site-health' );
	return;
}

// ---------------------------------------------------------------------------
echo "\n-- with no rules configured --\n";

$dc->apply_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 0,
		'conditional_rules'        => array(),
		'auto_close_days'          => 0,
	),
	false,
	false
);

dc_check( 'the rules line is reported even when off', 'Disabled' === dc_site_health_value( 'conditional_rules' ) );
dc_check( 'with nothing configured', '0' === dc_site_health_value( 'conditional_rules_count' ) );
dc_check( 'and no age limit', 'No age limit' === dc_site_health_value( 'auto_close_days' ) );

// Nothing is disabled and nothing overrides it, so the plain sentence is the
// true one and must not be qualified.
dc_check( 'the status reads as enabled everywhere', 'Comments are enabled everywhere' === dc_site_health_value( 'status' ) );

// ---------------------------------------------------------------------------
echo "\n-- with a rule closing comments on some posts --\n";

// Deliberately: nothing disabled globally or by post type, so the only thing
// closing comments is the rule. This is the exact configuration the panel used
// to describe as "Comments are enabled everywhere".
$term    = wp_insert_term( 'dc-health-' . uniqid(), 'category' );
$term_id = is_wp_error( $term ) ? 1 : (int) $term['term_id'];

$dc->apply_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 1,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( $term_id ) ),
		),
		'auto_close_days'          => 30,
	),
	false,
	false
);

// Guards the assertions below: a rule that is not actually in force would make
// every one of them pass for the wrong reason.
$ruled = dc_make_test_post( 'post' );
wp_set_object_terms( $ruled, array( $term_id ), 'category' );

$options = get_option( 'disable_comments_options' );

dc_check( 'nothing is disabled globally or by post type', empty( $options['remove_everywhere'] ) && 0 === count( (array) $options['disabled_post_types'] ) );
dc_check( 'but the rule really does close this post', true === $dc->is_disabled_for_post( $ruled ) );

dc_check( 'the rules line now reports them on', 'Enabled' === dc_site_health_value( 'conditional_rules' ) );
dc_check( 'and how many are configured', '1' === dc_site_health_value( 'conditional_rules_count' ) );
dc_check( 'and the age limit with its unit', '30 days' === dc_site_health_value( 'auto_close_days' ) );

// The headline sentence is the one that was wrong. It still describes the
// settings — it simply stops claiming they are the whole story.
$status = dc_site_health_value( 'status' );

dc_check( 'the status no longer claims comments are simply enabled everywhere', 'Comments are enabled everywhere' !== $status );
dc_check( 'it still says what the settings do', false !== strpos( $status, 'Comments are enabled everywhere' ) );
dc_check( 'and says the rules override that per post', false !== strpos( $status, 'Conditional rules' ) );

// ---------------------------------------------------------------------------
echo "\n-- an age limit on its own is still a rule --\n";

// auto_close_days with no taxonomy or template rule closes comments on every
// post old enough, so the panel has to disclose it even with a rule count of
// zero.
$dc->apply_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 1,
		'conditional_rules'        => array(),
		'auto_close_days'          => 1,
	),
	false,
	false
);

dc_check( 'an age limit alone counts as rules being on', 'Enabled' === dc_site_health_value( 'conditional_rules' ) );
dc_check( 'with no rules listed', '0' === dc_site_health_value( 'conditional_rules_count' ) );
dc_check( 'and the window named in the singular', '1 day' === dc_site_health_value( 'auto_close_days' ) );
dc_check( 'and the status still discloses it', false !== strpos( dc_site_health_value( 'status' ), 'Conditional rules' ) );

// ---------------------------------------------------------------------------
echo "\n-- rules stored but switched off --\n";

// The screen keeps them so the switch is reversible; nothing enforces them.
// Reporting them as active here would send someone hunting for a rule that is
// doing nothing.
$dc->apply_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 0,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( $term_id ) ),
		),
		'auto_close_days'          => 30,
	),
	false,
	false
);

$options = get_option( 'disable_comments_options' );

dc_check( 'the rule is still stored', 1 === count( (array) $options['conditional_rules'] ) );
dc_check( 'but Site Health reports the layer as off', 'Disabled' === dc_site_health_value( 'conditional_rules' ) );
dc_check( 'and counts nothing as enforced', '0' === dc_site_health_value( 'conditional_rules_count' ) );
dc_check( 'the age limit is not enforced either', 'No age limit' === dc_site_health_value( 'auto_close_days' ) );
dc_check( 'so the status is not qualified', false === strpos( dc_site_health_value( 'status' ), 'Conditional rules' ) );

// ---------------------------------------------------------------------------
echo "\n-- the section still reports everything it did before --\n";

$fields = dc_site_health_fields();

$expected = array(
	'status',
	'plugin_configured',
	'total_comments',
	'global_disable',
	'disabled_post_type_count',
	'disabled_post_types',
	'xmlrpc_comments',
	'rest_api_comments',
	'show_existing_comments',
	'network_active',
	'sitewide_settings',
	'role_exclusion_enabled',
	'excluded_roles',
);

foreach ( $expected as $key ) {
	dc_check( "the {$key} field is still there", isset( $fields[ $key ] ) );
}

// Every field the panel renders needs both halves; a missing label prints an
// empty row.
$complete = true;
foreach ( $fields as $key => $field ) {
	if ( ! isset( $field['label'] ) || ! isset( $field['value'] ) ) {
		$complete = false;
		echo "        incomplete field: {$key}\n";
	}
}

dc_check( 'every field has a label and a value', $complete );

dc_smoke_finish( 'site-health' );
