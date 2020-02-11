<?php
/**
 * Uninstall script
 *
 * @package Disable_Comments
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'disable_comments_options' );
