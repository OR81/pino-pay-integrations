<?php

namespace PinoSite\PinoPay\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PinoSite\PinoPay\Callback;
use PinoSite\PinoPay\Inquiry;
use PinoSite\PinoPay\PaymentRequest;
use PinoSite\PinoPay\UnverifiedPayment;
use PinoSite\PinoPay\Verification;

/**
 * @method static PaymentRequest request(int $amount, string $callbackUrl, string $description, ?string $mobile = null, ?string $email = null, ?string $orderId = null, ?array $metadata = null)
 * @method static Callback callback(Request $request)
 * @method static Verification verify(string $authority, int $amount)
 * @method static Inquiry inquiry(string $authority)
 * @method static array<int, UnverifiedPayment> unverified()
 * @method static \PinoSite\PinoPay\PinoPay withMerchant(string $merchantId)
 * @method static \PinoSite\PinoPay\PinoPay sandbox(bool $sandbox = true)
 *
 * @see \PinoSite\PinoPay\PinoPay
 */
class PinoPay extends Facade
{
    /** Read the config on every call, so a change at runtime (or in a test) is not missed. */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return \PinoSite\PinoPay\PinoPay::class;
    }
}
