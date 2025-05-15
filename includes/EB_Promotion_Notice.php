<?php

namespace Disable_Comments\Classes;

/**
 * EB_Promotion_Notice
 * This class is responsible for data sending to insights.
 * @version 3.0.0
 */

/**
 * Exit if accessed directly
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Main SDK for EB_Promotion_Notice.
 */
if (! class_exists('EB_Promotion_Notice')) :
	class EB_Promotion_Notice {
		/**
		 * EB_Promotion_Notice Version
		 */
		const VERSION = '1.0.0';

		public function __construct() {
			// Common condition for all Essential Blocks promotions
			if ( ! class_exists( 'Classic_Editor' ) && ! class_exists( 'EssentialBlocks' ) ) {
				// Essential Blocks popup Promo
				add_action( 'wpdeveloper_eb_popup_promo_init', [ $this, 'eb_popup_promo_init' ] );
				if ( ( did_action( 'wpdeveloper_eb_popup_promo_init' ) < 1 ) && ! ( get_transient( 'eael_gb_eb_popup_hide' ) || get_transient( 'wpdeveloper_gb_eb_popup_hide' ) ) ) {
					do_action( 'wpdeveloper_eb_popup_promo_init' );
				}

				// Essential Blocks Banner Promo
				add_action( 'wpdeveloper_eb_banner_promo_init', [ $this, 'dc_eb_banner_promo_init' ] );
				if ( ( did_action( 'wpdeveloper_eb_banner_promo_init' ) < 1 ) && ! ( get_transient( 'eael_eb_banner_promo_hide' ) || get_transient( 'wpdeveloper_eb_banner_promo_hide' ) ) ) {
					do_action( 'wpdeveloper_eb_banner_promo_init' );
				}

				// Essential Blocks Admin Notice
				add_action( 'wpdeveloper_eb_optin_promo_init', [ $this, 'eb_admin_notice_init' ] );
				if ( ( did_action( 'wpdeveloper_eb_optin_promo_init' ) < 1 ) && ! ( get_option( 'eael_eb_optin_hide' ) || get_transient( 'wpdeveloper_eb_optin_hide' ) ) ) {
					do_action( 'wpdeveloper_eb_optin_promo_init' );
				}
			}
		}

		/**
		 * popup notice
		 *
		 * @return void
		 */
		public function eb_popup_promo_init() {
			add_action( 'enqueue_block_editor_assets', [ $this, 'essential_blocks_promo_enqueue_scripts' ] );
			add_action( 'wp_ajax_dc_gb_eb_popup_dismiss', [ $this, 'dc_gb_eb_popup_dismiss' ] );
		}

		/**
		 * admin notice
		 *
		 * @return void
		 */
		public function eb_admin_notice_init() {
			add_action( 'admin_notices', [ $this, 'essential_block_optin' ], 100 );
			add_action( 'wp_ajax_dc_eb_optin_notice_dismiss', [ $this, 'dc_eb_optin_notice_dismiss' ] );
		}

		/**
		 * banner notice
		 *
		 * @return void
		 */
		public function dc_eb_banner_promo_init() {
			add_action( 'enqueue_block_editor_assets', [ $this, 'essential_blocks_banner_promo_enqueue_scripts' ] );
			add_action( 'wp_ajax_dc_eb_banner_promo_dismiss', [ $this, 'dc_eb_banner_promo_dismiss' ] );
		}



		public function essential_blocks_promo_enqueue_scripts() {
			if ( is_plugin_active( 'essential-blocks/essential-blocks.php' ) ) {
				return;
			}

			add_action( 'admin_footer', [ $this, 'essential_blocks_promo_admin_js_template' ] );
			wp_enqueue_script( 'dc-gutenberg', DC_ASSETS_URI . 'js/dc-essential-blocks-promo.js', [ 'jquery' ], DC_VERSION, true );
			wp_enqueue_style( 'dc-gutenberg', DC_ASSETS_URI . 'css/dc-essential-blocks-promo.css', [], DC_VERSION );
		}

		/**
		 * Gutenberg banner
		 *
		 * @return void
		 */
		public function essential_blocks_banner_promo_enqueue_scripts() {
			if (is_plugin_active('essential-blocks/essential-blocks.php')) {
				return;
			}

			add_action('admin_footer', [$this, 'essential_blocks_banner_promo_admin_js_template']);
			wp_enqueue_script('dc-gutenberg', DC_ASSETS_URI . 'js/dc-essential-blocks-promo.js', ['jquery'], DC_VERSION, true);
			wp_enqueue_style('dc-gutenberg', DC_ASSETS_URI . 'css/dc-essential-blocks-promo.css', [], DC_VERSION);
		}

		/**
		 * Gutenberg banner
		 *
		 * @return void
		 */
		public function essential_blocks_banner_promo_admin_js_template() {
			$eb_not_installed = self::get_local_plugin_data('essential-blocks/essential-blocks.php') === false;
			$action           = $eb_not_installed ? 'install' : 'activate';
			$nonce            = wp_create_nonce('disable-comments');

			?>
			<script id="dc-gb-eb-banner-promo-template" type="text/html">
				<div id="dc-gb-eb-banner-promo">
					<div class="dc-gb-eb-banner-promo-left">
						<div class="dc-gb-eb-banner-promo-image">
							<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28" fill="none">
								<g clip-path="url(#clip0_1_89)">
									<path d="M26.3347 11.0312C27.0408 10.8298 27.4499 10.0941 27.2486 9.38804L25.061 1.71694C24.8596 1.01084 24.124 0.601656 23.4179 0.803023L15.7468 2.9906C15.0407 3.19197 14.6315 3.92762 14.8329 4.63371L17.0204 12.3048C17.2218 13.0109 17.9574 13.4201 18.6636 13.2187L26.3347 11.0312Z" fill="#A5AEB8" />
									<path d="M10.0059 15.2829C10.7402 15.2829 11.3354 14.6877 11.3354 13.9534V5.97652C11.3354 5.24227 10.7402 4.64703 10.0059 4.64703H2.02901C1.29476 4.64703 0.699524 5.24227 0.699524 5.97652V13.9534C0.699524 14.6877 1.29476 15.2829 2.02901 15.2829H10.0059Z" fill="#A5AEB8" />
									<path d="M10.0059 27.2483C10.7402 27.2483 11.3354 26.6531 11.3354 25.9188V17.9419C11.3354 17.2076 10.7402 16.6124 10.0059 16.6124H2.02901C1.29476 16.6124 0.699524 17.2076 0.699524 17.9419V25.9188C0.699524 26.6531 1.29476 27.2483 2.02901 27.2483H10.0059Z" fill="#A5AEB8" />
									<path d="M21.9734 27.2483C22.7077 27.2483 23.3029 26.6531 23.3029 25.9188V17.9419C23.3029 17.2076 22.7077 16.6124 21.9734 16.6124H13.9965C13.2622 16.6124 12.667 17.2076 12.667 17.9419V25.9188C12.667 26.6531 13.2622 27.2483 13.9965 27.2483H21.9734Z" fill="#A5AEB8" />
								</g>
								<defs>
									<clipPath id="clip0_1_89">
										<rect width="28" height="28" fill="white" />
									</clipPath>
								</defs>
							</svg>
						</div>
						<div class="dc-gb-eb-banner-promo-content">
							<h3 class="dc-gb-eb-banner-promo-title"><?php _e('Want To Get All Exclusive Gutenberg Blocks For Free?', 'disable-comments'); ?></h3>
							<p class="dc-gb-eb-banner-promo-description"><?php _e('If you want to enrich your Gutenberg block library with the latest designs and functionalities, Essential Blocks can be your best companion.', 'disable-comments'); ?></p>
						</div>
					</div>
					<div class="dc-gb-eb-banner-promo-right">
						<a class="dc-gb-eb-banner-promo-learn-more" href="https://essential-blocks.com/" target="_blank"><?php _e('Learn More', 'disable-comments') ?></a>
						<button class="dc-gb-eb-banner-promo-get-block dc-gb-eb-install" data-promotype="eb-banner" data-action="<?php echo esc_attr($action); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo $eb_not_installed ? __('Get Essential Blocks', 'disable-comments') : __('Activate', 'disable-comments'); ?></b>
							<button class="dc-gb-eb-banner-promo-close" data-nonce="<?php echo esc_attr($nonce); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
									<g clip-path="url(#clip0_1_101)">
										<path d="M18 6L6 18" stroke="#7A7B80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
										<path d="M6 6L18 18" stroke="#7A7B80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
									</g>
									<defs>
										<clipPath id="clip0_1_101">
											<rect width="24" height="24" fill="white" />
										</clipPath>
									</defs>
								</svg>
							</button>
					</div>
				</div>
			</script>
			<?php
		}


		public function essential_blocks_promo_admin_js_template() {
			$eb_logo          = DC_ASSETS_URI . 'img/essential-blocks/eb-new.svg';
			$eb_promo_cross   = DC_ASSETS_URI . 'img/essential-blocks/cross.svg';
			$eb_promo_img1    = DC_ASSETS_URI . 'img/essential-blocks/eb-promo-img1.gif';
			$eb_promo_img2    = DC_ASSETS_URI . 'img/essential-blocks/eb-promo-img2.gif';
			$eb_promo_img3    = DC_ASSETS_URI . 'img/essential-blocks/eb-promo-img3.gif';
			$eb_promo_img4    = DC_ASSETS_URI . 'img/essential-blocks/eb-promo-img4.jpg';
			$eb_promo_img5    = DC_ASSETS_URI . 'img/essential-blocks/eb-promo-img5.png';
			$eb_not_installed = self::get_local_plugin_data( 'essential-blocks/essential-blocks.php' ) === false;
			$action           = $eb_not_installed ? 'install' : 'activate';
			$button_title     = $eb_not_installed ? esc_html__( 'Try Essential Blocks', 'disable-comments' ) : esc_html__( 'Activate', 'disable-comments' );
			$nonce            = wp_create_nonce( 'disable-comments' );
			?>
			<script id="dc-gb-eb-button-template" type="text/html">
				<button id="dc-eb-popup-button" type="button" class="components-button is-primary">
					<img width="20" src="<?php echo esc_url( $eb_logo ); ?>" alt=""><?php esc_html_e( 'Essential Blocks', 'disable-comments' ); ?>
				</button>
			</script>

			<script id="dc-gb-eb-popup-template" type="text/html">
				<div class="dc-gb-eb-popup">
					<div class="dc-gb-eb-header">
						<img src="<?php echo esc_url( $eb_promo_cross ); ?>" class="dc-gb-eb-dismiss" alt="">
						<div class="dc-gb-eb-tooltip"><?php esc_html_e( 'Close dialog', 'disable-comments' ); ?></div>
					</div>
					<div class="dc-gb-eb-popup-content --page-1">
						<div class="dc-gb-eb-content">
							<div class="dc-gb-eb-content-image">
								<img src="<?php echo esc_url( $eb_promo_img1 ); ?>" alt="">
							</div>
							<div class="dc-gb-eb-content-pagination">
								<span class="active" data-page="1"></span>
								<span data-page="2"></span>
								<span data-page="3"></span>
								<span data-page="4"></span>
								<span data-page="5"></span>
							</div>
							<div class="dc-gb-eb-content-info">
								<h3><?php esc_html_e( 'Supercharge Your Gutenberg Experience With Essential Blocks', 'disable-comments' ); ?></h3>
								<p><?php esc_html_e( 'If you like Disable Comments, check out Essential Blocks, the ultimate block library for Gutenberg that is trusted by more than 100,000+ web creators.', 'disable-comments' ); ?></p>
								<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
							</div>
						</div>
						<div class="dc-gb-eb-footer">
							<button class="dc-gb-eb-never-show" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Skip for Now', 'disable-comments' ); ?></button>
							<button class="dc-gb-eb-prev"><?php esc_html_e( 'Previous', 'disable-comments' ); ?></button>
							<button class="dc-gb-eb-next"><?php esc_html_e( 'Next', 'disable-comments' ); ?></button>
						</div>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-1" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img1 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Supercharge Your Gutenberg Experience With Essential Blocks', 'disable-comments' ); ?></h3>
						<p><?php esc_html_e( 'If you like Disable Comments, check out Essential Blocks, the ultimate block library for Gutenberg that is trusted by more than 100,000+ web creators.', 'disable-comments' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-2" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img2 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( '60+ Amazing Gutenberg Blocks', 'disable-comments' ); ?></h3>
						<p><?php esc_html_e( 'Create & design your WordPress websites just the way you want with more than 60 amazing, ready blocks from Essential Blocks for Gutenberg.', 'disable-comments' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-3" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img3 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Useful Block Control Option', 'disable-comments' ); ?></h3>
						<p><?php esc_html_e( 'Get the fastest loading time and smoothest experience on your web page by enabling and disabling individual blocks as per your requirements.', 'disable-comments' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-4" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img4 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Access To Thousands Of Ready Gutenberg Templates', 'disable-comments' ); ?></h3>
						<p><?php esc_html_e( 'Design unique websites using ready Gutenberg templates from Templately with absolute ease and instantly grab attention.', 'disable-comments' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-5" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img5 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Try Essential Blocks Today!', 'disable-comments' ); ?></h3>
						<p><?php printf( __( 'Want to get started with Essential Blocks now? Check out %scomplete guides for each blocks%s to learn more about this ultimate block library for Gutenberg.', 'disable-comments' ), '<a href="https://essential-blocks.com/demo" target="_blank">', '</a>' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-promotype="eb-popup" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
						<button class="dc-gb-eb-never-show" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Skip for Now', 'disable-comments' ); ?></button>
					</div>
				</div>
			</script>
			<?php
		}


		public function essential_block_optin() {
			if ( is_plugin_active( 'essential-blocks/essential-blocks.php' ) ) {
				return;
			}

			$screen           = get_current_screen();
			$is_exclude       = ! empty( $_GET['post_type'] ) && in_array( $_GET['post_type'], [ 'elementor_library', 'product' ] );
			$ajax_url         = admin_url( 'admin-ajax.php' );
			$nonce            = wp_create_nonce( 'disable-comments' );
			$eb_not_installed = self::get_local_plugin_data( 'essential-blocks/essential-blocks.php' ) === false;
			$action           = $eb_not_installed ? 'install' : 'activate';
			$button_title     = $eb_not_installed ? esc_html__( 'Install Essential Blocks', 'disable-comments' ) : esc_html__( 'Activate', 'disable-comments' );

			if ( $screen->parent_base !== 'edit' || $is_exclude ) {
				return;
			}
			?>
			<div class="wpnotice-wrapper notice  notice-info is-dismissible dc-eb-optin-notice">
				<div class="wpnotice-content-wrapper">
					<div class="dc-eb-optin">
						<h3><?php esc_html_e( 'Using Gutenberg? Check out Essential Blocks!', 'disable-comments' ); ?></h3>
						<p><?php _e( 'Are you using the Gutenberg Editor for your website? Then try out Essential Blocks for Gutenberg, and explore 60+ unique blocks to make your web design experience in WordPress even more powerful. 🚀', 'disable-comments' ); ?></p>
						<p><?php _e( 'For more information, <a href="https://essential-blocks.com/demo/" target="_blank">check out the demo here</a>.', 'disable-comments' ); ?></p>
						<p>
							<a href="#" class="button-primary wpdeveloper-eb-plugin-installer" data-promotype="eb-notice" data-action="<?php echo esc_attr( $action ); ?>"><?php echo esc_html( $button_title ); ?></a>
						</p>
					</div>
				</div>
			</div>

			<script>
				// install/activate plugin
				(function ($) {
					$(document).on("click", ".wpdeveloper-eb-plugin-installer", function (ev) {
						ev.preventDefault();

						var button = $(this),
							promoType = button.data("promotype") ?? "",
							action = button.data("action");

						if ($.active && typeof action != "undefined") {
							button.text("Waiting...").attr("disabled", true);

							setInterval(function () {
								if (!$.active) {
									button.attr("disabled", false).trigger("click");
								}
							}, 1000);
						}

						if (action === "install" && !$.active) {
							button.text("Installing...").attr("disabled", true);

							$.ajax({
								url: "<?php echo esc_html( $ajax_url ); ?>",
								type: "POST",
								data: {
									action: "wpdeveloper_install_plugin",
									security: "<?php echo esc_html( $nonce ); ?>",
									slug: "essential-blocks",
									promotype: promoType,
								},
								success: function (response) {
									if (response.success) {
										button.text("Activated");
										button.data("action", null);

										setTimeout(function () {
											location.reload();
										}, 1000);
									} else {
										button.text("Install");
									}

									button.attr("disabled", false);
								},
								error: function (err) {
									console.log(err.responseJSON);
								},
							});
						} else if (action === "activate" && !$.active) {
							button.text("Activating...").attr("disabled", true);

							$.ajax({
								url: "<?php echo esc_html( $ajax_url ); ?>",
								type: "POST",
								data: {
									action: "wpdeveloper_activate_plugin",
									security: "<?php echo esc_html( $nonce ); ?>",
									basename: "essential-blocks/essential-blocks.php",
								},
								success: function (response) {
									if (response.success) {
										button.text("Activated");
										button.data("action", null);

										setTimeout(function () {
											location.reload();
										}, 1000);
									} else {
										button.text("Activate");
									}

									button.attr("disabled", false);
								},
								error: function (err) {
									console.log(err.responseJSON);
								},
							});
						}
					}).on('click', '.dc-eb-optin-notice button.notice-dismiss', function (e) {
						e.preventDefault();

						var $notice_wrapper = $(this).closest('.dc-eb-optin-notice');

						$.ajax({
							url: "<?php echo esc_html( $ajax_url ); ?>",
							type: "POST",
							data: {
								action: "dc_eb_optin_notice_dismiss",
								security: "<?php echo esc_html( $nonce ); ?>",
							},
							success: function (response) {
								if (response.success) {
									$notice_wrapper.remove();
								} else {
									console.log(response.data);
								}
							},
							error: function (err) {
								console.log(err.responseText);
							},
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		public static function get_local_plugin_data($basename = '') {
			if (empty($basename)) {
				return false;
			}

			if (!function_exists('get_plugins')) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins = get_plugins();

			if (!isset($plugins[$basename])) {
				return false;
			}

			return $plugins[$basename];
		}


		public function dc_eb_optin_notice_dismiss() {
			check_ajax_referer( 'disable-comments', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this action', 'disable-comments' ) );
			}

			// update_option( 'wpdeveloper_eb_optin_hide', true );
			set_transient( 'wpdeveloper_eb_optin_hide', true, MONTH_IN_SECONDS * 2);
			wp_send_json_success();
		}

		public function dc_gb_eb_popup_dismiss() {
			check_ajax_referer( 'disable-comments', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this action', 'disable-comments' ) );
			}

			set_transient( 'wpdeveloper_gb_eb_popup_hide', true, MONTH_IN_SECONDS * 2 );
			wp_send_json_success();
		}

		public function dc_eb_banner_promo_dismiss() {
			check_ajax_referer( 'disable-comments', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this action', 'disable-comments' ) );
			}

			set_transient( 'wpdeveloper_eb_banner_promo_hide', true, DAY_IN_SECONDS * 45 );
			wp_send_json_success();
		}
	}
endif;
