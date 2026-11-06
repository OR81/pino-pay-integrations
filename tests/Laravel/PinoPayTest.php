<?php

namespace PinoSite\PinoPay\Tests\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use PinoSite\PinoPay\Exceptions\PinoPayException;
use PinoSite\PinoPay\Facades\PinoPay;
use PinoSite\PinoPay\Inquiry;
use PinoSite\PinoPay\PinoPay as PinoPayClient;

class PinoPayTest extends TestCase
{
    private const AUTHORITY = 'P8ZK2M4Q7X1B9C3D5F6G8H0J2L4N6R8T1V00';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function fakeSuccess(array $data): void
    {
        Http::fake(['pay.example.test/*' => Http::response(['data' => ['code' => 100, 'message' => 'Success'] + $data, 'errors' => []])]);
    }

    private function fakeError(int $code, string $message, int $status, array $validations = []): void
    {
        Http::fake(['pay.example.test/*' => Http::response([
            'data' => [],
            'errors' => ['code' => $code, 'message' => $message, 'validations' => $validations],
        ], $status)]);
    }

    private function requestAnswer(): array
    {
        return [
            'authority' => self::AUTHORITY,
            'payment_url' => 'https://pay.example.test/pay/'.self::AUTHORITY,
            'fee' => 10000,
            'fee_type' => 'Merchant',
        ];
    }

    public function test_a_request_sends_the_key_and_the_fields_and_returns_the_payment(): void
    {
        $this->fakeSuccess($this->requestAnswer());

        $payment = PinoPay::request(1000000, 'https://shop.test/back', 'سفارش ۱۲', mobile: '09121234567', email: 'a@b.test', orderId: '12', metadata: ['cart' => 7]);

        $this->assertSame(self::AUTHORITY, $payment->authority);
        $this->assertSame('https://pay.example.test/pay/'.self::AUTHORITY, $payment->paymentUrl);
        $this->assertSame(10000, $payment->fee);
        $this->assertSame('Merchant', $payment->feeType);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://pay.example.test/api/v1/payment/request'
            && $request->isJson()
            && $request->data() === [
                'merchant_id' => self::KEY,
                'amount' => 1000000,
                'callback_url' => 'https://shop.test/back',
                'description' => 'سفارش ۱۲',
                'mobile' => '09121234567',
                'email' => 'a@b.test',
                'order_id' => '12',
                'metadata' => ['cart' => 7],
            ]);
    }

    public function test_fields_left_out_are_not_sent(): void
    {
        $this->fakeSuccess($this->requestAnswer());

        PinoPay::request(1000000, 'https://shop.test/back', 'سفارش');

        Http::assertSent(fn (ClientRequest $request) => array_keys($request->data()) === ['merchant_id', 'amount', 'callback_url', 'description']);
    }

    public function test_redirect_sends_the_customer_to_the_payment_page(): void
    {
        $this->fakeSuccess($this->requestAnswer());

        $response = PinoPay::request(1000000, 'https://shop.test/back', 'سفارش')->redirect();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://pay.example.test/pay/'.self::AUTHORITY, $response->getTargetUrl());
    }

    public function test_the_sandbox_setting_moves_every_call_to_the_sandbox(): void
    {
        config(['pino-pay.sandbox' => true]);
        $this->fakeSuccess($this->requestAnswer());

        PinoPay::request(1000000, 'https://shop.test/back', 'سفارش');

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://pay.example.test/api/sandbox/v1/payment/request');
    }

    public function test_sandbox_and_with_merchant_return_a_new_client_and_leave_the_original_alone(): void
    {
        $this->fakeSuccess(['authorities' => []]);
        $client = app(PinoPayClient::class);

        $client->sandbox()->withMerchant('other-key')->unverified();
        $client->unverified();

        $requests = Http::recorded()->map(fn (array $pair) => $pair[0]);
        $this->assertSame('https://pay.example.test/api/sandbox/v1/payment/unverified', $requests[0]->url());
        $this->assertSame('other-key', $requests[0]['merchant_id']);
        $this->assertSame('https://pay.example.test/api/v1/payment/unverified', $requests[1]->url());
        $this->assertSame(self::KEY, $requests[1]['merchant_id']);
    }

    public function test_a_trailing_slash_on_the_base_url_is_harmless(): void
    {
        config(['pino-pay.base_url' => 'https://pay.example.test/']);
        $this->fakeSuccess(['authorities' => []]);

        PinoPay::unverified();

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://pay.example.test/api/v1/payment/unverified');
    }

    public function test_a_missing_key_fails_before_any_call(): void
    {
        config(['pino-pay.merchant_id' => null]);
        Http::fake();

        try {
            PinoPay::unverified();
            $this->fail('No exception.');
        } catch (PinoPayException $exception) {
            $this->assertSame('PINO_PAY_MERCHANT_ID is not set.', $exception->getMessage());
            $this->assertFalse($exception->isConnectionError());
        }

        Http::assertNothingSent();
    }

    public function test_an_api_error_becomes_an_exception_with_its_code_and_message(): void
    {
        $this->fakeError(-13, 'آدرس بازگشت روی دامنهٔ این درگاه نیست.', 422);

        try {
            PinoPay::request(1000000, 'https://evil.test/back', 'سفارش');
            $this->fail('No exception.');
        } catch (PinoPayException $exception) {
            $this->assertSame(PinoPayException::CALLBACK_DOMAIN, $exception->getCode());
            $this->assertSame('آدرس بازگشت روی دامنهٔ این درگاه نیست.', $exception->getMessage());
            $this->assertFalse($exception->isConnectionError());
        }
    }

    public function test_validation_errors_are_listed_field_by_field(): void
    {
        $this->fakeError(-9, 'ورودی‌ها معتبر نیستند.', 422, ['mobile' => ['فرمت موبایل معتبر نیست.']]);

        try {
            PinoPay::request(1000000, 'https://shop.test/back', 'سفارش', mobile: '123');
            $this->fail('No exception.');
        } catch (PinoPayException $exception) {
            $this->assertSame(PinoPayException::INVALID_INPUT, $exception->getCode());
            $this->assertSame(['mobile' => ['فرمت موبایل معتبر نیست.']], $exception->validations);
        }
    }

    public function test_no_connection_is_told_apart_from_an_unpaid_answer(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        try {
            PinoPay::verify(self::AUTHORITY, 1000000);
            $this->fail('No exception.');
        } catch (PinoPayException $exception) {
            $this->assertTrue($exception->isConnectionError());
            $this->assertStringContainsString('timed out', $exception->getMessage());
        }
    }

    #[TestWith(['<html>Bad gateway</html>', 502])]
    #[TestWith(['{"message":"Server Error"}', 500])]
    public function test_an_answer_that_is_not_ours_counts_as_no_answer(string $body, int $status): void
    {
        Http::fake(['pay.example.test/*' => Http::response($body, $status)]);

        try {
            PinoPay::verify(self::AUTHORITY, 1000000);
            $this->fail('No exception.');
        } catch (PinoPayException $exception) {
            $this->assertTrue($exception->isConnectionError());
            $this->assertStringContainsString((string) $status, $exception->getMessage());
        }
    }

    public function test_the_first_verification_is_told_apart_from_a_repeat(): void
    {
        $answer = ['ref_id' => '20145', 'card_pan' => '502229******5995', 'fee' => 10000, 'fee_type' => 'Merchant', 'net' => 990000];

        $this->fakeSuccess($answer);
        $verification = PinoPay::verify(self::AUTHORITY, 1000000);

        $this->assertTrue($verification->isFirst());
        $this->assertFalse($verification->isRepeated());
        $this->assertSame('20145', $verification->refId);
        $this->assertSame('502229******5995', $verification->cardPan);
        $this->assertSame(10000, $verification->fee);
        $this->assertSame(990000, $verification->net);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://pay.example.test/api/v1/payment/verify'
            && $request['authority'] === self::AUTHORITY && $request['amount'] === 1000000);
    }

    public function test_a_repeated_verification_says_so(): void
    {
        Http::fake(['pay.example.test/*' => Http::response(['data' => [
            'code' => 101, 'message' => 'Success', 'ref_id' => '20145', 'card_pan' => '', 'fee' => 10000, 'fee_type' => 'Merchant', 'net' => 990000,
        ], 'errors' => []])]);

        $verification = PinoPay::verify(self::AUTHORITY, 1000000);

        $this->assertTrue($verification->isRepeated());
        $this->assertFalse($verification->isFirst());
    }

    public function test_an_unpaid_payment_throws_on_verify(): void
    {
        $this->fakeError(-51, 'این تراکنش پرداخت نشده است.', 422);

        $this->expectException(PinoPayException::class);
        $this->expectExceptionCode(PinoPayException::NOT_PAID);

        PinoPay::verify(self::AUTHORITY, 1000000);
    }

    #[TestWith([Inquiry::CREATED, false])]
    #[TestWith([Inquiry::EXPIRED, false])]
    #[TestWith([Inquiry::FAILED, false])]
    #[TestWith([Inquiry::PAID, true])]
    #[TestWith([Inquiry::VERIFIED, true])]
    public function test_an_inquiry_reports_where_a_payment_stands(string $status, bool $paid): void
    {
        $this->fakeSuccess([
            'status' => $status, 'amount' => 1000000, 'fee' => 10000, 'net' => 990000, 'description' => 'سفارش', 'order_id' => '12',
            'ref_id' => null, 'card_pan' => null, 'created_at' => '2026-11-06T12:00:00+03:30', 'paid_at' => null,
        ]);

        $inquiry = PinoPay::inquiry(self::AUTHORITY);

        $this->assertSame($status, $inquiry->status);
        $this->assertSame($paid, $inquiry->isPaid());
        $this->assertSame(1000000, $inquiry->amount);
        $this->assertSame('12', $inquiry->orderId);
        $this->assertNull($inquiry->refId);
    }

    public function test_unverified_lists_the_payments(): void
    {
        $this->fakeSuccess(['authorities' => [[
            'authority' => self::AUTHORITY, 'amount' => 1000000, 'order_id' => null, 'ref_id' => '20145',
            'callback_url' => 'https://shop.test/back', 'paid_at' => '2026-11-06T12:30:00+03:30',
        ]]]);

        $payments = PinoPay::unverified();

        $this->assertCount(1, $payments);
        $this->assertSame(self::AUTHORITY, $payments[0]->authority);
        $this->assertSame(1000000, $payments[0]->amount);
        $this->assertNull($payments[0]->orderId);
        $this->assertSame('20145', $payments[0]->refId);
    }

    public function test_the_callback_is_read_from_the_query_string(): void
    {
        $callback = PinoPay::callback(Request::create('/back', 'GET', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $this->assertSame(self::AUTHORITY, $callback->authority);
        $this->assertTrue($callback->isOk());
    }

    #[TestWith([['Authority' => 'A1', 'Status' => 'NOK']])]
    #[TestWith([['Authority' => 'A1']])]
    #[TestWith([['Status' => 'OK']])]
    #[TestWith([['Authority' => ['A1'], 'Status' => ['OK']]])]
    public function test_a_callback_without_a_clear_ok_is_not_ok(array $query): void
    {
        $this->assertFalse(PinoPay::callback(Request::create('/back', 'GET', $query))->isOk());
    }

    public function test_the_config_can_be_published(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'pino-pay-config', '--force' => true])->assertSuccessful();

        $this->assertFileExists(config_path('pino-pay.php'));
        unlink(config_path('pino-pay.php'));
    }
}
