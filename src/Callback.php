<?php

namespace PinoSite\PinoPay;

use Illuminate\Http\Request;

/**
 * What the customer's browser brought back to your callback URL. The browser can send anything,
 * so this only decides whether verify() is worth calling; it never proves a payment.
 */
class Callback
{
    public function __construct(
        public readonly string $authority,
        public readonly string $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $authority = $request->query('Authority');
        $status = $request->query('Status');

        return new self(is_string($authority) ? $authority : '', is_string($status) ? $status : '');
    }

    /** Whether the customer's browser says the payment went through. */
    public function isOk(): bool
    {
        return $this->status === 'OK' && $this->authority !== '';
    }
}
