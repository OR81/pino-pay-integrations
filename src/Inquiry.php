<?php

namespace PinoSite\PinoPay;

/** Where a payment stands. Amounts are in Rial. */
class Inquiry
{
    public const CREATED = 'CREATED';

    public const PAID = 'PAID';

    public const VERIFIED = 'VERIFIED';

    public const FAILED = 'FAILED';

    public const EXPIRED = 'EXPIRED';

    public function __construct(
        public readonly string $status,
        public readonly int $amount,
        public readonly int $fee,
        public readonly int $net,
        public readonly string $description,
        public readonly ?string $orderId,
        public readonly ?string $refId,
        public readonly ?string $cardPan,
        public readonly string $createdAt,
        public readonly ?string $paidAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            (string) $data['status'],
            (int) $data['amount'],
            (int) $data['fee'],
            (int) $data['net'],
            (string) $data['description'],
            $data['order_id'] ?? null,
            $data['ref_id'] ?? null,
            $data['card_pan'] ?? null,
            (string) $data['created_at'],
            $data['paid_at'] ?? null,
        );
    }

    /** The customer has paid, whether or not your server has confirmed it yet. */
    public function isPaid(): bool
    {
        return in_array($this->status, [self::PAID, self::VERIFIED], true);
    }
}
