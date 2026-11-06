<?php

namespace PinoSite\PinoPay;

/** A payment the customer finished that your server has not confirmed yet. The amount is in Rial. */
class UnverifiedPayment
{
    public function __construct(
        public readonly string $authority,
        public readonly int $amount,
        public readonly ?string $orderId,
        public readonly ?string $refId,
        public readonly string $callbackUrl,
        public readonly string $paidAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            (string) $data['authority'],
            (int) $data['amount'],
            $data['order_id'] ?? null,
            $data['ref_id'] ?? null,
            (string) $data['callback_url'],
            (string) $data['paid_at'],
        );
    }
}
