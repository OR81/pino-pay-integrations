<?php

namespace PinoSite\PinoPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use PinoSite\PinoPay\Exceptions\PinoPayException;

/** The Pino Pay API. All amounts are in Rial. */
class PinoPay
{
    public function __construct(
        private Factory $http,
        private string $merchantId,
        private bool $sandbox = false,
        private string $baseUrl = 'https://pay.pinosite.ir',
        private int $timeout = 20,
    ) {}

    /**
     * Asks for a payment. Keep the authority with your order, then send the customer to it with redirect().
     *
     * @param  array<string, mixed>|null  $metadata  Anything you want kept with the transaction, up to 2000 bytes.
     *
     * @throws PinoPayException
     */
    public function request(
        int $amount,
        string $callbackUrl,
        string $description,
        ?string $mobile = null,
        ?string $email = null,
        ?string $orderId = null,
        ?array $metadata = null,
    ): PaymentRequest {
        $data = $this->post('request', array_filter([
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'mobile' => $mobile,
            'email' => $email,
            'order_id' => $orderId,
            'metadata' => $metadata,
        ], fn (mixed $value) => $value !== null));

        return PaymentRequest::fromApi($data);
    }

    /** Reads the customer's return to your callback URL. It proves nothing by itself: call verify() before delivering. */
    public function callback(Request $request): Callback
    {
        return Callback::fromRequest($request);
    }

    /**
     * Confirms a payment on your server, with the amount from your own order. It throws when the payment
     * was not made, so an order must only be delivered after this returns.
     *
     * @throws PinoPayException
     */
    public function verify(string $authority, int $amount): Verification
    {
        return Verification::fromApi($this->post('verify', ['authority' => $authority, 'amount' => $amount]));
    }

    /**
     * Where a payment stands.
     *
     * @throws PinoPayException
     */
    public function inquiry(string $authority): Inquiry
    {
        return Inquiry::fromApi($this->post('inquiry', ['authority' => $authority]));
    }

    /**
     * Payments the customer finished but your server has not confirmed yet, newest first (at most 100).
     * Run it on a schedule to catch customers whose browser never came back to your site.
     *
     * @return array<int, UnverifiedPayment>
     *
     * @throws PinoPayException
     */
    public function unverified(): array
    {
        return array_map(
            fn (array $payment) => UnverifiedPayment::fromApi($payment),
            $this->post('unverified', [])['authorities'] ?? [],
        );
    }

    /** The same client with another site's key, for an application that serves several sites. */
    public function withMerchant(string $merchantId): static
    {
        $client = clone $this;
        $client->merchantId = $merchantId;

        return $client;
    }

    /** The same client for the sandbox (or, with false, for real payments), whatever the config says. */
    public function sandbox(bool $sandbox = true): static
    {
        $client = clone $this;
        $client->sandbox = $sandbox;

        return $client;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> The "data" part of the answer.
     *
     * @throws PinoPayException
     */
    private function post(string $call, array $payload): array
    {
        if ($this->merchantId === '') {
            throw PinoPayException::missingMerchantId();
        }

        try {
            $response = $this->http->acceptJson()->asJson()->connectTimeout(8)->timeout($this->timeout)
                ->post($this->url($call), ['merchant_id' => $this->merchantId] + $payload);
        } catch (ConnectionException $exception) {
            throw PinoPayException::connectionFailed($exception);
        }

        $answer = $response->json();
        if (! is_array($answer) || (! isset($answer['data']) && ! isset($answer['errors']))) {
            throw PinoPayException::unreadableAnswer($response->status());
        }
        if (! empty($answer['errors'])) {
            throw PinoPayException::fromApi($answer['errors']);
        }

        return $answer['data'];
    }

    private function url(string $call): string
    {
        return rtrim($this->baseUrl, '/').($this->sandbox ? '/api/sandbox/v1/payment/' : '/api/v1/payment/').$call;
    }
}
