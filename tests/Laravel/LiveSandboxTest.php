<?php

namespace PinoSite\PinoPay\Tests\Laravel;

use Illuminate\Support\Facades\Http;
use PinoSite\PinoPay\Exceptions\PinoPayException;
use PinoSite\PinoPay\Facades\PinoPay;

/**
 * Runs the whole trip against a real Pino Pay sandbox, to catch the package and the API drifting apart.
 * It is skipped unless PINO_PAY_LIVE_BASE_URL and PINO_PAY_LIVE_MERCHANT_ID are set.
 */
class LiveSandboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('PINO_PAY_LIVE_BASE_URL') || ! getenv('PINO_PAY_LIVE_MERCHANT_ID')) {
            $this->markTestSkipped('Set PINO_PAY_LIVE_BASE_URL and PINO_PAY_LIVE_MERCHANT_ID to run this.');
        }

        config([
            'pino-pay.base_url' => getenv('PINO_PAY_LIVE_BASE_URL'),
            'pino-pay.merchant_id' => getenv('PINO_PAY_LIVE_MERCHANT_ID'),
            'pino-pay.sandbox' => true,
        ]);
    }

    public function test_the_whole_trip_works_against_a_real_sandbox(): void
    {
        $payment = PinoPay::request(1000000, 'http://localhost/back', 'Live test', mobile: '09120000002', orderId: 'LIVE-1');
        $this->assertStringContainsString('/pay/'.$payment->authority, $payment->paymentUrl);
        $this->assertSame('CREATED', PinoPay::inquiry($payment->authority)->status);

        try {
            PinoPay::verify($payment->authority, 1000000);
            $this->fail('An unpaid payment was verified.');
        } catch (PinoPayException $exception) {
            $this->assertSame(PinoPayException::NOT_PAID, $exception->getCode());
        }

        // The customer's click on "پرداخت موفق".
        Http::withoutRedirecting()->asForm()->post($payment->paymentUrl.'/sandbox', ['result' => 'ok']);

        $this->assertSame('PAID', PinoPay::inquiry($payment->authority)->status);
        $this->assertContains($payment->authority, array_column(array_map(fn ($item) => (array) $item, PinoPay::unverified()), 'authority'));

        $first = PinoPay::verify($payment->authority, 1000000);
        $this->assertTrue($first->isFirst());
        $this->assertNotSame('', $first->refId);

        $this->assertTrue(PinoPay::verify($payment->authority, 1000000)->isRepeated());
        $this->assertSame('VERIFIED', PinoPay::inquiry($payment->authority)->status);

        try {
            PinoPay::verify($payment->authority, 999);
            $this->fail('A wrong amount was verified.');
        } catch (PinoPayException $exception) {
            $this->assertSame(PinoPayException::AMOUNT_MISMATCH, $exception->getCode());
        }
    }
}
