<?php
/**
 * Tips about xSpeed Cache, shown on this plugin's own settings screen.
 *
 * Four spots, each tied to what the admin is doing at that moment:
 *
 *  - sidebar : a "How fast is your site?" card beside the settings form.
 *  - save    : one line under the Save button after a successful save.
 *  - delete  : one line under the Delete button after a large delete.
 *  - footer  : a "Free Speed Test" link in the footer links.
 *
 * Rules every tip follows:
 *  - Rendered only by this plugin's own views. No admin notices, nothing on
 *    the dashboard, the plugins list or the editor.
 *  - Never shown when xSpeed Cache is already active. The sidebar then shows
 *    a status line instead, because the save handler refreshes xSpeed's page
 *    cache (see Disable_Comments::purge_page_caches()).
 *  - Dismissing a tip hides it for that user for good. The save and delete
 *    tips also count as dismissed once they have been shown.
 *  - Nothing is sent until an admin clicks "Scan my site free". That click
 *    sends this site's address to xSpeed Scan as an unlisted scan (see
 *    start_scan() and the readme's External services section). Every other
 *    link is a plain link with a UTM tag, and the install link opens
 *    WordPress's own plugin details window.
 *  - The `disable_comments_show_xspeed_tips` filter turns every tip off.
 *
 * @package Disable_Comments
 * @since   2.9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * xSpeed Cache tips.
 */
class Disable_Comments_XSpeed_Tips {

	/**
	 * User meta: list of tip ids this user has dismissed or already seen.
	 */
	const DISMISSED_META = 'disable_comments_xspeed_tips_dismissed';

	/**
	 * Tip ids a user can dismiss.
	 */
	const TIP_IDS = 'sidebar,save,delete';

	/**
	 * WordPress.org slug of xSpeed Cache.
	 */
	const WPORG_SLUG = 'xspeed';

	/**
	 * xSpeed Scan endpoint that starts a scan.
	 */
	const SCAN_API = 'https://xspeedcache.com/api/scan';

	/**
	 * Register hooks. Called once from the plugin constructor.
	 */
	public static function init() {
		add_action( 'wp_ajax_disable_comments_dismiss_xspeed_tip', array( __CLASS__, 'ajax_dismiss' ) );
		add_action( 'wp_ajax_disable_comments_start_xspeed_scan', array( __CLASS__, 'ajax_start_scan' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Is xSpeed Cache active on this site?
	 *
	 * @return bool
	 */
	public static function xspeed_active() {
		return defined( 'XSPEED_VERSION' );
	}

	/**
	 * May tips be shown to the current user at all?
	 *
	 * @return bool
	 */
	public static function allowed() {
		/**
		 * Filter whether Disable Comments may show tips about xSpeed Cache.
		 *
		 * Return false to remove every tip, the footer link included.
		 *
		 * @since 2.9.2
		 *
		 * @param bool $show Whether tips may be shown.
		 */
		if ( ! apply_filters( 'disable_comments_show_xspeed_tips', true ) ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( is_network_admin() ) {
			return current_user_can( 'manage_network_options' );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Should this tip be shown to the current user?
	 *
	 * @param string $tip Tip id.
	 * @return bool
	 */
	public static function should_show( $tip ) {
		if ( ! self::allowed() ) {
			return false;
		}

		// Once xSpeed is active only the sidebar status line remains.
		if ( self::xspeed_active() ) {
			return 'sidebar' === $tip;
		}

		if ( 'footer' === $tip ) {
			return true;
		}

		return ! in_array( $tip, self::dismissed(), true );
	}

	/**
	 * Tip ids the current user has dismissed or seen.
	 *
	 * @return string[]
	 */
	public static function dismissed() {
		$list = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );

		return is_array( $list ) ? array_values( array_filter( $list, 'is_string' ) ) : array();
	}

	/**
	 * Cache plugins xSpeed can import settings from.
	 *
	 * Each entry: plugin file => array( name, a function or constant that only
	 * exists once that plugin has actually loaded ).
	 *
	 * @return array
	 */
	private static function importable_plugins() {
		return array(
			'wp-rocket/wp-rocket.php'                 => array( 'WP Rocket', 'rocket_clean_domain' ),
			'litespeed-cache/litespeed-cache.php'     => array( 'LiteSpeed Cache', 'LSCWP_V' ),
			'w3-total-cache/w3-total-cache.php'       => array( 'W3 Total Cache', 'w3tc_flush_posts' ),
			'wp-super-cache/wp-cache.php'             => array( 'WP Super Cache', 'wp_cache_clear_cache' ),
		);
	}

	/**
	 * Name of the cache plugin this site actually runs, when xSpeed can
	 * import its settings.
	 *
	 * Both must hold: the plugin is active (on this site or network-wide), and
	 * its code is loaded. A leftover function from a drop-in, a host's
	 * must-use shim or a deactivated plugin's folder is not enough.
	 *
	 * @return string Plugin name, or '' when none is active.
	 */
	public static function importable_cache_plugin() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::importable_plugins() as $file => $plugin ) {
			list( $name, $loaded ) = $plugin;

			if ( ! is_plugin_active( $file ) ) {
				continue;
			}

			if ( function_exists( $loaded ) || defined( $loaded ) ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Outbound link with a campaign tag.
	 *
	 * @param string $path     Path on xspeedcache.com.
	 * @param string $campaign Which tip the click came from.
	 * @return string
	 */
	public static function link( $path, $campaign ) {
		return add_query_arg(
			array(
				'utm_source'   => 'disable-comments',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'tip-' . $campaign,
			),
			'https://xspeedcache.com/' . ltrim( $path, '/' )
		);
	}

	/**
	 * Load the script and styles on this plugin's settings screen.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if (
			'settings_page_' . DC_PLUGIN_SLUG !== $hook_suffix &&
			'options-general_' . DC_PLUGIN_SLUG !== $hook_suffix
		) {
			return;
		}

		if ( ! self::allowed() ) {
			return;
		}

		wp_enqueue_style( 'disable-comments-xspeed-tips', DC_ASSETS_URI . 'css/xspeed-tips.css', array(), DC_VERSION );

		if ( self::xspeed_active() ) {
			return;
		}

		wp_enqueue_script( 'disable-comments-xspeed-tips', DC_ASSETS_URI . 'js/xspeed-tips.js', array( 'jquery' ), DC_VERSION, true );
		wp_localize_script(
			'disable-comments-xspeed-tips',
			'disableCommentsXSpeedTips',
			array(
				'action'     => 'disable_comments_dismiss_xspeed_tip',
				'scanAction' => 'disable_comments_start_xspeed_scan',
				'starting'   => __( 'Starting scan…', 'disable-comments' ),
				'tabTitle'   => __( 'Starting your speed scan…', 'disable-comments' ),
				'nonce'      => wp_create_nonce( 'disable_comments_save_settings' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				/**
				 * Filter how many comments a delete must remove before the
				 * database tip appears.
				 *
				 * @since 2.9.2
				 *
				 * @param int $min Minimum deleted comments. Default 50.
				 */
				'deleteMin'  => (int) apply_filters( 'disable_comments_xspeed_delete_tip_min', 50 ),
			)
		);
	}

	/**
	 * AJAX: hide a tip for the current user.
	 */
	public static function ajax_dismiss() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'disable_comments_save_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'disable-comments' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'disable-comments' ) ), 403 );
		}

		$tip = isset( $_POST['tip'] ) ? sanitize_key( wp_unslash( $_POST['tip'] ) ) : '';

		if ( ! in_array( $tip, explode( ',', self::TIP_IDS ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown tip.', 'disable-comments' ) ), 400 );
		}

		$list = self::dismissed();
		if ( ! in_array( $tip, $list, true ) ) {
			$list[] = $tip;
			update_user_meta( get_current_user_id(), self::DISMISSED_META, $list );
		}

		wp_send_json_success();
	}

	/**
	 * Start an xSpeed Scan of this site.
	 *
	 * Runs only when an admin clicks "Scan my site free". The browser cannot
	 * call xSpeed Scan itself (no cross-origin access), so the site does it
	 * and hands back xSpeed's scan experience page for that scan, the same
	 * page the scan box on the xspeedcache.com home page opens. It plays the
	 * scan out while it runs.
	 *
	 * The scan is unlisted: it is left out of xSpeed Scan's public list of
	 * recent scans, and only someone with the report link can open it.
	 *
	 * @param string $campaign Which tip was clicked, for the link's campaign tag.
	 * @return array { ok: bool, url: string, message?: string }
	 */
	public static function start_scan( $campaign = 'sidebar' ) {
		$campaign = in_array( $campaign, array( 'sidebar', 'save' ), true ) ? $campaign : 'sidebar';
		$fallback = self::link( '', $campaign );

		/**
		 * Filter the address xSpeed Scan is asked to test.
		 *
		 * @since 2.9.2
		 *
		 * @param string $url Home page of this site.
		 */
		$site = (string) apply_filters( 'disable_comments_xspeed_scan_url', home_url( '/' ) );

		$response = wp_remote_post(
			self::SCAN_API,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'url'        => $site,
						'visibility' => 'unlisted',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'url'     => $fallback,
				'message' => __( 'xSpeed Scan could not be reached, so it opens without starting a scan.', 'disable-comments' ),
			);
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$report = is_array( $data ) && isset( $data['reportUrl'] ) ? (string) $data['reportUrl'] : '';
		$scan   = is_array( $data ) && isset( $data['scanId'] ) ? (string) $data['scanId'] : '';

		// Only trust a scan xSpeed Scan really started: its report must be on
		// xspeedcache.com, and the scan id must be a plain id.
		if (
			'' === $report ||
			'xspeedcache.com' !== wp_parse_url( $report, PHP_URL_HOST ) ||
			0 !== strpos( (string) wp_parse_url( $report, PHP_URL_PATH ), '/scan/' ) ||
			! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/', $scan )
		) {
			$message = __( 'xSpeed Scan could not start a scan of this site, so it opens without one.', 'disable-comments' );
			if ( is_array( $data ) && isset( $data['error'] ) && 'invalid_url' === $data['error'] ) {
				$message = __( 'This site is not reachable from the internet (for example a local or staging site), so xSpeed Scan opens without starting a scan.', 'disable-comments' );
			}

			return array(
				'ok'      => false,
				'url'     => $fallback,
				'message' => $message,
			);
		}

		return array(
			'ok'  => true,
			'url' => add_query_arg(
				array(
					'scan'  => rawurlencode( $scan ),
					'start' => 1,
				),
				self::link( 'experience/', $campaign )
			),
		);
	}

	/**
	 * AJAX: start a scan and return the page to open.
	 */
	public static function ajax_start_scan() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'disable_comments_save_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'disable-comments' ) ), 403 );
		}

		if ( ! self::allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'disable-comments' ) ), 403 );
		}

		$campaign = isset( $_POST['tip'] ) ? sanitize_key( wp_unslash( $_POST['tip'] ) ) : 'sidebar';

		wp_send_json_success( self::start_scan( $campaign ) );
	}

	/**
	 * Print one tip.
	 *
	 * @param string $tip Tip id: sidebar, save, delete or footer.
	 */
	public static function render( $tip ) {
		if ( ! self::should_show( $tip ) ) {
			return;
		}

		switch ( $tip ) {
			case 'sidebar':
				self::render_sidebar();
				break;
			case 'save':
				self::render_inline(
					'save',
					sprintf(
						/* translators: %s: link to a free speed scan. */
						esc_html__( 'Your comment settings are live. Want to see how fast these pages load for visitors? %s', 'disable-comments' ),
						'<a href="' . esc_url( self::link( '', 'save' ) ) . '" target="_blank" rel="noopener noreferrer" data-dc-xspeed-scan="save">' . esc_html__( 'Run a free speed scan', 'disable-comments' ) . '</a>'
					)
				);
				break;
			case 'delete':
				self::render_inline(
					'delete',
					sprintf(
						/* translators: 1: number of comments removed, 2: link to xSpeed's database docs. */
						esc_html__( 'Your database is %1$s rows lighter. xSpeed\'s Database Clean can also remove old revisions, expired transients and orphaned metadata, and shows you the list before deleting. %2$s', 'disable-comments' ),
						'<span class="dc-xspeed-tip__count"></span>',
						'<a href="' . esc_url( self::link( 'docs/database', 'delete' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'See what xSpeed cleans', 'disable-comments' ) . '</a>'
					)
				);
				break;
			case 'footer':
				echo '<li><a class="dc-xspeed-footer-link" href="' . esc_url( self::link( 'scan/', 'footer' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Free Speed Test', 'disable-comments' ) . '</a></li>';
				break;
		}
	}

	/**
	 * The sidebar card, or the status line when xSpeed is active.
	 */
	private static function render_sidebar() {
		if ( self::xspeed_active() ) {
			?>
			<div class="dc-xspeed-card dc-xspeed-card--active">
				<p><?php esc_html_e( 'xSpeed Cache is active. Disable Comments refreshes its page cache whenever you save.', 'disable-comments' ); ?></p>
			</div>
			<?php
			return;
		}

		$cache_plugin = self::importable_cache_plugin();
		?>
		<div class="dc-xspeed-card" data-dc-xspeed-tip="sidebar">
			<button type="button" class="dc-xspeed-tip__close" data-dc-xspeed-dismiss="sidebar" aria-label="<?php esc_attr_e( 'Dismiss this tip', 'disable-comments' ); ?>">&times;</button>
			<div class="dc-xspeed-card__head">
				<span class="dc-xspeed-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
						<path d="M4.5 18.5a9 9 0 1 1 15 0"/>
						<path class="dc-xspeed-card__needle" d="M12 14.5l4-5"/>
						<circle cx="12" cy="14.5" r="1.4" fill="currentColor" stroke="none"/>
					</svg>
				</span>
				<h4 class="dc-xspeed-card__title"><?php esc_html_e( 'How fast is your site?', 'disable-comments' ); ?></h4>
			</div>
			<p class="dc-xspeed-card__body"><?php esc_html_e( 'Run a free speed scan. No sign-up and nothing to install. You get a score and a fix for each slow spot.', 'disable-comments' ); ?></p>
			<?php if ( '' !== $cache_plugin ) : ?>
				<p class="dc-xspeed-card__context">
					<?php
					printf(
						/* translators: 1: name of the active cache plugin, 2: link to the migration docs. */
						esc_html__( 'Using %1$s? xSpeed Cache can import its settings in one click! %2$s', 'disable-comments' ),
						'<strong>' . esc_html( $cache_plugin ) . '</strong>',
						'<a href="' . esc_url( self::link( 'docs/migration', 'sidebar-migrate' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How it works', 'disable-comments' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
			<a class="dc-xspeed-btn dc-xspeed-btn--solid dc-xspeed-btn--block" href="<?php echo esc_url( self::link( '', 'sidebar' ) ); ?>" target="_blank" rel="noopener noreferrer" data-dc-xspeed-scan="sidebar"><?php esc_html_e( 'Scan my site free', 'disable-comments' ); ?></a>
			<p class="dc-xspeed-card__note" role="status" hidden></p>
			<p class="dc-xspeed-card__about">
				<?php
				printf(
					/* translators: %s: link to xSpeed Cache on WordPress.org. */
					esc_html__( 'Fix what it finds with %s, free on WordPress.org.', 'disable-comments' ),
					'<a href="' . esc_url( 'https://wordpress.org/plugins/' . self::WPORG_SLUG . '/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'xSpeed Cache', 'disable-comments' ) . '</a>'
				);
				?>
			</p>
			<p class="dc-xspeed-card__foot">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"/>
					<path d="m9 12 2 2 4-4"/>
				</svg>
				<span>
					<?php
					printf(
						/* translators: %s: WPDeveloper, the team name. */
						esc_html__( 'Backed by your trusted %s team', 'disable-comments' ),
						'<strong>WPDeveloper</strong>'
					);
					?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * A one-line tip, hidden until the script reveals it.
	 *
	 * @param string $tip  Tip id.
	 * @param string $html Escaped message.
	 */
	private static function render_inline( $tip, $html ) {
		?>
		<div class="dc-xspeed-tip" data-dc-xspeed-tip="<?php echo esc_attr( $tip ); ?>" role="status" hidden>
			<span class="dc-xspeed-tip__dot" aria-hidden="true"></span>
			<p class="dc-xspeed-tip__text"><?php echo wp_kses_post( $html ); ?></p>
			<button type="button" class="dc-xspeed-tip__close" data-dc-xspeed-dismiss="<?php echo esc_attr( $tip ); ?>" aria-label="<?php esc_attr_e( 'Dismiss this tip', 'disable-comments' ); ?>">&times;</button>
		</div>
		<?php
	}
}
