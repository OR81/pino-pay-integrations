<?php

namespace PinoSite\PinoPay\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An API error (getCode() holds its code), or a call that never got an answer (isConnectionError()).
 * After a connection error the payment may still have gone through, so ask again before failing an order.
 */
class PinoPayException extends RuntimeException
{
    public const INVALID_INPUT = -9;

    public const INVALID_KEY = -10;

    public const SITE_INACTIVE = -11;

    public const MERCHANT_INACTIVE = -12;

    public const CALLBACK_DOMAIN = -13;

    public const AMOUNT_RANGE = -14;

    public const TOO_MANY_REQUESTS = -15;

    public const AMOUNT_MISMATCH = -50;

    public const NOT_PAID = -51;

    public const EXPIRED = -52;

    public const UNKNOWN_AUTHORITY = -54;

    /**
     * @param  array<string, array<int, string>>  $validations  What was wrong with each field, on code -9.
     */
    public function __construct(
        string $message,
        int $code = 0,
        public readonly array $validations = [],
        private readonly bool $connectionError = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @param array{code?: int, message?: string, validations?: array<string, array<int, string>>} $error */
    public static function fromApi(array $error): self
    {
        return new self((string) ($error['message'] ?? 'Pino Pay refused the call.'), (int) ($error['code'] ?? 0), (array) ($error['validations'] ?? []));
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self('Could not reach Pino Pay: '.$previous->getMessage(), 0, [], true, $previous);
    }

    public static function unreadableAnswer(int $status): self
    {
        return new self("Pino Pay sent an unreadable answer (HTTP {$status}).", 0, [], true);
    }

    public static function missingMerchantId(): self
    {
        return new self('PINO_PAY_MERCHANT_ID is not set.');
    }

    /** Whether the call got no usable answer, so nothing is known about the payment. */
    public function isConnectionError(): bool
    {
        return $this->connectionError;
    }
}
