<?php
/**
 * Plugin Name:          Pino Pay for WooCommerce
 * Plugin URI:           https://github.com/OR81/pino-pay-integrations
 * Description:          درگاه پرداخت پینو پی برای ووکامرس. مشتری از صفحهٔ تسویه‌حساب به درگاه می‌رود و پس از پرداخت، سفارش خودکار تأیید می‌شود.
 * Version:              1.0.1
 * Author:               Pino Site
 * Author URI:           https://pinosite.ir
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          pino-pay-for-woocommerce
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 *
 * @package PinoPayForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'PINOPAY_WC_VERSION', '1.0.1' );
define( 'PINOPAY_WC_FILE', __FILE__ );
define( 'PINOPAY_WC_RECONCILE_HOOK', 'pinopay_wc_reconcile' );

/**
 * Tells WooCommerce this plugin works with order tables (HPOS) and with the block checkout.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PINOPAY_WC_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PINOPAY_WC_FILE, true );
		}
	}
);

/**
 * Loads the gateway once WooCommerce is there.
 */
function pinopay_wc_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونهٔ پینو پی به ووکامرس نیاز دارد. ابتدا ووکامرس را نصب و فعال کنید.', 'pino-pay-for-woocommerce' ) . '</p></div>';
			}
		);

		return;
	}

	require_once __DIR__ . '/includes/class-pinopay-order-data.php';
	require_once __DIR__ . '/includes/class-pinopay-api-exception.php';
	require_once __DIR__ . '/includes/class-pinopay-api-client.php';
	require_once __DIR__ . '/includes/class-wc-gateway-pinopay.php';

	add_filter(
		'woocommerce_payment_gateways',
		function ( $gateways ) {
			$gateways[] = 'WC_Gateway_PinoPay';

			return $gateways;
		}
	);

	add_action( PINOPAY_WC_RECONCILE_HOOK, 'pinopay_wc_reconcile' );
	add_filter( 'woocommerce_order_actions', 'pinopay_wc_order_actions', 10, 2 );
	add_action( 'woocommerce_order_action_pinopay_check', 'pinopay_wc_check_order' );
	add_action( 'admin_notices', 'pinopay_wc_admin_notices' );
}
add_action( 'plugins_loaded', 'pinopay_wc_init', 11 );

/**
 * The gateway WooCommerce created, which is the one with the saved settings and hooks.
 *
 * @return WC_Gateway_PinoPay|null
 */
function pinopay_wc_gateway() {
	if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
		return null;
	}

	$gateways = WC()->payment_gateways()->payment_gateways();

	return isset( $gateways['pinopay'] ) ? $gateways['pinopay'] : null;
}

/**
 * Confirms payments whose customer never came back to the store, for example after closing the tab.
 */
function pinopay_wc_reconcile() {
	$gateway = pinopay_wc_gateway();
	if ( $gateway ) {
		$gateway->reconcile();
	}
}

/**
 * Adds the admin's inquiry to the order actions of an unpaid Pino Pay order.
 *
 * @param array         $actions The order actions.
 * @param WC_Order|null $order   The order on the screen.
 * @return array
 */
function pinopay_wc_order_actions( $actions, $order = null ) {
	if ( $order instanceof WC_Order && 'pinopay' === $order->get_payment_method() && ! $order->is_paid() ) {
		$actions['pinopay_check'] = __( 'استعلام پرداخت از پینو پی', 'pino-pay-for-woocommerce' );
	}

	return $actions;
}

/**
 * Runs the admin's inquiry.
 *
 * @param WC_Order $order The order.
 */
function pinopay_wc_check_order( $order ) {
	$gateway = pinopay_wc_gateway();
	if ( $gateway ) {
		$gateway->check_order( $order );
	}
}

/**
 * Warns the admin of settings that would lose money or hide the gateway.
 */
function pinopay_wc_admin_notices() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$gateway = pinopay_wc_gateway();
	if ( ! $gateway || 'yes' !== $gateway->enabled ) {
		return;
	}

	if ( $gateway->is_sandbox() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'پینو پی در حالت آزمایشی است: هر کسی می‌تواند بدون پرداخت واقعی سفارش را تکمیل کند. پیش از فروش واقعی آن را خاموش کنید.', 'pino-pay-for-woocommerce' ) . '</p></div>';
	}
	if ( ! PinoPay_Order_Data::supports_currency( get_woocommerce_currency() ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'پینو پی فقط با واحد پول ریال یا تومان کار می‌کند و با واحد پول فعلی فروشگاه در تسویه‌حساب نمایش داده نمی‌شود.', 'pino-pay-for-woocommerce' ) . '</p></div>';
	}
	if ( '' === $gateway->get_option( 'merchant_id' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'کلید درگاه پینو پی وارد نشده است و این روش پرداخت در تسویه‌حساب نمایش داده نمی‌شود.', 'pino-pay-for-woocommerce' ) . '</p></div>';
	}
}

/**
 * Makes the gateway available to the block checkout.
 */
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		require_once __DIR__ . '/includes/class-pinopay-blocks-support.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new PinoPay_Blocks_Support() );
			}
		);
	}
);

/**
 * Link to the settings from the plugins page.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( PINOPAY_WC_FILE ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pinopay' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'pino-pay-for-woocommerce' ) . '</a>' );

		return $links;
	}
);

register_activation_hook(
	PINOPAY_WC_FILE,
	function () {
		if ( ! wp_next_scheduled( PINOPAY_WC_RECONCILE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', PINOPAY_WC_RECONCILE_HOOK );
		}
	}
);

register_deactivation_hook(
	PINOPAY_WC_FILE,
	function () {
		wp_clear_scheduled_hook( PINOPAY_WC_RECONCILE_HOOK );
	}
);
