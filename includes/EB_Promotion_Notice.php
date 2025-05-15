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

			//Essential Blocks Promo
			if ( ! class_exists( 'Classic_Editor' ) && ! class_exists( 'EssentialBlocks' ) && ( ! get_option( 'dc_eb_optin_hide' ) || ! get_transient( 'dc_gb_eb_popup_hide' ) ) ) {
				add_action( 'enqueue_block_editor_assets', [ $this, 'essential_blocks_promo_enqueue_scripts' ] );
				// add_action( 'admin_notices', [ $this, 'essential_block_optin' ] );
				add_action( 'dc_admin_notices', [ $this, 'essential_block_special_optin' ], 100 );
				add_action( 'wp_ajax_dc_eb_optin_notice_dismiss', [ $this, 'dc_eb_optin_notice_dismiss' ] );
				add_action( 'wp_ajax_dc_gb_eb_popup_dismiss', [ $this, 'dc_gb_eb_popup_dismiss' ] );
			}
			//Essential Blocks Banner Promo
			if (! class_exists('Classic_Editor') && ! class_exists('EssentialBlocks') && ! get_transient('dc_eb_banner_promo_hide')) {
				add_action('enqueue_block_editor_assets', [$this, 'essential_blocks_banner_promo_enqueue_scripts']);
				add_action('wp_ajax_dc_eb_banner_promo_dismiss', [$this, 'dc_eb_banner_promo_dismiss']);
			}
		}


		public function essential_blocks_promo_enqueue_scripts() {
			if ( is_plugin_active( 'essential-blocks/essential-blocks.php' ) || get_transient( 'dc_gb_eb_popup_hide' ) ) {
				return;
			}

			add_action( 'admin_footer', [ $this, 'essential_blocks_promo_admin_js_template' ] );
			wp_enqueue_script( 'dc-gutenberg', DC_ASSETS_URI . 'js/dc-essential-blocks-promo.js', [ 'jquery' ], DC_VERSION, true );
			wp_enqueue_style( 'dc-gutenberg', DC_ASSETS_URI . 'css/dc-essential-blocks-promo.css', [], DC_VERSION );
		}

		public function essential_blocks_banner_promo_enqueue_scripts() {
			if (is_plugin_active('essential-blocks/essential-blocks.php')) {
				return;
			}

			add_action('admin_footer', [$this, 'essential_blocks_banner_promo_admin_js_template']);
			wp_enqueue_script('dc-gutenberg', DC_ASSETS_URI . 'js/dc-essential-blocks-promo.js', ['jquery'], DC_VERSION, true);
			wp_enqueue_style('dc-gutenberg', DC_ASSETS_URI . 'css/dc-essential-blocks-promo.css', [], DC_VERSION);
		}




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
							<h3 class="dc-gb-eb-banner-promo-title"><?php _e('Want To Get All Exclusive Gutenberg Blocks For Free?', 'essential-addons-for-elementor-lite'); ?></h3>
							<p class="dc-gb-eb-banner-promo-description"><?php _e('If you want to enrich your Gutenberg block library with the latest designs and functionalities, Essential Blocks can be your best companion.', 'essential-addons-for-elementor-lite'); ?></p>
						</div>
					</div>
					<div class="dc-gb-eb-banner-promo-right">
						<a class="dc-gb-eb-banner-promo-learn-more" href="https://essential-blocks.com/" target="_blank"><?php _e('Learn More', 'essential-addons-for-elementor-lite') ?></a>
						<button class="dc-gb-eb-banner-promo-get-block dc-gb-eb-install" data-promotype="eb-banner" data-action="<?php echo esc_attr($action); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo $eb_not_installed ? __('Get Essential Blocks', 'essential-addons-for-elementor-lite') : __('Activate', 'essential-addons-for-elementor-lite'); ?></b>
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
			$button_title     = $eb_not_installed ? esc_html__( 'Try Essential Blocks', 'essential-addons-for-elementor-lite' ) : esc_html__( 'Activate', 'essential-addons-for-elementor-lite' );
			$nonce            = wp_create_nonce( 'essential-addons-elementor' );
			?>
			<script id="dc-gb-eb-button-template" type="text/html">
				<button id="dc-eb-popup-button" type="button" class="components-button is-primary">
					<img width="20" src="<?php echo esc_url( $eb_logo ); ?>" alt=""><?php esc_html_e( 'Essential Blocks', 'essential-addons-for-elementor-lite' ); ?>
				</button>
			</script>

			<script id="dc-gb-eb-popup-template" type="text/html">
				<div class="dc-gb-eb-popup">
					<div class="dc-gb-eb-header">
						<img src="<?php echo esc_url( $eb_promo_cross ); ?>" class="dc-gb-eb-dismiss" alt="">
						<div class="dc-gb-eb-tooltip"><?php esc_html_e( 'Close dialog', 'essential-addons-for-elementor-lite' ); ?></div>
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
								<h3><?php esc_html_e( 'Supercharge Your Gutenberg Experience With Essential Blocks', 'essential-addons-for-elementor-lite' ); ?></h3>
								<p><?php esc_html_e( 'If you like Essential Addons for Elementor, check out Essential Blocks, the ultimate block library for Gutenberg that is trusted by more than 60,000+ web creators.', 'essential-addons-for-elementor-lite' ); ?></p>
								<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
							</div>
						</div>
						<div class="dc-gb-eb-footer">
							<button class="dc-gb-eb-never-show" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Skip for Now', 'essential-addons-for-elementor-lite' ); ?></button>
							<button class="dc-gb-eb-prev"><?php esc_html_e( 'Previous', 'essential-addons-for-elementor-lite' ); ?></button>
							<button class="dc-gb-eb-next"><?php esc_html_e( 'Next', 'essential-addons-for-elementor-lite' ); ?></button>
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
						<h3><?php esc_html_e( 'Supercharge Your Gutenberg Experience With Essential Blocks', 'essential-addons-for-elementor-lite' ); ?></h3>
						<p><?php esc_html_e( 'If you like Essential Addons for Elementor, check out Essential Blocks, the ultimate block library for Gutenberg that is trusted by more than 60,000+ web creators.', 'essential-addons-for-elementor-lite' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-2" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img2 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( '40+ Amazing Gutenberg Blocks', 'essential-addons-for-elementor-lite' ); ?></h3>
						<p><?php esc_html_e( 'Create & design your WordPress websites just the way you want with more than 40 amazing, ready blocks from Essential Blocks for Gutenberg.', 'essential-addons-for-elementor-lite' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-3" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img3 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Useful Block Control Option', 'essential-addons-for-elementor-lite' ); ?></h3>
						<p><?php esc_html_e( 'Get the fastest loading time and smoothest experience on your web page by enabling and disabling individual blocks as per your requirements.', 'essential-addons-for-elementor-lite' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-4" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img4 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Access To Thousands Of Ready Gutenberg Templates', 'essential-addons-for-elementor-lite' ); ?></h3>
						<p><?php esc_html_e( 'Design unique websites using ready Gutenberg templates from Templately with absolute ease and instantly grab attention.', 'essential-addons-for-elementor-lite' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
					</div>
				</div>
			</script>

			<script id="dc-gb-eb-button-template-page-5" type="text/html">
				<div>
					<div class="dc-gb-eb-content-image">
						<img src="<?php echo esc_url( $eb_promo_img5 ); ?>" alt="">
					</div>
					<div class="dc-gb-eb-content-info">
						<h3><?php esc_html_e( 'Try Essential Blocks Today!', 'essential-addons-for-elementor-lite' ); ?></h3>
						<p><?php printf( __( 'Want to get started with Essential Blocks now? Check out %scomplete guides for each blocks%s to learn more about this ultimate block library for Gutenberg.', 'essential-addons-for-elementor-lite' ), '<a href="https://essential-blocks.com/demo" target="_blank">', '</a>' ) ?></p>
						<button class="dc-gb-eb-install components-button is-primary" data-action="<?php echo esc_attr( $action ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php echo esc_html( $button_title ); ?></button>
						<button class="dc-gb-eb-never-show" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Skip for Now', 'essential-addons-for-elementor-lite' ); ?></button>
					</div>
				</div>
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



		public function dc_eb_banner_promo_dismiss() {
			check_ajax_referer('disable-comments', 'security');

			if (! current_user_can('manage_options')) {
				wp_send_json_error(__('You are not allowed to do this action', 'essential-addons-for-elementor-lite'));
			}

			set_transient('dc_eb_banner_promo_hide', true, DAY_IN_SECONDS * 45);
			wp_send_json_success();
		}
	}
endif;
