<?php
/**
 * The gateway on the block checkout, which is WooCommerce's default checkout.
 *
 * @package PinoPayForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers the script and the data the block checkout needs. The payment itself runs in process_payment().
 */
class PinoPay_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway's id.
	 *
	 * @var string
	 */
	protected $name = 'pinopay';

	/**
	 * Loads the settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_pinopay_settings', array() );
	}

	/**
	 * Whether the gateway can be offered right now.
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateway = pinopay_wc_gateway();

		return $gateway && $gateway->is_available();
	}

	/**
	 * The checkout script.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'pinopay-wc-blocks',
			plugins_url( 'assets/js/blocks.js', PINOPAY_WC_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			PINOPAY_WC_VERSION,
			true
		);

		return array( 'pinopay-wc-blocks' );
	}

	/**
	 * What the checkout script shows.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = pinopay_wc_gateway();

		return array(
			'title'       => $gateway ? $gateway->get_title() : '',
			'description' => $gateway ? $gateway->get_description() : '',
			'icon'        => $gateway ? $gateway->icon : '',
			'supports'    => $gateway ? array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ) : array(),
		);
	}
}
