<?php
/**
 * DisableComments_Plugin_Tracker
 * This class is responsible for data sending to insights.
 * @version 3.0.0
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main SDK for DisableComments_Plugin_Tracker.
 */
if( ! class_exists('DisableComments_Plugin_Tracker') ) :
	class DisableComments_Plugin_Tracker {
		/**
		 * WP Insights Version
		 */
		const WPINS_VERSION = '3.0.2';
		/**
		 * API URL
		 */
		const API_URL = 'https://send.wpinsight.com/process-plugin-data';
		/**
		 * Installed Plugin File
		 *
		 * @var string
		 */
		private $plugin_file = null;
		/**
		 * Installed Plugin Name
		 *
		 * @var string
		 */
		private $plugin_name = null;
		/**
		 * How often the event should subsequently
		 * @var string
		 */
		public $recurrence = 'daily';
		public $notice_options = [];
		public $item_id = null;
		public $disabled_wp_cron;
		public $enable_self_cron;
		public $require_optin;
		public $include_goodbye_form;
		public $marketing;
		public $options;
		private $event_hook = null;
		/**
		 * Instace of DisableComments_Plugin_Tracker
		 * @var DisableComments_Plugin_Tracker
		 */
		private static $_instance = null;
		/**
		 * Get Instance of DisableComments_Plugin_Tracker
		 * @return DisableComments_Plugin_Tracker
		 */
		public static function get_instance( $plugin_file, $args = [] ){
			if( is_null( static::$_instance ) ) {
				static::$_instance = new static( $plugin_file, $args );
			}
			return static::$_instance;
		}
		/**
		 * Automatically Invoked when initialized.
		 *
		 * @param array $args
		 */
		public function __construct( $plugin_file, $args = [] ){
			$this->plugin_file          = $plugin_file;
			$this->plugin_name          = basename( $this->plugin_file, '.php' );
			$this->disabled_wp_cron     = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true;
			$this->enable_self_cron     = $this->disabled_wp_cron == true ? true : false;

			$this->event_hook 			= 'put_do_weekly_action';

			$this->require_optin        = isset( $args['opt_in'] ) ? $args['opt_in'] : true;
			$this->include_goodbye_form = isset( $args['goodbye_form'] ) ? $args['goodbye_form'] : true;
			$this->marketing            = isset( $args['email_marketing'] ) ? $args['email_marketing'] : true;
			$this->options              = isset( $args['options'] ) ? $args['options'] : [];
			$this->item_id              = isset( $args['item_id'] ) ? $args['item_id'] : false;
			/**
			 * Activation Hook
			 */
			register_activation_hook( $this->plugin_file, array( $this, 'activate_this_plugin' ) );
			/**
			 * Deactivation Hook
			 */
			register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate_this_plugin' ) );
		}
		/**
		 * When user agreed to opt-in tracking schedule is enabled.
		 * @since 3.0.0
		 */
		public function schedule_tracking() {
			if( $this->disabled_wp_cron ) {
				return;
			}
			if ( ! wp_next_scheduled( $this->event_hook ) ) {
				wp_schedule_event( time(), $this->recurrence, $this->event_hook );
			}
		}
		/**
		 * Add the schedule event if the plugin is tracked.
		 *
		 * @return void
		 */
		public function activate_this_plugin(){
			$allow_tracking = $this->is_tracking_allowed();
			if( ! $allow_tracking ) {
				return;
			}
			$this->schedule_tracking();
		}
		/**
		 * Remove the schedule event when plugin is deactivated and send the deactivated reason to inishghts if user submitted.
		 * @since 3.0.0
		 */
		public function deactivate_this_plugin() {
			/**
			 * Check tracking is allowed or not.
			 */
			$allow_tracking = $this->is_tracking_allowed();
			if( ! $allow_tracking ) {
				return;
			}
			$body = $this->get_data();
			$body['status'] = 'Deactivated';
			$body['deactivated_date'] = time();

			// Check deactivation reason and add for insights data.
			if( false !== get_option( 'wpins_deactivation_reason_' . $this->plugin_name ) ) {
				$body['deactivation_reason'] = get_option( 'wpins_deactivation_reason_' . $this->plugin_name );
			}
			if( false !== get_option( 'wpins_deactivation_details_' . $this->plugin_name ) ) {
				$body['deactivation_details'] = get_option( 'wpins_deactivation_details_' . $this->plugin_name );
			}

			$this->send_data( $body );
			delete_option( 'wpins_deactivation_reason_' . $this->plugin_name );
			delete_option( 'wpins_deactivation_details_' . $this->plugin_name );
			/**
			 * Clear the event schedule.
			 */
			if( ! $this->disabled_wp_cron ) {
				wp_clear_scheduled_hook( $this->event_hook );
			}
		}
		/**
		 * Initial Method to Hook Everything.
		 * @return void
		 */
		public function init(){
			$this->clicked();
			add_action( $this->event_hook, array( $this, 'do_tracking' ) );
			// For Test
			// add_action( 'admin_init', array( $this, 'force_tracking' ) );
			add_action( 'disable_comments_notice', array( $this, 'notice' ) );
			/**
			 * Deactivation Reason Form and Submit Data to Insights.
			 */
			add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'deactivate_action_links' ) );
			add_action( 'admin_footer-plugins.php', array( $this, 'deactivate_reasons_form' ) );
			add_action( 'wp_ajax_deactivation_form_' . esc_attr( $this->plugin_name ), array( $this, 'deactivate_reasons_form_submit' ) );
		}
		/**
		 * For Redirecting Current Page without Arguments!
		 *
		 * @return void
		 */
		private function redirect_to(){
			if (empty($_SERVER['REQUEST_URI'])) {
				return;
			}

			$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			$request_uri  = wp_parse_url( $request_uri, PHP_URL_PATH );
			$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );
			parse_str( $query_string, $current_url );

			$unset_array = array( 'dismiss', 'plugin', '_wpnonce', 'later', 'plugin_action', 'marketing_optin' );

			foreach( $unset_array as $value ) {
				if( isset( $current_url[ $value ] ) ) {
					unset( $current_url[ $value ] );
				}
			}

			$current_url = http_build_query($current_url);
			$redirect_url = $request_uri . '?' . $current_url;
			return $redirect_url;
		}
		/**
		 * This method forcing the do_tracking method to execute instant.
		 * @return void
		 */
		public function force_tracking(){
			$this->do_tracking( true );
		}
		/**
		 * This method is responsible for all the magic from the front of the plugin.
		 * @since 3.0.0
		 * @param $force	Force tracking if it's not the correct time to track/
		 */
		public function do_tracking( $force = false ) {
			/**
			 * Check URL is set or not.
			 */
			if ( empty( self::API_URL ) ) {
				return;
			}
			/**
			 * Check is tracking allowed or not.
			 */
			if( ! $this->is_tracking_allowed() ) {
				return;
			}
			/**
			 * Check is this the correct time to track or not.
			 * or Force to track.
			 */
			if( ! $this->is_time_to_track() && ! $force ) {
				return;
			}
			/**
			 * Get All Data.
			 */
			$body = $this->get_data();
			/**
			 * Send all data.
			 */
			return $this->send_data( $body );
		}
		/**
		 * Is tracking allowed?
		 * @since 1.0.0
		 */
		private function is_tracking_allowed() {
			// First, check if the user has changed their mind and opted out of tracking
			if( $this->has_user_opted_out() ) {
				$this->set_is_tracking_allowed( false, $this->plugin_name );
				return false;
			}
			// The wpins_allow_tracking option is an array of plugins that are being tracked
			$allow_tracking = get_option( 'wpins_allow_tracking' );
			// If this plugin is in the array, then tracking is allowed
			if( isset( $allow_tracking[$this->plugin_name] ) ) {
				return true;
			}
			return false;
		}
		/**
		 * Set a flag in DB If tracking is allowed.
		 *
		 * @since 3.0.0
		 * @param $is_allowed	Boolean	 true if is allowed.
		 */
		protected function set_is_tracking_allowed( $is_allowed, $plugin = null ) {
			if( empty( $plugin ) ) {
				$plugin = $this->plugin_name;
			}
			/**
			 * Get All Tracked Plugin List using this Tracker.
			 */
			$allow_tracking = get_option( 'wpins_allow_tracking' );
			/**
			 * Check user is opted out for tracking or not.
			 */
			if( $this->has_user_opted_out() ) {
				if( isset( $allow_tracking[$plugin] ) ) {
					unset( $allow_tracking[$plugin] );
				}
			} else if( $is_allowed || ! $this->require_optin ) {
				/**
				 * If user has agreed to allow tracking
				 */
				if( empty( $allow_tracking ) || ! is_array( $allow_tracking ) ) {
					$allow_tracking = array( $plugin => $plugin );
				} else {
					$allow_tracking[$plugin] = $plugin;
				}
			} else {
				if( isset( $allow_tracking[$plugin] ) ) {
					unset( $allow_tracking[$plugin] );
				}
			}
			update_option( 'wpins_allow_tracking', $allow_tracking );
		}

		/**
		 * Check the user has opted out or not.
		 *
		 * @since 3.0.0
		 * @return Boolean
		 */
		protected function has_user_opted_out() {
			if( ! empty( $this->options ) ) {
				foreach( $this->options as $option_name ) {
					$options = get_option( $option_name );
					if( ! empty( $options['wpins_opt_out'] ) ) {
						return true;
					}
				}
			}
			return false;
		}
		/**
		 * Check if it's time to track
		 *
		 * @since 3.0.0
		 */
		public function is_time_to_track() {
			$track_times = get_option( 'wpins_last_track_time', array() );
			return ! isset( $track_times[$this->plugin_name] ) ? true :
					( ( isset( $track_times[$this->plugin_name] ) && $track_times[$this->plugin_name] ) < strtotime( '-1 day' ) ? true : false );
		}
		/**
		 * Set tracking time.
		 *
		 * @since 3.0.0
		 */
		public function set_track_time() {
			$track_times = get_option( 'wpins_last_track_time', array() );
			$track_times[ $this->plugin_name ] = time();
			update_option( 'wpins_last_track_time', $track_times );
		}
		/**
		 * This method is responsible for collecting all data.
		 *
		 * @since 3.0.0
		 */
		public function get_data() {
			$body = array(
				'plugin_slug'		=> sanitize_text_field( $this->plugin_name ),
				'url'				=> get_bloginfo( 'url' ),
				'site_name' 		=> get_bloginfo( 'name' ),
				'site_version'		=> get_bloginfo( 'version' ),
				'site_language'		=> get_bloginfo( 'language' ),
				'charset'			=> get_bloginfo( 'charset' ),
				'wpins_version'		=> self::WPINS_VERSION,
				'php_version'		=> phpversion(),
				'multisite'			=> is_multisite(),
				'file_location'		=> __FILE__
			);

			// Collect the email if the correct option has been set
			if( $this->marketing ) {
				if( ! function_exists( 'wp_get_current_user' ) ) {
					include ABSPATH . 'wp-includes/pluggable.php';
				}
				$current_user = wp_get_current_user();
				$email = $current_user->user_email;
				if( is_email( $email ) ) {
					$body['email'] = $email;
				}
			}
			$body['marketing_method'] = $this->marketing;
			$body['server'] = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';

			/**
			 * Collect all active and inactive plugins
			 */
			if( ! function_exists( 'get_plugins' ) ) {
				include ABSPATH . '/wp-admin/includes/plugin.php';
			}
			$plugins = array_keys( get_plugins() );
			$active_plugins = is_network_admin() ? array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) : get_option( 'active_plugins', array() );
			foreach ( $plugins as $key => $plugin ) {
				if ( in_array( $plugin, $active_plugins ) ) {
					unset( $plugins[$key] );
				}
			}
			$body['active_plugins'] = $active_plugins;
			$body['inactive_plugins'] = $plugins;

			/**
			 * Text Direction.
			 */
			$body['text_direction']	= ( function_exists( 'is_rtl' ) ? ( is_rtl() ? 'RTL' : 'LTR' ) : 'NOT SET' );
			/**
			 * Get Our Plugin Data.
			 * @since 3.0.0
			 */
			$plugin = $this->plugin_data();
			if( empty( $plugin ) ) {
				$body['message'] .= __( 'We can\'t detect any plugin information. This is most probably because you have not included the code in the plugin main file.', 'disable-comments' );
				$body['status'] = 'NOT FOUND';
			} else {
				if( isset( $plugin['Name'] ) ) {
					$body['plugin'] = sanitize_text_field( $plugin['Name'] );
				}
				if( isset( $plugin['Version'] ) ) {
					$body['version'] = sanitize_text_field( $plugin['Version'] );
				}
				$body['status'] = 'Active';
			}

			/**
			 * Get our plugin options
			 * @since 1.0.0
			 */
			// $options = $this->options;
			// $plugin_options = array();
			// if( ! empty( $options ) && is_array( $options ) ) {
			// 	foreach( $options as $option ) {
			// 		$fields = get_option( $option );
			// 		// Check for permission to send this option
			// 		if( isset( $fields['wpins_registered_setting'] ) ) {
			// 			foreach( $fields as $key=>$value ) {
			// 				$plugin_options[$key] = $value;
			// 			}
			// 		}
			// 	}
			// }
			// $body['plugin_options'] = $this->options; // Returns array
			// $body['plugin_options_fields'] = $plugin_options; // Returns object

			/**
			 * Get active theme name and version
			 * @since 3.0.0
			 */
			$theme = wp_get_theme();
			if( $theme->Name ) {
				$body['theme'] = sanitize_text_field( $theme->Name );
			}
			if( $theme->Version ) {
				$body['theme_version'] = sanitize_text_field( $theme->Version );
			}
			return $body;
		}

		/**
		 * Collect plugin data,
		 * Retrieve current plugin information
		 *
		 * @since 3.0.0
		 */
		public function plugin_data() {
			if( ! function_exists( 'get_plugin_data' ) ) {
				include ABSPATH . '/wp-admin/includes/plugin.php';
			}
			$plugin = get_plugin_data( $this->plugin_file );
			return $plugin;
		}
		/**
		 * Send the data to insights.
		 * @since 3.0.0
		 */
		public function send_data( $body ) {
			/**
			 * Get SITE ID
			 */
			$site_id_key       = "wpins_{$this->plugin_name}_site_id";
			$site_id           = get_option( $site_id_key, false );
			$failed_data       = [];
			$site_url          = get_bloginfo( 'url' );
			$original_site_url = get_option( "wpins_{$this->plugin_name}_original_url", false );

			if( ( $original_site_url === false || $original_site_url != $site_url ) && version_compare( $body['wpins_version'], '3.0.1', '>=' ) ) {
				$site_id = false;
			}
			/**
			 * Send Initial Data to API
			 */
			if( $site_id == false && $this->item_id !== false ) {
				if( isset( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] && $_SERVER['REMOTE_ADDR'] != '127.0.0.1' ) ) {
					$country_request = wp_remote_get( 'http://ip-api.com/json/'. sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) .'?fields=country');
					if( ! is_wp_error( $country_request ) && $country_request['response']['code'] == 200 ) {
						$ip_data = json_decode( $country_request["body"] );
						$body['country'] = isset( $ip_data->country ) ? $ip_data->country : 'NOT SET';
					}
				}

				$body['plugin_slug'] = $this->plugin_name;
				$body['url']         = $site_url;
				$body['item_id']     = $this->item_id;

				$request = $this->remote_post( $body );
				if( ! is_wp_error( $request ) && $request['response']['code'] == 200 ) {
					$retrieved_body = json_decode( wp_remote_retrieve_body( $request ), true );
					if( is_array( $retrieved_body ) && isset( $retrieved_body['siteId'] ) ) {
						update_option( $site_id_key, $retrieved_body['siteId'] );
						update_option( "wpins_{$this->plugin_name}_original_url", $site_url );
						update_option( "wpins_{$this->plugin_name}_{$retrieved_body['siteId']}", $body );
					}
				} else {
					$failed_data = $body;
				}
			}

			$site_id_data_key        = "wpins_{$this->plugin_name}_{$site_id}";
			$site_id_data_failed_key = "wpins_{$this->plugin_name}_{$site_id}_send_failed";

			if( $site_id != false ) {
				$old_sent_data = get_option( $site_id_data_key, [] );
				$diff_data = $this->diff( $body, $old_sent_data );
				$failed_data = get_option( $site_id_data_failed_key, [] );
				if( ! empty( $failed_data ) && $diff_data != $failed_data ) {
					$failed_data = array_merge( $failed_data, $diff_data );
				}
			}

			if( ! empty( $failed_data ) && $site_id != false ) {
				$failed_data['plugin_slug']  = $this->plugin_name;
				$failed_data['url']          = $site_url;
				$failed_data['site_id']      = $site_id;
				if( $original_site_url != false ) {
					$failed_data['original_url'] = $original_site_url;
				}

				$request = $this->remote_post( $failed_data );
				if( ! is_wp_error( $request ) ) {
					delete_option( $site_id_data_failed_key );
					$replaced_data = array_merge( $old_sent_data, $failed_data );
					update_option( $site_id_data_key, $replaced_data );
				}
			}

			if( ! empty( $diff_data ) && $site_id != false && empty( $failed_data ) ) {
				$diff_data['plugin_slug']  = $this->plugin_name;
				$diff_data['url']          = $site_url;
				$diff_data['site_id']      = $site_id;
				if( $original_site_url != false ) {
					$diff_data['original_url'] = $original_site_url;
				}

				$request = $this->remote_post( $diff_data );
				if( is_wp_error( $request ) ) {
					update_option( $site_id_data_failed_key, $diff_data );
				} else {
					$replaced_data = array_merge( $old_sent_data, $diff_data );
					update_option( $site_id_data_key, $replaced_data );
				}
			}

			$this->set_track_time();

			if( isset( $request ) && is_wp_error( $request ) ) {
				return $request;
			}

			if( isset( $request ) ) {
				return true;
			}
			return false;
		}
		/**
		 * WP_REMOTE_POST method responsible for send data to the API_URL
		 *
		 * @param array $data
		 * @param array $args
		 * @return void
		 */
		protected function remote_post( $data = array(), $args = array() ){
			if( empty( $data ) ) {
				return;
			}

			$args = wp_parse_args( $args, array(
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'body'        => $data,
				'user-agent'  => 'PUT/1.0.0; ' . get_bloginfo( 'url' )
			));

			$request = wp_remote_post( esc_url( self::API_URL ), $args );
			if( is_wp_error( $request ) || ( isset( $request['response'], $request['response']['code'] ) && $request['response']['code'] != 200 ) ) {
				return new WP_Error( 500, 'Something went wrong.' );
			}
			return $request;
		}
		/**
		 * Difference between old and new data
		 *
		 * @param array $new_data
		 * @param array $old_data
		 * @return void
		 */
		protected function diff( $new_data, $old_data ){
			$data = [];
			if( ! empty( $new_data ) ) {
				foreach( $new_data as $key => $value ) {
					if( isset( $old_data[ $key ] ) ) {
						if( $old_data[ $key ] == $value ) {
							continue;
						}
					}
					$data[ $key ] = $value;
				}
			}
			return $data;
		}
		/**
		 * Display the admin notice to users to allow them to opt in
		 *
		 * @since 3.0.0
		 */
		public function notice() {
			/**
			 * Return if notice is not set.
			 */
			if( ! isset( $this->notice_options['notice'] ) ) {
				return;
			}
			/**
			 * Check is allowed or blocked for notice.
			 */
			$block_notice = get_option( 'wpins_block_notice' );
			if( isset( $block_notice[$this->plugin_name] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$nonce = wp_create_nonce( 'wpins_notice' ); // Generate a nonce

			$url_yes = add_query_arg( [
				'plugin'        => $this->plugin_name,
				'plugin_action' => 'yes',
				'_nonce'        => $nonce,               // Add nonce to the URL
			] );

			$url_no = add_query_arg( array(
				'plugin'        => $this->plugin_name,
				'plugin_action' => 'no',
				'_nonce'        => $nonce,               // Add nonce to the URL
			) );


			// Decide on notice text
			$extra_notice_text = $this->notice_options['extra_notice'];

			?>

			<div class="notice dc-text__block disable__comment__alert mb30">
				<div class="alert__content">
					<?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img src="<?php echo esc_url(DC_ASSETS_URI) ?>img/icon-logo.png" alt="">
                    <p>
						<?php echo wp_kses_post($this->notice_options['notice']) ?>
						<a href="#" class="wpinsights-<?php echo esc_attr($this->plugin_name) ?>-collect"><?php echo esc_html($this->notice_options['consent_button_text']) ?>
						</a>
					</p>
                    <div class="wpinsights-data" style="display: none;">
                        <p><?php echo wp_kses_post($extra_notice_text) ?></p>
                    </div>
                </div>

				<div class="button__group">
					<a href="<?php echo esc_url( $url_yes ) ?>" class="button button--sm button__success"><?php echo esc_html($this->notice_options['yes']) ?></a>&nbsp;
					<a href="<?php echo esc_url( $url_no ) ?>" class="button button--sm"><?php echo esc_html($this->notice_options['no']) ?></a>
				</div>
				<script type='text/javascript'>
				jQuery('.wpinsights-<?php echo esc_attr($this->plugin_name) ?>-collect').on('click', function(e) {
					e.preventDefault();
					jQuery('.wpinsights-data').slideToggle('fast');
				});
				</script>";
			</div>
			<?php
		}
		/**
		 * Set all notice options to customized notice.
		 *
		 * @since 3.0.0
		 * @param array $options
		 * @return void
		 */
		public function set_notice_options( $options = [] ){
			$default_options = [
				'consent_button_text' => __( 'What we collect.', 'disable-comments' ),
				'yes'                 => __( 'Sure, I\'d like to help', 'disable-comments' ),
				'no'                  => __( 'No Thanks.', 'disable-comments' ),
			];
			$options = wp_parse_args( $options, $default_options );
			$this->notice_options = $options;
		}
		/**
		 * Responsible for track the click from Notice.
		 * @return void
		 */
		public function clicked(){
			if ( ! isset( $_REQUEST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_REQUEST['_nonce']) ), 'wpins_notice' ) ) {
                return;
			}

			if( ! isset( $_GET['plugin'] ) || ! isset( $_GET['plugin_action'] ) ) {
				return;
			}
			$plugin = trim(sanitize_text_field(wp_unslash($_GET['plugin'])));
			if( $plugin === $this->plugin_name ) {
				if( isset( $_GET['tab'] ) && $_GET['tab'] === 'plugin-information' ) {
                    return;
                }

				$action = sanitize_text_field( wp_unslash($_GET['plugin_action']) );
				if( $action == 'yes' ) {
					$this->schedule_tracking();
					$this->set_is_tracking_allowed( true, $plugin );
					if( $this->do_tracking( true ) ) {
						$this->update_block_notice( $plugin );
					}
					/**
					 * Redirect User To the Current URL, but without set query arguments.
					 */
					wp_safe_redirect( $this->redirect_to() );
				} else {
					$this->set_is_tracking_allowed( false, $plugin );
					$this->update_block_notice( $plugin );
				}
			}
		}
		/**
		 * Set if we should block the opt-in notice for this plugin
		 *
		 * @since 3.0.0
		 */
		public function update_block_notice( $plugin = null ) {
			if( empty( $plugin ) ) {
				$plugin = $this->plugin_name;
			}
			$block_notice = get_option( 'wpins_block_notice' );
			if( empty( $block_notice ) || ! is_array( $block_notice ) ) {
				$block_notice = array( $plugin => $plugin );
			} else {
				$block_notice[$plugin] = $plugin;
			}
			update_option( 'wpins_block_notice', $block_notice );
		}
		/**
		 * AJAX callback when the deactivated form is submitted.
		 * @since 3.0.0
		 */
		public function deactivate_reasons_form_submit() {
			check_ajax_referer( 'wpins_deactivation_nonce', 'security' );
			if( isset( $_POST['values'] ) ) {
				$values = sanitize_text_field( wp_unslash($_POST['values']) );
				update_option( 'wpins_deactivation_reason_' . $this->plugin_name, $values );
			}
			if( isset( $_POST['details'] ) ) {
				$details = sanitize_text_field( wp_unslash($_POST['details']) );
				update_option( 'wpins_deactivation_details_' . $this->plugin_name, $details );
			}
			echo 'success';
			wp_die();
		}
		/**
		 * Filter the deactivation link to allow us to present a form when the user deactivates the plugin
		 * @since 3.0.0
		 */
		public function deactivate_action_links( $links ) {
			/**
			 * Check is tracking allowed or not.
			 */
			if( ! $this->is_tracking_allowed() ) {
				return $links;
			}
			if( isset( $links['deactivate'] ) && $this->include_goodbye_form ) {
				$deactivation_link = $links['deactivate'];
				/**
				 * Change the default deactivate button link.
				 */
				$deactivation_link = str_replace( '<a ', '<div class="wpinsights-goodbye-form-wrapper-'. esc_attr( $this->plugin_name ) .'"><div class="wpinsights-goodbye-form-bg"></div><span class="wpinsights-goodbye-form" id="wpinsights-goodbye-form"></span></div><a onclick="javascript:event.preventDefault();" id="wpinsights-goodbye-link-' . esc_attr( $this->plugin_name ) . '" ', $deactivation_link );
				$links['deactivate'] = $deactivation_link;
			}
			return $links;
		}
		/**
		 * ALL Deactivate Reasons.
		 * @since 3.0.0
		 */
		public function deactivation_reasons() {
			$form = array();
			$form['heading'] = __( 'Sorry to see you go', 'disable-comments' );
			$form['body'] = __( 'Before you deactivate the plugin, would you quickly give us your reason for doing so?', 'disable-comments' );

			$form['options'] = array(
				__( 'I no longer need the plugin', 'disable-comments' ),
				[
					'label' => __( 'I found a better plugin', 'disable-comments' ),
					'extra_field' => __( 'Please share which plugin', 'disable-comments' )
				],
				__( "I couldn't get the plugin to work", 'disable-comments' ),
				__( 'It\'s a temporary deactivation', 'disable-comments' ),
				[
					'label' => __( 'Other', 'disable-comments' ),
					'extra_field' => __( 'Please share the reason', 'disable-comments' ),
					'type' => 'textarea'
				]
			);
			return apply_filters( 'wpins_form_text_' . $this->plugin_name, $form );
		}
		/**
		 * Deactivate Reasons Form.
		 * This form will appears when user wants to deactivate the plugin to send you deactivated reasons.
		 *
		 * @since 3.0.0
		 */
		public function deactivate_reasons_form() {
			$allowed_html = array(
				'div' => array(
					'class' => array(),
					'id' => array(),
				),
				'strong' => array(),
				'p' => array(
					'class' => array(),
				),
				'ul' => array(),
				'li' => array(
					'class' => array(),
				),
				'input' => array(
					'type' => array(),
					'name' => array(),
					'id' => array(),
					'value' => array(),
					'style' => array(),
					'placeholder' => array(),
				),
				'label' => array(
					'for' => array(),
				),
				'span' => array(
					'class' => array(),
				),
			);
			$form = $this->deactivation_reasons();
			$class_plugin_name = esc_attr( $this->plugin_name );
			$html_escaped = '<div class="wpinsights-goodbye-form-head"><strong>' . esc_html( $form['heading'] ) . '</strong></div>';
			$html_escaped .= '<div class="wpinsights-goodbye-form-body"><p class="wpinsights-goodbye-form-caption">' . esc_html( $form['body'] ) . '</p>';
			if( is_array( $form['options'] ) ) {
				$html_escaped .= '<div id="wpinsights-goodbye-options" class="wpinsights-goodbye-options"><ul>';
				foreach( $form['options'] as $option ) {
					if( is_array( $option ) ) {
						$id = strtolower( str_replace( " ", "_", esc_attr( $option['label'] ) ) );
						$id = $id . '_' . $class_plugin_name;
						$html_escaped .= '<li class="has-goodbye-extra">';
						$html_escaped .= '<input type="radio" name="wpinsights-'. esc_attr($class_plugin_name) .'-goodbye-options" id="' . esc_attr($id) . '" value="' . esc_attr( $option['label'] ) . '">';
						$html_escaped .= '<div><label for="' . esc_attr($id) . '">' . esc_attr( $option['label'] ) . '</label>';

						if (isset($option['extra_field'])) {
							$allowed_tags = array(
								'input', 'textarea', 'select', 'button', 'datalist', 'fieldset', 'form', 'label', 'legend', 'meter', 'optgroup', 'option', 'output', 'progress'
							);
							$extra_field_id = str_replace(" ", "", $option['extra_field']);
							$extra_field_placeholder = $option['extra_field'];
							$extra_field_name = $id;

							$tag = isset($option['type']) ? $option['type'] : 'input';

							if (in_array($tag, $allowed_tags)) {
								$html_escaped .= '<' . esc_attr($tag) . ' style="display: none" type="text" name="' . esc_attr($extra_field_name) . '" id="' . esc_attr($extra_field_id) . '" placeholder="' . esc_attr($extra_field_placeholder) . '"></' . esc_attr($tag) . '>';
							}
						}
						$html_escaped .= '</div></li>';
					} else {
						$id = strtolower( str_replace( " ", "_", esc_attr( $option ) ) );
						$id = $id . '_' . $class_plugin_name;
						$html_escaped .= '<li><input type="radio" name="wpinsights-'. esc_attr($class_plugin_name) .'-goodbye-options" id="' . esc_attr($id) . '" value="' . esc_attr( $option ) . '"> <label for="' . esc_attr($id) . '">' . esc_attr( $option ) . '</label></li>';
					}
				}
				$html_escaped .= '</ul></div><!-- .wpinsights-'. esc_attr($class_plugin_name) .'-goodbye-options -->';
			}
			$html_escaped .= '</div><!-- .wpinsights-goodbye-form-body -->';
			$html_escaped .= '<p class="deactivating-spinner"><span class="spinner"></span> ' . __( 'Submitting form', 'disable-comments' ) . '</p>';

			$wrapper_class = '.wpinsights-goodbye-form-wrapper-'. esc_attr($class_plugin_name);

			$styles_escaped = '';
			$styles_escaped .= '<style type="text/css">';
				$styles_escaped .= '.wpinsights-form-active-' . esc_attr($class_plugin_name) . ' .wpinsights-goodbye-form-bg {';
					$styles_escaped .= 'background: rgba( 0, 0, 0, .8 );position: fixed;top: 0;left: 0;width: 100%;height: 100%;z-index: 9;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . '{';
					$styles_escaped .= 'position: relative; display: none;';
				$styles_escaped .= '}';
				$styles_escaped .= '.wpinsights-form-active-' . esc_attr($class_plugin_name) . ' ' . esc_attr($wrapper_class) . '{';
					$styles_escaped .= 'display: flex !important; position: fixed;top: 0;left: 0;width: 100%;height: 100%; justify-content: center; align-items: center;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form { display: none; }';
				$styles_escaped .= '.wpinsights-form-active-' . esc_attr($class_plugin_name) . ' .wpinsights-goodbye-form {';
					$styles_escaped .= 'position: relative !important; width: 550px; max-width: 80%; background: #fff; box-shadow: 2px 8px 23px 3px rgba(0,0,0,.2); border-radius: 3px; white-space: normal; overflow: hidden; display: block; z-index: 999999;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-head {';
					$styles_escaped .= 'background: #fff; color: #495157; padding: 18px; box-shadow: 0 0 8px rgba(0,0,0,.1); font-size: 15px;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form .wpinsights-goodbye-form-head strong { font-size: 15px; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body { padding: 8px 18px; color: #333; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body label { padding-left: 5px; color: #6d7882; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body .wpinsights-goodbye-form-caption {';
					$styles_escaped .= 'font-weight: 500; font-size: 15px; color: #495157; line-height: 1.4;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body #wpinsights-goodbye-options { padding-top: 5px; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body #wpinsights-goodbye-options ul > li { margin-bottom: 15px; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body #wpinsights-goodbye-options ul > li > div { display: inline; padding-left: 3px; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-body #wpinsights-goodbye-options ul > li > div > input, '. esc_attr($wrapper_class) .' .wpinsights-goodbye-form-body #wpinsights-goodbye-options ul > li > div > textarea {';
					$styles_escaped .= 'margin: 10px 18px; padding: 8px; width: 80%;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .deactivating-spinner { display: none; padding-bottom: 20px !important; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .deactivating-spinner .spinner { float: none; margin: 4px 4px 0 18px; vertical-align: bottom; visibility: visible; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-footer { padding: 8px 18px; margin-bottom: 15px; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-footer > .wpinsights-goodbye-form-buttons { display: flex; align-items: center; justify-content: space-between; }';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-footer .wpinsights-submit-btn {';
					$styles_escaped .= 'background-color: #d30c5c; -webkit-border-radius: 3px; border-radius: 3px; color: #fff; line-height: 1; padding: 15px 20px; font-size: 13px;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .wpinsights-goodbye-form-footer .wpinsights-deactivate-btn {';
					$styles_escaped .= 'font-size: 13px; color: #a4afb7; background: none; float: right; padding-right: 10px; width: auto; text-decoration: underline;';
				$styles_escaped .= '}';
				$styles_escaped .= esc_attr($wrapper_class) . ' .test {';
				$styles_escaped .= '}';
			$styles_escaped .= '</style>';
			$styles_escaped .= '';

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $styles_escaped;
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$("#wpinsights-goodbye-link-<?php echo esc_attr($class_plugin_name); ?>").on("click",function(){
						// We'll send the user to this deactivation link when they've completed or dismissed the form
						var url = document.getElementById("wpinsights-goodbye-link-<?php echo esc_attr($class_plugin_name); ?>");
						$('body').toggleClass('wpinsights-form-active-<?php echo esc_attr($class_plugin_name); ?>');
						$(".wpinsights-goodbye-form-wrapper-<?php echo esc_attr($class_plugin_name); ?> #wpinsights-goodbye-form").fadeIn();
						$(".wpinsights-goodbye-form-wrapper-<?php echo esc_attr($class_plugin_name); ?> #wpinsights-goodbye-form").html( '<?php echo wp_kses($html_escaped, $allowed_html); ?>' + '<div class="wpinsights-goodbye-form-footer"><div class="wpinsights-goodbye-form-buttons"><a id="wpinsights-submit-form-<?php echo esc_attr($class_plugin_name); ?>" class="wpinsights-submit-btn" href="#"><?php esc_html_e( 'Submit and Deactivate', 'disable-comments' ); ?></a>&nbsp;<a class="wpsp-put-deactivate-btn" href="'+url+'"><?php esc_html_e( 'Just Deactivate', 'disable-comments' ); ?></a></div></div>');
						$('#wpinsights-submit-form-<?php echo esc_attr($class_plugin_name); ?>').on('click', function(e){
							// As soon as we click, the body of the form should disappear
							$("#wpinsights-goodbye-form-<?php echo esc_attr($class_plugin_name); ?> .wpinsights-goodbye-form-body").fadeOut();
							$("#wpinsights-goodbye-form-<?php echo esc_attr($class_plugin_name); ?> .wpinsights-goodbye-form-footer").fadeOut();
							// Fade in spinner
							$("#wpinsights-goodbye-form-<?php echo esc_attr($class_plugin_name); ?> .deactivating-spinner").fadeIn();
							e.preventDefault();
							var checkedInput = $("input[name='wpinsights-<?php echo esc_attr($class_plugin_name); ?>-goodbye-options']:checked"),								checkedInputVal, details;
							if( checkedInput.length > 0 ) {
								checkedInputVal = checkedInput.val();
								details = $('input[name="'+ checkedInput[0].id +'"], textarea[name="'+ checkedInput[0].id +'"]').val();
							}

							if( typeof details === 'undefined' ) {
								details = '';
							}
							if( typeof checkedInputVal === 'undefined' ) {
								checkedInputVal = 'No Reason';
							}

							var data = {
								'action': 'deactivation_form_<?php echo esc_js($class_plugin_name); ?>',
								'values': checkedInputVal,
								'details': details,
								'security': "<?php echo esc_js(wp_create_nonce ( 'wpins_deactivation_nonce' )); ?>",
								'dataType': "json"
							}

							$.post(
								ajaxurl,
								data,
								function(response){
									// Redirect to original deactivation URL
									window.location.href = url;
								}
							);
						});
						$('#wpinsights-goodbye-options > ul ').on('click', 'li label, li > input', function( e ){
							var parent = $(this).parents('li');
							parent.siblings().find('label').next('input, textarea').css('display', 'none');
							parent.find('label').next('input, textarea').css('display', 'block');
						});
						// If we click outside the form, the form will close
						$('.wpinsights-goodbye-form-bg').on('click',function(){
							$("#wpinsights-goodbye-form").fadeOut();
							$('body').removeClass('wpinsights-form-active-<?php echo esc_attr($class_plugin_name); ?>');
						});
					});
				});
			</script>
		<?php }
	}
endif;