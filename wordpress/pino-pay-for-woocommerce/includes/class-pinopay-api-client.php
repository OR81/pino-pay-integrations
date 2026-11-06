<?php
/**
 * The Pino Pay API through the WordPress HTTP functions. All amounts are in Rial.
 *
 * @package PinoPayForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * The calls the gateway makes.
 */
class PinoPay_Api_Client {

	/**
	 * The site's key.
	 *
	 * @var string
	 */
	private $merchant_id;

	/**
	 * Whether calls go to the sandbox.
	 *
	 * @var bool
	 */
	private $sandbox;

	/**
	 * The address of Pino Pay, without a trailing slash.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Creates the client.
	 *
	 * @param string $merchant_id The site's key.
	 * @param bool   $sandbox     Whether calls go to the sandbox.
	 * @param string $base_url    The address of Pino Pay.
	 */
	public function __construct( $merchant_id, $sandbox, $base_url ) {
		$this->merchant_id = $merchant_id;
		$this->sandbox     = $sandbox;
		$this->base_url    = untrailingslashit( $base_url );
	}

	/**
	 * Asks for a payment.
	 *
	 * @param array $payload amount, callback_url, description and the optional mobile, email, order_id.
	 * @return array authority, payment_url, fee, fee_type.
	 * @throws PinoPay_Api_Exception When it is refused or gets no answer.
	 */
	public function request_payment( array $payload ) {
		return $this->post( 'request', $payload );
	}

	/**
	 * Confirms a payment.
	 *
	 * @param string $authority The payment's id.
	 * @param int    $amount    The order's amount in Rial.
	 * @return array code (100 first time, 101 after that), ref_id, card_pan, fee, fee_type, net.
	 * @throws PinoPay_Api_Exception When it was not paid or the call gets no answer.
	 */
	public function verify( $authority, $amount ) {
		return $this->post(
			'verify',
			array(
				'authority' => $authority,
				'amount'    => $amount,
			)
		);
	}

	/**
	 * Where a payment stands.
	 *
	 * @param string $authority The payment's id.
	 * @return array status (CREATED, PAID, VERIFIED, FAILED or EXPIRED) and the payment's details.
	 * @throws PinoPay_Api_Exception When it is refused or gets no answer.
	 */
	public function inquiry( $authority ) {
		return $this->post( 'inquiry', array( 'authority' => $authority ) );
	}

	/**
	 * Payments the customer finished that this store has not confirmed yet.
	 *
	 * @return array[] Each with authority, amount, order_id, ref_id, callback_url and paid_at.
	 * @throws PinoPay_Api_Exception When it is refused or gets no answer.
	 */
	public function unverified() {
		$data = $this->post( 'unverified', array() );

		return isset( $data['authorities'] ) ? $data['authorities'] : array();
	}

	/**
	 * Makes a call.
	 *
	 * @param string $call    The name of the call.
	 * @param array  $payload What to send besides the key.
	 * @return array The "data" part of the answer.
	 * @throws PinoPay_Api_Exception When it is refused or gets no answer.
	 */
	private function post( $call, array $payload ) {
		$path = ( $this->sandbox ? '/api/sandbox/v1/payment/' : '/api/v1/payment/' ) . $call;

		$response = wp_remote_post(
			$this->base_url . $path,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( array( 'merchant_id' => $this->merchant_id ) + $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new PinoPay_Api_Exception( esc_html( $response->get_error_message() ) );
		}

		$answer = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $answer ) || ( ! isset( $answer['data'] ) && ! isset( $answer['errors'] ) ) ) {
			throw new PinoPay_Api_Exception( esc_html( 'Unreadable answer, HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
		}
		if ( ! empty( $answer['errors'] ) ) {
			throw new PinoPay_Api_Exception( esc_html( (string) $answer['errors']['message'] ), (int) $answer['errors']['code'] );
		}

		return $answer['data'];
	}
}
