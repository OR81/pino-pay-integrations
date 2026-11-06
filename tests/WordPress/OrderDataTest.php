<?php

namespace PinoSite\PinoPay\Tests\WordPress;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use PinoPay_Order_Data;

class OrderDataTest extends TestCase
{
    #[TestWith(['IRR', 1000000, 1000000])]
    #[TestWith(['IRT', 100000, 1000000])]
    #[TestWith(['IRHR', 1000, 1000000])]
    #[TestWith(['IRHT', 100, 1000000])]
    #[TestWith(['IRT', '150000.00', 1500000])]
    #[TestWith(['IRHT', '1.5', 15000])]
    public function test_a_total_is_converted_to_rial(string $currency, int|string $total, int $rial): void
    {
        $this->assertSame($rial, PinoPay_Order_Data::to_rial($total, $currency));
        $this->assertTrue(PinoPay_Order_Data::supports_currency($currency));
    }

    #[TestWith(['USD'])]
    #[TestWith(['EUR'])]
    #[TestWith([''])]
    public function test_a_foreign_currency_is_refused(string $currency): void
    {
        $this->assertNull(PinoPay_Order_Data::to_rial(100, $currency));
        $this->assertFalse(PinoPay_Order_Data::supports_currency($currency));
    }

    public function test_a_float_total_does_not_lose_a_rial(): void
    {
        $this->assertSame(1999, PinoPay_Order_Data::to_rial(199.9, 'IRT'));
    }

    #[TestWith(['09121234567'])]
    #[TestWith(['۰۹۱۲۱۲۳۴۵۶۷'])]
    #[TestWith(['٠٩١٢١٢٣٤٥٦٧'])]
    #[TestWith(['+98 912 123 4567'])]
    #[TestWith(['00989121234567'])]
    #[TestWith(['989121234567'])]
    #[TestWith(['9121234567'])]
    #[TestWith(['0912-123-4567'])]
    public function test_a_mobile_number_is_normalized(string $typed): void
    {
        $this->assertSame('09121234567', PinoPay_Order_Data::mobile($typed));
    }

    #[TestWith(['02112345678'])]
    #[TestWith(['0912123456'])]
    #[TestWith([''])]
    #[TestWith(['not a number'])]
    #[TestWith([['09121234567']])]
    #[TestWith([null])]
    public function test_what_is_not_a_mobile_number_is_left_out(mixed $typed): void
    {
        $this->assertNull(PinoPay_Order_Data::mobile($typed));
    }
}
