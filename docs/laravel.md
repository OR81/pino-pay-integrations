# پکیج لاراول پینو پی

درخواست پرداخت، هدایت مشتری، تأیید پرداخت و استعلام با یک Facade ساده. همهٔ مبلغ‌ها به **ریال** است.

## پیش‌نیازها

- PHP نسخهٔ 8.1 یا بالاتر
- لاراول 10، 11، 12 یا 13 (لاراول 10 و 11 دیگر به‌روزرسانی امنیتی نمی‌گیرند. پکیج روی آن‌ها کار می‌کند، ولی بهتر است پروژه را ارتقا دهید)
- یک درگاه در پنل پینو پی ([مراحل ساخت آن](../README.md#پیش-از-شروع))

## نصب

```bash
composer config repositories.pino-pay vcs https://github.com/OR81/pino-pay-integrations
composer require pinosite/laravel-pino-pay
```

Service Provider و Facade خودکار ثبت می‌شوند.

## تنظیمات

در `.env`:

```dotenv
PINO_PAY_MERCHANT_ID=کلید-درگاه-شما

# اختیاری
PINO_PAY_SANDBOX=false
PINO_PAY_BASE_URL=https://pay.pinosite.ir
PINO_PAY_TIMEOUT=20
```

| کلید | پیش‌فرض | توضیح |
| --- | --- | --- |
| `PINO_PAY_MERCHANT_ID` | ندارد | کلید درگاه از پنل پینو پی. فقط سمت سرور نگه دارید |
| `PINO_PAY_SANDBOX` | `false` | روشن بودن آن همهٔ درخواست‌ها را به محیط آزمایشی می‌فرستد |
| `PINO_PAY_BASE_URL` | `https://pay.pinosite.ir` | آدرس پینو پی |
| `PINO_PAY_TIMEOUT` | `20` | حداکثر زمان انتظار برای پاسخ، به ثانیه |

برای دیدن و تغییر فایل تنظیمات:

```bash
php artisan vendor:publish --tag=pino-pay-config
```

## مثال کامل

این مثال یک مدل `Order` با ستون‌های `amount` (به ریال)، `mobile`، `authority`، `ref_id` و `paid_at` را فرض می‌کند. این ستون‌ها باید با `update()` قابل پر شدن باشند و مسیری هم با نام `orders.show` برای نمایش سفارش لازم است.

### مسیرها

```php
// routes/web.php
use App\Http\Controllers\PaymentController;

Route::post('orders/{order}/pay', [PaymentController::class, 'start'])->name('payment.start');
Route::get('payment/back', [PaymentController::class, 'back'])->name('payment.back');
```

مسیر بازگشت با GET صدا زده می‌شود و به تنظیم CSRF نیازی ندارد.

### کنترلر

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use PinoSite\PinoPay\Exceptions\PinoPayException;
use PinoSite\PinoPay\Facades\PinoPay;

class PaymentController extends Controller
{
    /** درخواست پرداخت و فرستادن مشتری به درگاه */
    public function start(Order $order)
    {
        try {
            $payment = PinoPay::request(
                amount: $order->amount,
                callbackUrl: route('payment.back'),
                description: "سفارش {$order->id}",
                mobile: $order->mobile,
                orderId: (string) $order->id,
            );
        } catch (PinoPayException $exception) {
            report($exception);

            return back()->with('error', 'اتصال به درگاه پرداخت انجام نشد. لطفاً دوباره تلاش کنید.');
        }

        $order->update(['authority' => $payment->authority]);

        return $payment->redirect();
    }

    /** برگشت مشتری از درگاه */
    public function back(Request $request)
    {
        $callback = PinoPay::callback($request);
        $order = Order::where('authority', $callback->authority)->first();

        if (! $order) {
            abort(404);
        }

        // مشتری انصراف داده یا پرداخت ناموفق بوده است.
        if (! $callback->isOk()) {
            return redirect()->route('orders.show', $order)->with('error', 'پرداخت انجام نشد.');
        }

        try {
            // مبلغ را از سفارش خودتان بدهید، نه از چیزی که از مرورگر آمده است.
            $result = PinoPay::verify($order->authority, $order->amount);
        } catch (PinoPayException $exception) {
            // بدون پاسخ، وضعیت پرداخت نامعلوم است؛ سفارش را ناموفق نکنید.
            if ($exception->isConnectionError()) {
                return redirect()->route('orders.show', $order)->with('warning', 'پرداخت شما در حال بررسی است.');
            }

            return redirect()->route('orders.show', $order)->with('error', 'پرداخت تأیید نشد.');
        }

        // کد ۱۰۰ فقط یک بار برمی‌گردد؛ اگر کاربر صفحه را تازه کند، کد ۱۰۱ می‌گیرد.
        if ($result->isFirst()) {
            $order->update(['paid_at' => now(), 'ref_id' => $result->refId]);
            // سفارش را تحویل دهید.
        }

        return redirect()->route('orders.show', $order)->with('success', "پرداخت موفق بود. کد پیگیری: {$result->refId}");
    }
}
```

## توابع

### `PinoPay::request()`

```php
PinoPay::request(
    int $amount,            // مبلغ به ریال
    string $callbackUrl,    // آدرس بازگشت، روی دامنهٔ درگاه یا زیردامنه‌های آن
    string $description,    // حداکثر ۲۵۵ نویسه
    ?string $mobile = null, // به شکل 09xxxxxxxxx
    ?string $email = null,
    ?string $orderId = null,   // حداکثر ۶۴ نویسه
    ?array $metadata = null,   // هر داده‌ای برای خودتان، حداکثر ۲۰۰۰ بایت
): PaymentRequest
```

| ویژگی `PaymentRequest` | توضیح |
| --- | --- |
| `authority` | شناسهٔ پرداخت؛ کنار سفارش ذخیره کنید |
| `paymentUrl` | آدرس صفحهٔ پرداخت |
| `fee` | کارمزد این پرداخت به ریال، که از سهم شما کم می‌شود |
| `feeType` | پرداخت‌کنندهٔ کارمزد؛ همیشه `Merchant` |
| `redirect()` | پاسخ redirect به صفحهٔ پرداخت |

مهلت پرداخت هر درخواست محدود است و پس از آن باید درخواست تازه بدهید.

### `PinoPay::callback()`

آنچه مرورگر مشتری به آدرس بازگشت آورده است را می‌خواند: `authority`، `status` و `isOk()`.

> این داده‌ها از مرورگر می‌آیند و چیزی را ثابت نمی‌کنند. `isOk()` فقط می‌گوید صدا زدن `verify()` لازم است یا نه.

### `PinoPay::verify()`

```php
PinoPay::verify(string $authority, int $amount): Verification
```

پرداخت را تأیید می‌کند. اگر پرداخت انجام نشده باشد **استثنا می‌دهد**، پس اگر این تابع برگشت، پرداخت موفق بوده است.

| ویژگی `Verification` | توضیح |
| --- | --- |
| `isFirst()` | اولین تأیید است (کد ۱۰۰)؛ سفارش را تحویل دهید |
| `isRepeated()` | قبلاً تأیید شده بود (کد ۱۰۱)؛ دوباره تحویل ندهید |
| `refId` | کد پیگیری بانکی؛ به مشتری نشان دهید |
| `cardPan` | شماره کارت پوشیده |
| `fee` ، `net` | کارمزد و مبلغ خالص شما، به ریال |

### `PinoPay::inquiry()`

وضعیت یک پرداخت را برمی‌گرداند، بدون اینکه آن را تأیید کند.

| ویژگی `Inquiry` | توضیح |
| --- | --- |
| `status` | `CREATED` ، `PAID` ، `VERIFIED` ، `FAILED` یا `EXPIRED` |
| `isPaid()` | مشتری پرداخت کرده است، چه تأیید کرده باشید و چه نه |
| `amount` ، `fee` ، `net` | مبلغ‌ها به ریال |
| `description` ، `orderId` ، `refId` ، `cardPan` | جزئیات پرداخت |
| `createdAt` ، `paidAt` | زمان‌ها به شکل ISO 8601 |

### `PinoPay::unverified()`

آرایه‌ای از `UnverifiedPayment` با ویژگی‌های `authority`، `amount`، `orderId`، `refId`، `callbackUrl` و `paidAt` (حداکثر ۱۰۰ مورد آخر).

## بررسی پرداخت‌های تأییدنشده

ممکن است مشتری پرداخت کند ولی پیش از رسیدن به سایت شما صفحه را ببندد یا سرور شما در آن لحظه خطا بدهد. این پرداخت برای شما ثبت شده است، ولی سفارش بدون تأیید می‌ماند. با یک کار زمان‌بندی‌شده این موارد را پیدا کنید:

```php
// routes/console.php
use App\Models\Order;
use Illuminate\Support\Facades\Schedule;
use PinoSite\PinoPay\Facades\PinoPay;

Schedule::call(function () {
    foreach (PinoPay::unverified() as $payment) {
        $order = Order::where('authority', $payment->authority)->whereNull('paid_at')->first();

        if ($order && $order->amount === $payment->amount) {
            $result = PinoPay::verify($payment->authority, $order->amount);
            $order->update(['paid_at' => now(), 'ref_id' => $result->refId]);
            // سفارش را تحویل دهید.
        }
    }
})->hourly();
```

برای اجرای این کار، کرون‌جاب `php artisan schedule:run` باید روی سرور فعال باشد.

## مدیریت خطا

همهٔ خطاها `PinoSite\PinoPay\Exceptions\PinoPayException` هستند:

```php
use PinoSite\PinoPay\Exceptions\PinoPayException;

try {
    $result = PinoPay::verify($authority, $amount);
} catch (PinoPayException $exception) {
    $exception->isConnectionError(); // پاسخی نرسید؛ وضعیت پرداخت نامعلوم است
    $exception->getCode();           // کد خطا، مثلاً PinoPayException::NOT_PAID
    $exception->getMessage();        // پیام فارسی خطا
    $exception->validations;         // ایراد هر فیلد، روی کد -9
}
```

| ثابت | کد | معنی |
| --- | --- | --- |
| `INVALID_INPUT` | `-9` | ورودی‌ها معتبر نیستند |
| `INVALID_KEY` | `-10` | کلید درگاه معتبر نیست |
| `SITE_INACTIVE` | `-11` | درگاه فعال نیست |
| `MERCHANT_INACTIVE` | `-12` | حساب پذیرنده فعال نیست |
| `CALLBACK_DOMAIN` | `-13` | آدرس بازگشت روی دامنهٔ درگاه نیست |
| `AMOUNT_RANGE` | `-14` | مبلغ خارج از محدوده است |
| `TOO_MANY_REQUESTS` | `-15` | تعداد درخواست‌ها زیاد است |
| `AMOUNT_MISMATCH` | `-50` | مبلغ با تراکنش یکی نیست |
| `NOT_PAID` | `-51` | پرداخت نشده است |
| `EXPIRED` | `-52` | مهلت پرداخت تمام شده است |
| `UNKNOWN_AUTHORITY` | `-54` | تراکنش پیدا نشد |

## محیط آزمایشی

`PINO_PAY_SANDBOX=true` را در `.env` بگذارید. درخواست‌ها به محیط آزمایشی می‌روند، پولی جابه‌جا نمی‌شود و مشتری به جای درگاه بانکی دو دکمهٔ «پرداخت موفق» و «پرداخت ناموفق» می‌بیند. این محیط پیش از تأیید حساب و درگاه هم کار می‌کند و آدرس بازگشت می‌تواند هر آدرسی باشد.

> **هشدار:** این گزینه را روی سایت واقعی روشن نگذارید. در این حالت هر کسی می‌تواند بدون پرداخت، سفارش را تکمیل کند.

برای یک درخواست مشخص هم می‌توانید بدون تغییر تنظیمات از محیط آزمایشی استفاده کنید:

```php
PinoPay::sandbox()->request(1000000, $callbackUrl, 'سفارش آزمایشی');
```

## چند سایت با یک برنامه

اگر برنامهٔ شما چند درگاه دارد، کلید را برای هر درخواست تعیین کنید:

```php
PinoPay::withMerchant($shop->pino_pay_key)->request(1000000, $callbackUrl, 'سفارش ۱۲');
```

## تست برنامهٔ شما

پکیج از HTTP Client لاراول استفاده می‌کند، پس در تست‌ها می‌توانید پاسخ‌ها را با `Http::fake` شبیه‌سازی کنید:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/api/v1/payment/request' => Http::response(['data' => [
        'code' => 100,
        'message' => 'Success',
        'authority' => 'P000000000000000000000000000000000001',
        'payment_url' => 'https://pay.pinosite.ir/pay/P000000000000000000000000000000000001',
        'fee' => 10000,
        'fee_type' => 'Merchant',
    ], 'errors' => []]),

    '*/api/v1/payment/verify' => Http::response(['data' => [
        'code' => 100,
        'message' => 'Success',
        'ref_id' => '20145',
        'card_pan' => '502229******5995',
        'fee' => 10000,
        'fee_type' => 'Merchant',
        'net' => 990000,
    ], 'errors' => []]),
]);
```

برای شبیه‌سازی خطا:

```php
Http::fake(['*/api/v1/payment/verify' => Http::response([
    'data' => [],
    'errors' => ['code' => -51, 'message' => 'این تراکنش پرداخت نشده است.', 'validations' => []],
], 422)]);
```

## عیب‌یابی

| پیام یا کد | علت و راه‌حل |
| --- | --- |
| `PINO_PAY_MERCHANT_ID is not set.` | کلید در `.env` نیست. اگر تنظیمات کش شده است، `php artisan config:clear` را اجرا کنید |
| `-10` | کلید اشتباه است یا از پنل کلید تازه ساخته‌اید |
| `-11` | درگاه هنوز تأیید نشده است. تا تأیید از محیط آزمایشی استفاده کنید |
| `-13` | آدرس بازگشت باید روی دامنه‌ای باشد که برای درگاه ثبت کرده‌اید. روی سرور واقعی `APP_URL` را هم بررسی کنید |
| `-14` | مبلغ به ریال است. اگر قیمت‌ها به تومان است، در ۱۰ ضرب کنید |
| `-50` | مبلغ `verify()` با مبلغ `request()` یکی نیست؛ احتمالاً یکی به تومان و دیگری به ریال است |
