<?php
/**
 * Settings export and import.
 *
 * The interesting half is not "a round trip works" — it is that an import is
 * an uploaded file. Every value in it is attacker-controlled, so the payload
 * is validated key by key and anything unrecognised is dropped rather than
 * written into the options.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

/**
 * Apply a configuration through the real save handler.
 *
 * @param Disable_Comments $dc   Plugin instance.
 * @param array            $args Form data.
 */
function dc_save( $dc, $args ) {
	$dc->disable_comments_settings( $args );
}

// ---------------------------------------------------------------------------
echo "\n-- the panel the handlers are driven from --\n";

// Rendered first, before any save in this file has run. apply_settings()
// rebuilds $options from the payload and drops `sitewide_settings`, which the
// partial reads unguarded — a pre-existing wart, but rendering after a save
// would spray warnings through this scenario's output for reasons that have
// nothing to do with what it is testing.
$render = Closure::bind(
	function () {
		ob_start();
		// The export/import panel moved to the Settings tab in the tab split.
		include DC_PLUGIN_VIEWS_PATH . 'partials/_settings.php';
		return ob_get_clean();
	},
	$dc,
	'Disable_Comments'
);
$html = $render();

// Preview and Apply arrive disabled and unlock a step apart: preview once a
// file has been read, apply once a preview has found changes. That is correct
// behaviour, but it only reads as deliberate because the markup carries a live
// hint saying which step is next — without it, two dead buttons on arrival
// look like a broken panel, which is what was reported.
dc_check( 'the panel renders the step hint the JS drives', false !== strpos( $html, 'id="import_dc_settings_hint"' ) );
dc_check( 'and the file-name readout it fills in', false !== strpos( $html, 'id="import_dc_settings_filename"' ) );

// Disabled in the markup, not only from JS: neither button may be live in the
// window before the script runs.
dc_check(
	'preview starts disabled in the markup',
	1 === preg_match( '/id="import_dc_settings_preview"[^>]*\sdisabled/', $html )
);
dc_check(
	'apply starts disabled in the markup',
	1 === preg_match( '/id="import_dc_settings_apply"[^>]*\sdisabled/', $html )
);

// One class across the three actions is what gives them a single button style
// and the deliberate disabled treatment. The trailing quote matters: without
// it this also counts the two `dc-portability__actions` wrappers.
dc_check( 'the three actions share the action class', 3 === substr_count( $html, 'dc-portability__action"' ) );

// The file input keeps its id and stays a real file input — the label only
// dresses it — so `this.files[0]` in the change handler still finds a file.
dc_check(
	'the file input is still a real file input',
	1 === preg_match( '/<input type="file"\s+id="import_dc_settings_file"/', $html )
);

// ---------------------------------------------------------------------------
echo "\n-- export shape --\n";

dc_save(
	$dc,
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array( 'post', 'page' ),
		'remove_xmlrpc_comments'   => 1,
		'remove_rest_API_comments' => 0,
		'show_existing_comments'   => 1,
		'allowed_comment_types'    => array( 'note' ),
		'enable_exclude_by_role'   => 1,
		'exclude_by_role'          => array( 'administrator' ),
	)
);

$export = $dc->export_settings();

dc_check( 'the export declares a schema version', Disable_Comments::EXPORT_SCHEMA_VERSION === $export['schema_version'] );
dc_check( 'the export records the plugin version', !empty( $export['plugin_version'] ) );
dc_check( 'the export is timestamped', !empty( $export['exported_at'] ) );
dc_check( 'the export carries the disabled post types', array( 'post', 'page' ) === $export['settings']['disabled_post_types'] );
dc_check( 'the export carries the allowlist', array( 'note' ) === $export['settings']['allowed_comment_types'] );
dc_check( 'the export carries the role exclusion', array( 'administrator' ) === $export['settings']['exclude_by_role'] );

// Site-specific and internal state must not travel.
foreach ( array( 'disabled_sites', 'db_version', 'settings_saved', 'sitewide_settings', 'is_network_options' ) as $forbidden ) {
	dc_check( "the export omits {$forbidden}", !isset( $export['settings'][ $forbidden ] ) );
}

$json = wp_json_encode( $export );
dc_check( 'the export encodes to JSON', is_string( $json ) && '' !== $json );

// ---------------------------------------------------------------------------
echo "\n-- dry run reports without writing --\n";

// Move the site somewhere else so the diff has something to report.
dc_save(
	$dc,
	array(
		'mode'                   => 'selected_types',
		'disabled_types'         => array(),
		'remove_xmlrpc_comments' => 0,
		'show_existing_comments' => 0,
		'allowed_comment_types'  => array(),
		'enable_exclude_by_role' => 0,
		'exclude_by_role'        => array(),
	)
);

$before = $dc->export_settings();
$result = $dc->import_settings( $json, true );

dc_check( 'the dry run succeeded', !is_wp_error( $result ) );
dc_check( 'the dry run says it applied nothing', false === $result['applied'] );
dc_check( 'the dry run reported changes', !empty( $result['changes'] ) );
dc_check( 'a change names both sides', isset( $result['changes']['disabled_post_types']['from'] ) && isset( $result['changes']['disabled_post_types']['to'] ) );
dc_check( 'the dry run changed nothing', $before === $dc->export_settings() );

// ---------------------------------------------------------------------------
echo "\n-- applying the import --\n";

$result = $dc->import_settings( $json, false );

dc_check( 'the import applied', true === $result['applied'] );

$after = $dc->export_settings();

dc_check( 'post types round-tripped', array( 'post', 'page' ) === $after['settings']['disabled_post_types'] );
dc_check( 'the allowlist round-tripped', array( 'note' ) === $after['settings']['allowed_comment_types'] );
dc_check( 'the role exclusion round-tripped', array( 'administrator' ) === $after['settings']['exclude_by_role'] );
dc_check( 'the xmlrpc toggle round-tripped', 1 === $after['settings']['remove_xmlrpc_comments'] );

// A second identical import is a no-op, not an error.
$result = $dc->import_settings( $json, true );
dc_check( 're-importing the same file reports no changes', empty( $result['changes'] ) );

// ---------------------------------------------------------------------------
echo "\n-- post types the destination does not have --\n";

$foreign = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'disabled_post_types' => array( 'post', 'dc_not_here', 'another_missing' ),
		),
	)
);

$result = $dc->import_settings( $foreign, true );

dc_check( 'unknown post types are reported, not silently dropped', array( 'dc_not_here', 'another_missing' ) === $result['unknown_post_types'] );

$dc->import_settings( $foreign, false );
$after = $dc->export_settings();

dc_check( 'and are not written into the options', array( 'post' ) === $after['settings']['disabled_post_types'] );

// ---------------------------------------------------------------------------
echo "\n-- hostile payloads --\n";

$before = $dc->export_settings();

$rejected = array(
	'not JSON at all'              => 'this is not json {{{',
	'valid JSON, wrong document'   => '{"hello":"world"}',
	'settings is not an object'    => '{"settings":"nope"}',
	'no recognised keys'           => '{"settings":{"evil_key":1,"another":"x"}}',
);

foreach ( $rejected as $label => $payload ) {
	$result = $dc->import_settings( $payload, false );
	dc_check( "rejected: {$label}", is_wp_error( $result ) );
}

dc_check( 'no rejected payload wrote anything', $before === $dc->export_settings() );

// A file from a future format version must not be applied on a guess.
$newer = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION + 1,
		'settings'       => array( 'remove_everywhere' => true ),
	)
);
$result = $dc->import_settings( $newer, false );

dc_check( 'a newer schema version is refused', is_wp_error( $result ) );
dc_check( 'and the error says to update the plugin', false !== strpos( $result->get_error_message(), 'newer version' ) );

// ---------------------------------------------------------------------------
echo "\n-- unknown keys are dropped, known ones coerced --\n";

$mixed = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'remove_everywhere'      => 'yes',
			'disabled_post_types'    => array( 'post', array( 'nested' ), '', 'PAGE' ),
			'show_existing_comments' => 0,
			'exclude_by_role'        => 'not-an-array',
			'evil_key'               => 'rm -rf',
			'db_version'             => 999,
			'settings_saved'         => false,
		),
	)
);

$dc->import_settings( $mixed, false );
$after   = $dc->export_settings();
$options = get_option( 'disable_comments_options' );

dc_check( 'a string truthy value became a real boolean', true === $after['settings']['remove_everywhere'] );
dc_check( 'nested and empty entries were stripped', array( 'post', 'page' ) === $after['settings']['disabled_post_types'] );
dc_check( 'a non-array where a list was expected became an empty list', array() === $after['settings']['exclude_by_role'] );
dc_check( 'an unknown key never reached the options', !isset( $options['evil_key'] ) );
dc_check( 'db_version was not taken from the file', Disable_Comments::DB_VERSION === (int) $options['db_version'] );

// ---------------------------------------------------------------------------
echo "\n-- an import purges page caches --\n";

$purges = 0;
add_action(
	'disable_comments_purge_caches',
	function () use ( &$purges ) {
		$purges++;
	}
);

$dc->import_settings( $json, true );
dc_check( 'a dry run purges nothing', 0 === $purges );

$dc->import_settings( $json, false );
dc_check( 'applying an import purges the page cache', 1 === $purges );

// An identical file changes nothing, so it must not rewrite the options or
// invalidate every subsite's page cache while reporting "nothing to change".
$purges = 0;
$result = $dc->import_settings( $json, false );

dc_check( 'a no-op import reports no changes', empty( $result['changes'] ) );
dc_check( 'and says it applied nothing', false === $result['applied'] );
dc_check( 'and purges nothing', 0 === $purges );

// ---------------------------------------------------------------------------
echo "\n-- custom post types survive a change of activation mode --\n";

// A network export keeps custom slugs in extra_post_types; a single-site one
// keeps them in disabled_post_types. Copying both across verbatim loses them
// in one direction or the other, because the two fields are read differently
// depending on how the plugin is activated.
$from_network = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'disabled_post_types' => array( 'post' ),
			'extra_post_types'    => array( 'page' ),
		),
	)
);

$dc->import_settings( $from_network, false );
$options = get_option( 'disable_comments_options' );

// `page` is registered here, so it belongs in the list this site actually
// reads rather than sitting in a field only a network install consults.
dc_check(
	'a registered slug from extra_post_types is applied on this site',
	in_array( 'page', (array) $options['disabled_post_types'], true )
);
dc_check(
	'and the original selection is kept too',
	in_array( 'post', (array) $options['disabled_post_types'], true )
);
dc_check(
	'the effective disabled list includes it',
	in_array( 'page', (array) $dc->get_disabled_post_types(), true )
);

// An unregistered slug in either field is reported, and on a single site it is
// not stored anywhere that would pretend it took effect.
$with_unknown = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'disabled_post_types' => array( 'post' ),
			'extra_post_types'    => array( 'dc_absent_cpt' ),
		),
	)
);

$result = $dc->import_settings( $with_unknown, true );
dc_check( 'an unknown slug in extra_post_types is reported', in_array( 'dc_absent_cpt', $result['unknown_post_types'], true ) );

$dc->import_settings( $with_unknown, false );
$options = get_option( 'disable_comments_options' );
dc_check( 'and is not stored on a single site', empty( $options['extra_post_types'] ) );

// ---------------------------------------------------------------------------
echo "\n-- textual booleans --\n";

// A producer that encodes booleans as strings would otherwise have
// "remove_everywhere":"false" disable comments everywhere, because PHP treats
// every non-empty string except "0" as true.
foreach ( array( 'false', 'FALSE', 'no', 'off', '0', '' ) as $falsey ) {
	$dc->import_settings(
		wp_json_encode(
			array(
				'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
				'settings'       => array( 'remove_everywhere' => true ),
			)
		),
		false
	);

	$dc->import_settings(
		wp_json_encode(
			array(
				'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
				'settings'       => array( 'remove_everywhere' => $falsey ),
			)
		),
		false
	);

	dc_check( "\"{$falsey}\" imports as false", false === $dc->export_settings()['settings']['remove_everywhere'] );
}

foreach ( array( 'true', 'yes', 'on', '1' ) as $truthy ) {
	$dc->import_settings(
		wp_json_encode(
			array(
				'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
				'settings'       => array( 'remove_everywhere' => $truthy ),
			)
		),
		false
	);

	dc_check( "\"{$truthy}\" imports as true", true === $dc->export_settings()['settings']['remove_everywhere'] );
}

// ---------------------------------------------------------------------------
echo "\n-- the preview describes what will actually be stored --\n";

$dc->import_settings(
	wp_json_encode(
		array(
			'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
			'settings'       => array( 'remove_everywhere' => false, 'disabled_post_types' => array(), 'extra_post_types' => array() ),
		)
	),
	false
);

// Network-shaped payload landing on a single site: `page` moves fields during
// normalisation, so a diff taken before that describes a value never written.
$cross = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'disabled_post_types' => array( 'post' ),
			'extra_post_types'    => array( 'page' ),
		),
	)
);

$preview = $dc->import_settings( $cross, true );
$dc->import_settings( $cross, false );
$stored = get_option( 'disable_comments_options' );

dc_check(
	'the preview named the post-type list that was actually written',
	isset( $preview['changes']['disabled_post_types'] )
		&& $preview['changes']['disabled_post_types']['to'] === array_values( (array) $stored['disabled_post_types'] )
);
dc_check(
	'and did not promise an extra_post_types value that was never stored',
	! isset( $preview['changes']['extra_post_types'] )
		|| $preview['changes']['extra_post_types']['to'] === array_values( (array) $stored['extra_post_types'] )
);

// ---------------------------------------------------------------------------
echo "\n-- a partial import leaves post types alone --\n";

$dc->import_settings(
	wp_json_encode(
		array(
			'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
			'settings'       => array( 'disabled_post_types' => array( 'post', 'page' ) ),
		)
	),
	false
);

// A payload mentioning neither post-type field must not re-split the site's
// existing lists — a disabled custom type that is temporarily unregistered
// would be discarded and its comments silently re-enabled later.
$before = $dc->export_settings()['settings']['disabled_post_types'];

$dc->import_settings(
	wp_json_encode(
		array(
			'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
			'settings'       => array( 'show_existing_comments' => true ),
		)
	),
	false
);

dc_check( 'an unrelated partial import does not touch the post types', $before === $dc->export_settings()['settings']['disabled_post_types'] );
dc_check( 'and still applies what it did carry', true === $dc->export_settings()['settings']['show_existing_comments'] );

// ---------------------------------------------------------------------------
echo "\n-- per-site role rules never enter the network option --\n";

// The network settings screen deliberately does not offer role exclusion. A
// single-site export carrying "exclude logged-out users" would otherwise apply
// an invisible rule to every subsite, leaving comments open to the public with
// nothing on the network screen to explain it.
$with_roles = wp_json_encode(
	array(
		'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
		'settings'       => array(
			'remove_everywhere'      => true,
			'enable_exclude_by_role' => true,
			'exclude_by_role'        => array( 'administrator' ),
		),
	)
);

// Single-site import: the rule is a legitimate per-site setting and applies.
$dc->import_settings( $with_roles, false, false );
dc_check( 'a single-site import applies the role rule', true === $dc->export_settings()['settings']['enable_exclude_by_role'] );

// Reset, then import in a network context.
$dc->import_settings(
	wp_json_encode(
		array(
			'schema_version' => Disable_Comments::EXPORT_SCHEMA_VERSION,
			// remove_everywhere too, or the assertion below that a network
			// import still applies what it may has nothing left to change.
			'settings'       => array( 'remove_everywhere' => false, 'enable_exclude_by_role' => false, 'exclude_by_role' => array() ),
		)
	),
	false,
	false
);

$preview = $dc->import_settings( $with_roles, true, true );

dc_check( 'a network import does not report the role rule as a change', ! isset( $preview['changes']['enable_exclude_by_role'] ) );
dc_check( 'nor the excluded roles', ! isset( $preview['changes']['exclude_by_role'] ) );
dc_check( 'but still applies what it may', isset( $preview['changes']['remove_everywhere'] ) );

// ---------------------------------------------------------------------------
echo "\n-- conditional rules travel with the settings --\n";

// Rules ship in the same release as this exporter. Leaving them out of the
// portable map made the file quietly incomplete: the screen says the settings
// were copied, and the destination silently gets no taxonomy, template or age
// rules at all.
$dc->apply_settings(
	array(
		'enable_conditional_rules' => 1,
		'auto_close_days'          => 45,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( 3 ) ),
		),
		'disabled_types'           => array( 'post' ),
	),
	false,
	false
);

$export   = $dc->export_settings();
$exported = $export['settings'];

dc_check( 'the export carries enable_conditional_rules', ! empty( $exported['enable_conditional_rules'] ) );
dc_check( 'and the rules themselves', 1 === count( (array) $exported['conditional_rules'] ) );
dc_check( 'and auto_close_days', 45 === (int) $exported['auto_close_days'] );

// Clear them, then import the file back — a round trip is the only thing that
// proves the export is actually restorable rather than merely populated.
$dc->apply_settings( array( 'disabled_types' => array() ), false, false );
$wiped = get_option( 'disable_comments_options' );
dc_check( 'the site really is cleared before the import', 0 === count( (array) $wiped['conditional_rules'] ) );

$dc->import_settings( wp_json_encode( $export ), false, false );
$restored = get_option( 'disable_comments_options' );

dc_check( 'importing restores enable_conditional_rules', ! empty( $restored['enable_conditional_rules'] ) );
dc_check( 'importing restores the rules', 1 === count( (array) $restored['conditional_rules'] ) );
dc_check( 'importing restores auto_close_days', 45 === (int) $restored['auto_close_days'] );

// ---------------------------------------------------------------------------
echo "\n-- taxonomy rules travel by slug, not by term id --\n";

// Term IDs are rows in one site's terms table. Exporting them was worse than
// omitting the rules altogether: the destination has a term 104 too, something
// entirely unrelated, so the import silently closed comments on the wrong
// category while the one the operator meant stayed open. The same-site round
// trip above cannot catch that — it passes with raw IDs.
$slug   = 'dc-port-' . substr( uniqid(), -6 );
$source = wp_insert_term( 'Src ' . $slug, 'category', array( 'slug' => $slug ) );
$src_id = (int) $source['term_id'];

$dc->apply_settings(
	array(
		'enable_conditional_rules' => 1,
		'conditional_rules'        => array(
			array( 'type' => 'taxonomy', 'action' => 'disable', 'taxonomy' => 'category', 'terms' => array( $src_id ) ),
		),
		'disabled_types'           => array(),
	),
	false,
	false
);

$export    = $dc->export_settings();
$exported  = $export['settings']['conditional_rules'][0]['terms'];

dc_check( 'the export carries a slug, not a term id', array( $slug ) === array_values( $exported ) );

// Stand in for a different site: same slug, different id. The decoy in between
// guarantees the new id cannot coincide with the old one.
wp_delete_term( $src_id, 'category' );
wp_insert_term( 'Decoy ' . uniqid(), 'category' );
$dest    = wp_insert_term( 'Dst ' . $slug, 'category', array( 'slug' => $slug ) );
$dest_id = (int) $dest['term_id'];

dc_check( 'the destination really does use a different id', $dest_id !== $src_id );

$dc->apply_settings( array( 'enable_conditional_rules' => 0, 'conditional_rules' => array(), 'disabled_types' => array() ), false, false );
$dc->import_settings( wp_json_encode( $export ), false, false );

$stored    = get_option( 'disable_comments_options' );
$resolved  = array_map( 'intval', (array) $stored['conditional_rules'][0]['terms'] );

dc_check( 'importing resolves the slug to the destination term', in_array( $dest_id, $resolved, true ) );
dc_check( 'and does not carry the source id across', ! in_array( $src_id, $resolved, true ) );

// A slug the destination has never heard of cannot be matched, so the rule is
// dropped rather than stored pointing at nothing.
$orphan = $export;
$orphan['settings']['conditional_rules'][0]['terms'] = array( 'dc-not-here-' . uniqid() );
$dc->apply_settings( array( 'enable_conditional_rules' => 0, 'conditional_rules' => array(), 'disabled_types' => array() ), false, false );
$dc->import_settings( wp_json_encode( $orphan ), false, false );
$stored = get_option( 'disable_comments_options' );

dc_check( 'a rule naming an unknown term is dropped, not stored empty', 0 === count( (array) $stored['conditional_rules'] ) );

dc_smoke_finish( 'settings-portability' );
