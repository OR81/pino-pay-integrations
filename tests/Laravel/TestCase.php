<?php

namespace PinoSite\PinoPay\Tests\Laravel;

use Orchestra\Testbench\TestCase as Orchestra;
use PinoSite\PinoPay\Facades\PinoPay;
use PinoSite\PinoPay\PinoPayServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const KEY = '8f14e45f-ceea-4e7d-a1b2-0123456789ab';

    protected function getPackageProviders($app): array
    {
        return [PinoPayServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['PinoPay' => PinoPay::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('pino-pay.merchant_id', self::KEY);
        $app['config']->set('pino-pay.base_url', 'https://pay.example.test');
        $app['config']->set('pino-pay.sandbox', false);
    }
}
