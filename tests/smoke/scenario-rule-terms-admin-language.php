<?php
/**
 * A rule's stored terms survive an admin working in another language.
 *
 * WPML filters get_terms() to the admin's current language and makes
 * get_term() / get_term_by() return the current language's translation. The
 * settings screen, export and import read a rule's stored terms back, so in a
 * Spanish admin a rule on "News" plus the English-only "Archive" used to come
 * back as "Noticias" alone: the form submitted only that, the next save
 * rewrote the rule and dropped "Archive", and an export wrote Spanish slugs.
 *
 * WPML does not run in the sandbox, so its language behaviour is stood in for
 * with its public hooks (`wpml_current_language`, `wpml_switch_language`)
 * plus `get_terms` / `get_term` filters that act like its language filter.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();
wp_set_current_user( 1 );

$news     = (int) wp_create_category( 'dc-smoke-news-' . uniqid() );
$noticias = (int) wp_create_category( 'dc-smoke-noticias-' . uniqid() );
$archive  = (int) wp_create_category( 'dc-smoke-archive-' . uniqid() );

$GLOBALS['dc_smoke_lang']       = 'en';
$GLOBALS['dc_smoke_term_lang']  = array( $news => 'en', $noticias => 'es', $archive => 'en' );
$GLOBALS['dc_smoke_translation'] = array(
	$news     => array( 'es' => $noticias ),
	$noticias => array( 'en' => $news ),
);

add_filter(
	'wpml_current_language',
	function () {
		return $GLOBALS['dc_smoke_lang'];
	}
);
add_action(
	'wpml_switch_language',
	function ( $lang ) {
		$GLOBALS['dc_smoke_lang'] = $lang;
	}
);
// Like WPML: list only the current language's terms, in the SQL, so each
// language's query is cached apart as it is with WPML...
add_filter(
	'terms_clauses',
	function ( $clauses ) {
		$lang = $GLOBALS['dc_smoke_lang'];
		if ( 'all' === $lang ) {
			return $clauses;
		}
		$hide = array();
		foreach ( $GLOBALS['dc_smoke_term_lang'] as $id => $term_lang ) {
			if ( $lang !== $term_lang ) {
				$hide[] = (int) $id;
			}
		}
		if ( $hide ) {
			$clauses['where'] .= ' AND t.term_id NOT IN (' . implode( ',', $hide ) . ')';
		}
		return $clauses;
	}
);
// ...and hand back the current language's translation of one term.
add_filter(
	'get_term',
	function ( $term ) {
		static $busy = false;
		$lang = $GLOBALS['dc_smoke_lang'];
		if ( $busy || 'all' === $lang || ! $term instanceof WP_Term || empty( $GLOBALS['dc_smoke_translation'][ $term->term_id ][ $lang ] ) ) {
			return $term;
		}
		$busy       = true;
		$translated = get_term( $GLOBALS['dc_smoke_translation'][ $term->term_id ][ $lang ], $term->taxonomy );
		$busy       = false;
		return $translated;
	}
);

$news_slug    = get_term( $news, 'category' )->slug;
$archive_slug = get_term( $archive, 'category' )->slug;

$dc->disable_comments_settings(
	array(
		'mode'                     => 'selected_types',
		'disabled_types'           => array(),
		'enable_conditional_rules' => 1,
		'auto_close_days'          => 0,
		'conditional_rules'        => array(
			array(
				'type'     => 'taxonomy',
				'action'   => 'disable',
				'taxonomy' => 'category',
				'terms'    => array( $news, $archive ),
			),
		),
	)
);

$stored = get_option( 'disable_comments_options' );
dc_check( 'the rule is stored with the English terms', array( $news, $archive ) === $stored['conditional_rules'][0]['terms'] );

// ---------------------------------------------------------------------------
echo "\n-- the stand-in behaves like WPML in a Spanish admin --\n";

$GLOBALS['dc_smoke_lang'] = 'es';
$plain = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'include' => array( $news, $archive ) ) );
dc_check( 'a plain lookup of the stored terms finds neither', array() === array_values( wp_list_pluck( (array) $plain, 'term_id' ) ) );
dc_check( 'get_term() of News returns Noticias', $noticias === (int) get_term( $news, 'category' )->term_id );

// ---------------------------------------------------------------------------
echo "\n-- settings screen in a Spanish admin --\n";

require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen( 'settings_page_disable_comments_settings' );
ob_start();
$dc->settings_page();
$html = ob_get_clean();

preg_match( '#<select id="conditional_rule_terms".*?</select>#s', $html, $select );
preg_match_all( '#<option value="(\d+)" selected>#', isset( $select[0] ) ? $select[0] : '', $selected );
$submitted = array_map( 'intval', $selected[1] );
sort( $submitted );
$expected = array( $news, $archive );
sort( $expected );

dc_check( 'the form submits exactly the stored terms', $expected === $submitted );
dc_check( 'the admin language is back to Spanish afterwards', 'es' === $GLOBALS['dc_smoke_lang'] );

// ---------------------------------------------------------------------------
echo "\n-- export and import in a Spanish admin --\n";

$export = $dc->export_settings();
$slugs  = $export['settings']['conditional_rules'][0]['terms'];
dc_check( 'export writes the stored terms\' own slugs', array( $news_slug, $archive_slug ) === $slugs );

$resolve = new ReflectionMethod( $dc, 'rules_from_portable' );
$resolve->setAccessible( true );
$back = $resolve->invoke( $dc, $export['settings']['conditional_rules'] );
dc_check( 'import resolves the file back to the stored term IDs', isset( $back[0]['terms'] ) && array( $news, $archive ) === $back[0]['terms'] );
dc_check( 'the admin language is still Spanish', 'es' === $GLOBALS['dc_smoke_lang'] );

// ---------------------------------------------------------------------------
echo "\n-- no multilingual plugin: unchanged --\n";

remove_all_filters( 'wpml_current_language' );
remove_all_actions( 'wpml_switch_language' );
remove_all_filters( 'terms_clauses' );
remove_all_filters( 'get_term' );

$export = $dc->export_settings();
$back   = $resolve->invoke( $dc, $export['settings']['conditional_rules'] );
dc_check( 'export and import round-trip the stored IDs', isset( $back[0]['terms'] ) && array( $news, $archive ) === $back[0]['terms'] );

dc_smoke_finish( 'rule-terms-admin-language' );
