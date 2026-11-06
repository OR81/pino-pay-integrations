<?php

namespace PinoSite\PinoPay;

/** A payment Pino Pay has confirmed as paid. Amounts are in Rial. */
class Verification
{
    public const FIRST = 100;

    public const REPEATED = 101;

    public function __construct(
        public readonly int $code,
        public readonly string $refId,
        public readonly string $cardPan,
        public readonly int $fee,
        public readonly string $feeType,
        public readonly int $net,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            (int) $data['code'],
            (string) $data['ref_id'],
            (string) $data['card_pan'],
            (int) $data['fee'],
            (string) $data['fee_type'],
            (int) $data['net'],
        );
    }

    /** This is the first confirmation: deliver the order now. */
    public function isFirst(): bool
    {
        return $this->code === self::FIRST;
    }

    /** It was confirmed before: it is paid, but do not deliver it a second time. */
    public function isRepeated(): bool
    {
        return $this->code === self::REPEATED;
    }
}
