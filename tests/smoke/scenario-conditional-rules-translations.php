<?php
/**
 * Conditional rules on a multilingual site.
 *
 * WPML and Polylang give every translation of a term its own term ID. A rule
 * stores the IDs picked on the settings screen, so it has to match the
 * translations of those terms too, or a translated post slips past it.
 *
 * Neither plugin runs in the sandbox, so their public APIs are stood in for:
 * WPML's `wpml_active_languages` and `wpml_object_id` filters, and Polylang's
 * pll_get_term_translations(). Each block uses terms of its own, because the
 * plugin keeps the expanded term list for the rest of the request.
 *
 * Seeded like the conditional-rules scenario: the feature is on at construct
 * time, so the per-post filters are registered and comments_open() is real.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

dc_check( 'the runner enabled conditional rules before construct', $dc->has_conditional_rules() );

/**
 * Apply one taxonomy rule through the save handler.
 *
 * @param Disable_Comments $dc     Plugin instance.
 * @param string           $action 'disable' or 'enable'.
 * @param int[]            $terms  Term IDs on the rule.
 * @param string           $mode   Disable mode.
 */
function dc_translations_save( $dc, $action, $terms, $mode = 'selected_types' ) {
	$dc->disable_comments_settings(
		array(
			'mode'                     => $mode,
			'disabled_types'           => array(),
			'enable_conditional_rules' => 1,
			'auto_close_days'          => 0,
			'conditional_rules'        => array(
				array(
					'type'     => 'taxonomy',
					'action'   => $action,
					'taxonomy' => 'category',
					'terms'    => $terms,
				),
			),
		)
	);
}

/**
 * A category and a post filed under it.
 *
 * @param string $label Name prefix.
 * @return array { term, post }
 */
function dc_translations_pair( $label ) {
	$term = wp_create_category( 'dc-smoke-' . $label . '-' . uniqid() );
	$post = dc_make_test_post( 'post' );
	wp_set_post_categories( $post, array( $term ) );

	return array(
		'term' => (int) $term,
		'post' => (int) $post,
	);
}

// ---------------------------------------------------------------------------
echo "\n-- no multilingual plugin: a rule only reaches its own terms --\n";

$plain_rule  = dc_translations_pair( 'plain-rule' );
$plain_other = dc_translations_pair( 'plain-other' );

dc_translations_save( $dc, 'disable', array( $plain_rule['term'] ) );

dc_check( 'closed in the rule\'s category', false === comments_open( $plain_rule['post'] ) );
dc_check( 'open in an unrelated category', true === comments_open( $plain_other['post'] ) );

// ---------------------------------------------------------------------------
echo "\n-- WPML: a translated term is matched --\n";

$wpml_en    = dc_translations_pair( 'wpml-en' );
$wpml_es    = dc_translations_pair( 'wpml-es' );
$wpml_other = dc_translations_pair( 'wpml-other' );

// "en" is the original language and "es" the translation, in both directions.
$wpml_map = array(
	$wpml_en['term'] => array( 'en' => $wpml_en['term'], 'es' => $wpml_es['term'] ),
	$wpml_es['term'] => array( 'en' => $wpml_en['term'], 'es' => $wpml_es['term'] ),
);

add_filter(
	'wpml_active_languages',
	function () {
		return array(
			'en' => array( 'code' => 'en' ),
			'es' => array( 'code' => 'es' ),
		);
	}
);
add_filter(
	'wpml_object_id',
	function ( $id, $type, $return_original, $language ) use ( &$wpml_map ) {
		if ( 'category' === $type && isset( $wpml_map[ $id ][ $language ] ) ) {
			return $wpml_map[ $id ][ $language ];
		}

		return $return_original ? $id : null;
	},
	10,
	4
);

dc_translations_save( $dc, 'disable', array( $wpml_en['term'] ) );

dc_check( 'closed on the post in the original term', false === comments_open( $wpml_en['post'] ) );
dc_check( 'closed on the post in the translated term', false === comments_open( $wpml_es['post'] ) );
dc_check( 'still open in an unrelated category', true === comments_open( $wpml_other['post'] ) );

echo "\n-- WPML: a rule saved with the translated term reaches the original --\n";

$wpml_rev_en = dc_translations_pair( 'wpml-rev-en' );
$wpml_rev_es = dc_translations_pair( 'wpml-rev-es' );
$wpml_map[ $wpml_rev_en['term'] ] = array( 'en' => $wpml_rev_en['term'], 'es' => $wpml_rev_es['term'] );
$wpml_map[ $wpml_rev_es['term'] ] = array( 'en' => $wpml_rev_en['term'], 'es' => $wpml_rev_es['term'] );

dc_translations_save( $dc, 'disable', array( $wpml_rev_es['term'] ) );

dc_check( 'closed on the post in the original term', false === comments_open( $wpml_rev_en['post'] ) );
dc_check( 'closed on the post in the translated term', false === comments_open( $wpml_rev_es['post'] ) );

echo "\n-- WPML: an exception rule reopens every translation --\n";

dc_translations_save( $dc, 'enable', array( $wpml_en['term'] ), 'remove_everywhere' );

dc_check( 'the global toggle still closes an unrelated post', false === comments_open( $wpml_other['post'] ) );
dc_check( 'the exception reopens the original', true === comments_open( $wpml_en['post'] ) );
dc_check( 'and the translation', true === comments_open( $wpml_es['post'] ) );

remove_all_filters( 'wpml_active_languages' );
remove_all_filters( 'wpml_object_id' );

// ---------------------------------------------------------------------------
echo "\n-- Polylang: a translated term is matched --\n";

$pll_en = dc_translations_pair( 'pll-en' );
$pll_es = dc_translations_pair( 'pll-es' );

$GLOBALS['dc_smoke_pll_translations'] = array(
	$pll_en['term'] => array( 'en' => $pll_en['term'], 'es' => $pll_es['term'] ),
	$pll_es['term'] => array( 'en' => $pll_en['term'], 'es' => $pll_es['term'] ),
);

if ( ! function_exists( 'pll_get_term_translations' ) ) {
	/**
	 * Stand-in for Polylang's public API.
	 *
	 * @param int $term_id Term ID.
	 * @return array Language slug => term ID.
	 */
	function pll_get_term_translations( $term_id ) {
		$map = $GLOBALS['dc_smoke_pll_translations'];

		return isset( $map[ $term_id ] ) ? $map[ $term_id ] : array();
	}
}

dc_translations_save( $dc, 'disable', array( $pll_en['term'] ) );

dc_check( 'closed on the post in the original term', false === comments_open( $pll_en['post'] ) );
dc_check( 'closed on the post in the translated term', false === comments_open( $pll_es['post'] ) );

// ---------------------------------------------------------------------------
echo "\n-- disable_comments_rule_term_ids: another plugin can add translations --\n";

$hook_en = dc_translations_pair( 'hook-en' );
$hook_fr = dc_translations_pair( 'hook-fr' );

add_filter(
	'disable_comments_rule_term_ids',
	function ( $ids, $term_ids ) use ( $hook_en, $hook_fr ) {
		if ( in_array( $hook_en['term'], $term_ids, true ) ) {
			$ids[] = $hook_fr['term'];
		}

		return $ids;
	},
	10,
	2
);

dc_translations_save( $dc, 'disable', array( $hook_en['term'] ) );

dc_check( 'closed on the post in the rule\'s term', false === comments_open( $hook_en['post'] ) );
dc_check( 'closed on the post in the term the filter added', false === comments_open( $hook_fr['post'] ) );

dc_smoke_finish( 'conditional-rules-translations' );
