<?php
/**
 * Turning what a store holds into what the Pino Pay API accepts. Free of WordPress so it can be unit tested.
 *
 * @package PinoPayForWooCommerce
 */

/**
 * Amounts and mobile numbers for the API.
 */
class PinoPay_Order_Data {

	/**
	 * How many Rials one unit of each Iranian store currency is. IRHR and IRHT are the thousand-Rial and
	 * thousand-Toman units some Persian WooCommerce add-ons use.
	 *
	 * @var array<string, int>
	 */
	const RIAL_MULTIPLIERS = array(
		'IRR'  => 1,
		'IRT'  => 10,
		'IRHR' => 1000,
		'IRHT' => 10000,
	);

	/**
	 * An order total in Rial.
	 *
	 * @param float|string $total    The total in the store's currency.
	 * @param string       $currency The currency code.
	 * @return int|null Null when the API cannot take this currency.
	 */
	public static function to_rial( $total, $currency ) {
		if ( ! isset( self::RIAL_MULTIPLIERS[ $currency ] ) ) {
			return null;
		}

		return (int) round( (float) $total * self::RIAL_MULTIPLIERS[ $currency ] );
	}

	/**
	 * Whether the API can take a payment in this currency.
	 *
	 * @param string $currency The currency code.
	 * @return bool
	 */
	public static function supports_currency( $currency ) {
		return isset( self::RIAL_MULTIPLIERS[ $currency ] );
	}

	/**
	 * A mobile number as 09xxxxxxxxx. It accepts 0912…, 912…, +98912…, 0098912… in Persian, Arabic or Latin digits.
	 *
	 * @param mixed $value What the customer typed.
	 * @return string|null Null when it is not an Iranian mobile number, since the API refuses a malformed one.
	 */
	public static function mobile( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$latin  = str_replace(
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			(string) $value
		);
		$digits = (string) preg_replace( '/\D+/', '', $latin );

		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = substr( $digits, 2 );
		}
		$digits = ltrim( $digits, '0' );

		return preg_match( '/^9\d{9}$/', $digits ) ? '0' . $digits : null;
	}
}
