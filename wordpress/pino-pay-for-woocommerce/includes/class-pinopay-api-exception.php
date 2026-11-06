<?php
/**
 * What the Pino Pay API refused, or a call that got no answer.
 *
 * @package PinoPayForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds the API's error code. Without an answer the code is 0 and nothing is known about the payment.
 */
class PinoPay_Api_Exception extends Exception {

	/**
	 * Codes that mean this payment was definitely not made, so its order can fail.
	 *
	 * @var int[]
	 */
	const NOT_PAID_CODES = array( -50, -51, -52, -54 );

	/**
	 * Whether the API said this payment was not made, as opposed to the call failing for another reason.
	 *
	 * @return bool
	 */
	public function means_not_paid() {
		return in_array( $this->getCode(), self::NOT_PAID_CODES, true );
	}
}
