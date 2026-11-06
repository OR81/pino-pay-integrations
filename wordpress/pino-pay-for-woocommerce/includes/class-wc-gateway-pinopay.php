<?php
/**
 * The WooCommerce payment gateway.
 *
 * @package PinoPayForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends the customer to Pino Pay, reads their return and confirms the payment before completing the order.
 */
class WC_Gateway_PinoPay extends WC_Payment_Gateway {

	/**
	 * Order meta holding each payment started for an order. A customer can retry, so there can be several.
	 */
	const AUTHORITY_META = '_pinopay_authority';

	const SANDBOX_META = '_pinopay_sandbox';

	/**
	 * Sets the gateway up.
	 */
	public function __construct() {
		$this->id                 = 'pinopay';
		$this->method_title       = __( 'پینو پی', 'pino-pay-for-woocommerce' );
		$this->method_description = __( 'دریافت پرداخت از مشتری با درگاه پینو پی. کلید درگاه را از بخش «درگاه‌ها» در پنل پینو پی بردارید.', 'pino-pay-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->icon               = apply_filters( 'pinopay_wc_icon', plugins_url( 'assets/images/logo.png', PINOPAY_WC_FILE ) );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_pinopay', array( $this, 'handle_return' ) );
	}

	/**
	 * The settings page.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'فعال‌سازی', 'pino-pay-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'پرداخت با پینو پی فعال باشد', 'pino-pay-for-woocommerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'عنوان', 'pino-pay-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'پرداخت آنلاین با پینو پی', 'pino-pay-for-woocommerce' ),
			),
			'description' => array(
				'title'   => __( 'توضیح', 'pino-pay-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'پرداخت امن با همهٔ کارت‌های عضو شتاب.', 'pino-pay-for-woocommerce' ),
			),
			'merchant_id' => array(
				'title'       => __( 'کلید درگاه (merchant_id)', 'pino-pay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'از صفحهٔ درگاه در پنل پینو پی. دامنهٔ ثبت‌شده برای درگاه باید همین سایت باشد.', 'pino-pay-for-woocommerce' ),
				'default'     => '',
			),
			'sandbox'     => array(
				'title'       => __( 'حالت آزمایشی', 'pino-pay-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'پرداخت‌ها به محیط آزمایشی بروند', 'pino-pay-for-woocommerce' ),
				'description' => __( 'پولی جابه‌جا نمی‌شود و هر کسی می‌تواند سفارش را بدون پرداخت تکمیل کند. فقط برای تست؛ پیش از فروش واقعی خاموشش کنید.', 'pino-pay-for-woocommerce' ),
				'default'     => 'no',
			),
			'base_url'    => array(
				'title'       => __( 'آدرس پینو پی', 'pino-pay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'معمولاً نیازی به تغییر ندارد.', 'pino-pay-for-woocommerce' ),
				'default'     => 'https://pay.pinosite.ir',
			),
			'debug'       => array(
				'title'       => __( 'گزارش خطا', 'pino-pay-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'خطاهای اتصال در گزارش‌های ووکامرس ثبت شود', 'pino-pay-for-woocommerce' ),
				'description' => __( 'در ووکامرس، وضعیت، گزارش‌ها، با منبع pino-pay.', 'pino-pay-for-woocommerce' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Whether calls go to the sandbox.
	 *
	 * @return bool
	 */
	public function is_sandbox() {
		return 'yes' === $this->get_option( 'sandbox' );
	}

	/**
	 * Shows the gateway only when it can actually take this store's payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available()
			&& '' !== $this->get_option( 'merchant_id' )
			&& PinoPay_Order_Data::supports_currency( get_woocommerce_currency() );
	}

	/**
	 * The title customers see, saying so when it is only a test.
	 *
	 * @return string
	 */
	public function get_title() {
		$title = parent::get_title();

		return $this->is_sandbox() ? $title . ' (' . __( 'آزمایشی', 'pino-pay-for-woocommerce' ) . ')' : $title;
	}

	/**
	 * The logo with its size fixed, since themes let an image without a size fill the whole width.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = sprintf(
			'<img src="%s" alt="%s" style="display:inline-block;width:auto;height:24px;vertical-align:middle;" />',
			esc_url( $this->icon ),
			esc_attr( parent::get_title() )
		);

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Asks for the payment and sends the customer to it.
	 *
	 * @param int $order_id The order.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$amount = PinoPay_Order_Data::to_rial( $order->get_total(), $order->get_currency() );
		if ( null === $amount ) {
			wc_add_notice( __( 'این سفارش با واحد پول فروشگاه از پینو پی قابل پرداخت نیست.', 'pino-pay-for-woocommerce' ), 'error' );

			return array( 'result' => 'failure' );
		}

		try {
			$payment = $this->client()->request_payment( $this->payment_fields_for( $order, $amount ) );
		} catch ( PinoPay_Api_Exception $exception ) {
			$this->log( 'Payment request failed: ' . $exception->getCode() . ' ' . $exception->getMessage() );
			$order->add_order_note(
				/* translators: 1: error code, 2: error message */
				sprintf( __( 'درخواست پرداخت از پینو پی ناموفق بود: %1$s %2$s', 'pino-pay-for-woocommerce' ), $exception->getCode(), $exception->getMessage() )
			);
			wc_add_notice( __( 'اتصال به درگاه پرداخت انجام نشد. لطفاً چند دقیقهٔ دیگر دوباره تلاش کنید.', 'pino-pay-for-woocommerce' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$order->add_meta_data( self::AUTHORITY_META, $payment['authority'] );
		$order->update_meta_data( self::SANDBOX_META, $this->is_sandbox() ? 'yes' : 'no' );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $payment['payment_url'],
		);
	}

	/**
	 * The customer's return: checks it belongs to the order, then confirms with the API. The browser's word
	 * alone never completes an order.
	 */
	public function handle_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The order key authenticates this return.
		$order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$key       = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : '';
		$status    = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';
		// phpcs:enable

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || ! $this->owns_authority( $order, $authority ) ) {
			wc_add_notice( __( 'اطلاعات بازگشت از درگاه معتبر نیست.', 'pino-pay-for-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		if ( $order->is_paid() ) {
			$this->redirect_to_receipt( $order );
		}

		if ( 'OK' !== $status ) {
			$order->update_status( 'failed', __( 'مشتری از پرداخت انصراف داد یا پرداخت ناموفق بود.', 'pino-pay-for-woocommerce' ) );
			wc_add_notice( __( 'پرداخت انجام نشد. می‌توانید دوباره تلاش کنید.', 'pino-pay-for-woocommerce' ), 'error' );
			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;
		}

		$result = $this->settle( $order, $authority );

		if ( 'paid' === $result ) {
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			$this->redirect_to_receipt( $order );
		}

		if ( 'failed' === $result ) {
			wc_add_notice( __( 'پرداخت تأیید نشد. اگر مبلغی از حساب‌تان کم شده باشد، حداکثر تا ۷۲ ساعت برمی‌گردد.', 'pino-pay-for-woocommerce' ), 'error' );
			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;
		}

		// No answer: the customer may have paid, so the order waits for the next check rather than failing.
		wc_add_notice( __( 'ارتباط با درگاه برای تأیید پرداخت برقرار نشد. پرداخت شما به‌زودی بررسی می‌شود و نتیجه روی سفارش ثبت خواهد شد.', 'pino-pay-for-woocommerce' ), 'notice' );
		$this->redirect_to_receipt( $order );
	}

	/**
	 * Confirms the payments the customer finished without coming back to the store.
	 */
	public function reconcile() {
		if ( '' === $this->get_option( 'merchant_id' ) ) {
			return;
		}

		try {
			$payments = $this->client()->unverified();
		} catch ( PinoPay_Api_Exception $exception ) {
			$this->log( 'Reconcile failed: ' . $exception->getCode() . ' ' . $exception->getMessage() );

			return;
		}

		foreach ( $payments as $payment ) {
			$order = $this->order_of_payment( $payment );

			// A total edited after the payment started stays unconfirmed, without a new note on every run.
			if ( $order && ! $order->is_paid() && PinoPay_Order_Data::to_rial( $order->get_total(), $order->get_currency() ) === (int) $payment['amount'] ) {
				$this->settle( $order, $payment['authority'] );
			}
		}
	}

	/**
	 * Finds the order behind a payment from the callback URL it was started with, which names the order and
	 * carries its key. Payments of another store using the same key match nothing here.
	 *
	 * @param array $payment A payment from the API's unverified list.
	 * @return WC_Order|null
	 */
	private function order_of_payment( array $payment ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( $payment['callback_url'], PHP_URL_QUERY ), $query );

		$order = empty( $query['order_id'] ) ? false : wc_get_order( absint( $query['order_id'] ) );
		if ( ! $order || empty( $query['key'] ) || ! hash_equals( $order->get_order_key(), (string) $query['key'] ) ) {
			return null;
		}

		return $this->owns_authority( $order, $payment['authority'] ) ? $order : null;
	}

	/**
	 * The admin's inquiry: asks about every payment started for the order and confirms one that was paid.
	 *
	 * @param WC_Order $order The order.
	 */
	public function check_order( $order ) {
		foreach ( $this->authorities_of( $order ) as $authority ) {
			try {
				$inquiry = $this->client()->inquiry( $authority );
			} catch ( PinoPay_Api_Exception $exception ) {
				$order->add_order_note(
					/* translators: 1: error code, 2: error message */
					sprintf( __( 'استعلام از پینو پی انجام نشد: %1$s %2$s', 'pino-pay-for-woocommerce' ), $exception->getCode(), $exception->getMessage() )
				);

				return;
			}

			if ( in_array( $inquiry['status'], array( 'PAID', 'VERIFIED' ), true ) ) {
				$this->settle( $order, $authority );

				return;
			}
		}

		$order->add_order_note( __( 'استعلام از پینو پی: پرداخت موفقی برای این سفارش ثبت نشده است.', 'pino-pay-for-woocommerce' ) );
	}

	/**
	 * Confirms a payment with the API and completes the order.
	 *
	 * @param WC_Order $order     The order.
	 * @param string   $authority The payment's id.
	 * @return string paid, failed, or unknown when the API gave no usable answer.
	 */
	private function settle( $order, $authority ) {
		$amount = PinoPay_Order_Data::to_rial( $order->get_total(), $order->get_currency() );

		try {
			$result = $this->client()->verify( $authority, (int) $amount );
		} catch ( PinoPay_Api_Exception $exception ) {
			$this->log( 'Verify failed: ' . $exception->getCode() . ' ' . $exception->getMessage() );

			if ( $exception->means_not_paid() ) {
				$order->update_status(
					'failed',
					/* translators: 1: error code, 2: error message */
					sprintf( __( 'پینو پی پرداخت را تأیید نکرد: %1$s %2$s', 'pino-pay-for-woocommerce' ), $exception->getCode(), $exception->getMessage() )
				);

				return 'failed';
			}

			$order->add_order_note(
				/* translators: 1: error code, 2: error message */
				sprintf( __( 'تأیید پرداخت از پینو پی انجام نشد و دوباره بررسی می‌شود: %1$s %2$s', 'pino-pay-for-woocommerce' ), $exception->getCode(), $exception->getMessage() )
			);

			return 'unknown';
		}

		if ( ! $order->is_paid() ) {
			$order->payment_complete( $result['ref_id'] );
			$order->add_order_note(
				sprintf(
					/* translators: 1: tracking code, 2: masked card number, 3: fee in Rial, 4: "test" when it is a sandbox payment */
					__( 'پرداخت با پینو پی تأیید شد. کد پیگیری: %1$s، کارت: %2$s، کارمزد: %3$s ریال %4$s', 'pino-pay-for-woocommerce' ),
					$result['ref_id'],
					$result['card_pan'],
					number_format( (int) $result['fee'] ),
					'yes' === $order->get_meta( self::SANDBOX_META ) ? __( '(پرداخت آزمایشی، بدون جابه‌جایی پول)', 'pino-pay-for-woocommerce' ) : ''
				)
			);
		}

		return 'paid';
	}

	/**
	 * What the API needs for this order. Malformed optional fields are left out, as the API would refuse them.
	 *
	 * @param WC_Order $order  The order.
	 * @param int      $amount The amount in Rial.
	 * @return array
	 */
	private function payment_fields_for( $order, $amount ) {
		$callback = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			WC()->api_request_url( 'wc_gateway_pinopay' )
		);

		$fields = array(
			'amount'       => $amount,
			'callback_url' => $callback,
			/* translators: %s: order number */
			'description'  => sprintf( __( 'سفارش %s', 'pino-pay-for-woocommerce' ), $order->get_order_number() ),
			'order_id'     => substr( (string) $order->get_order_number(), 0, 64 ),
		);

		$mobile = PinoPay_Order_Data::mobile( $order->get_billing_phone() );
		if ( $mobile ) {
			$fields['mobile'] = $mobile;
		}
		if ( is_email( $order->get_billing_email() ) && strlen( $order->get_billing_email() ) <= 150 ) {
			$fields['email'] = $order->get_billing_email();
		}

		return $fields;
	}

	/**
	 * Every payment started for the order.
	 *
	 * @param WC_Order $order The order.
	 * @return string[]
	 */
	private function authorities_of( $order ) {
		return array_map(
			function ( $meta ) {
				return (string) $meta->value;
			},
			$order->get_meta( self::AUTHORITY_META, false )
		);
	}

	/**
	 * Whether this payment was started for this order.
	 *
	 * @param WC_Order $order     The order.
	 * @param string   $authority The payment's id.
	 * @return bool
	 */
	private function owns_authority( $order, $authority ) {
		return '' !== $authority && in_array( $authority, $this->authorities_of( $order ), true );
	}

	/**
	 * Sends the customer to the order's receipt page and stops.
	 *
	 * @param WC_Order $order The order.
	 */
	private function redirect_to_receipt( $order ) {
		wp_safe_redirect( $this->get_return_url( $order ) );
		exit;
	}

	/**
	 * The API client with the saved settings.
	 *
	 * @return PinoPay_Api_Client
	 */
	private function client() {
		return new PinoPay_Api_Client( $this->get_option( 'merchant_id' ), $this->is_sandbox(), $this->get_option( 'base_url' ) );
	}

	/**
	 * Writes to the WooCommerce log when the admin asked for it. The key is never logged.
	 *
	 * @param string $message What happened.
	 */
	private function log( $message ) {
		if ( 'yes' === $this->get_option( 'debug' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'pino-pay' ) );
		}
	}
}
