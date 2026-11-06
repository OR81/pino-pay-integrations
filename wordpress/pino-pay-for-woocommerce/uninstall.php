<?php
/**
 * Removes the plugin's settings when it is deleted. Orders and their payment notes stay.
 *
 * @package PinoPayForWooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_pinopay_settings' );
wp_clear_scheduled_hook( 'pinopay_wc_reconcile' );
