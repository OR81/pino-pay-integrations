<?php

namespace PinoSite\PinoPay;

use Illuminate\Http\RedirectResponse;

/** A payment that was opened: where to send the customer, and what will be taken as the fee (in Rial). */
class PaymentRequest
{
    public function __construct(
        public readonly string $authority,
        public readonly string $paymentUrl,
        public readonly int $fee,
        public readonly string $feeType,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self((string) $data['authority'], (string) $data['payment_url'], (int) $data['fee'], (string) $data['fee_type']);
    }

    /** Sends the customer to the payment page. */
    public function redirect(): RedirectResponse
    {
        return redirect()->away($this->paymentUrl);
    }
}
