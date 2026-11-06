<?php

namespace PinoSite\PinoPay;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class PinoPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pino-pay.php', 'pino-pay');

        $this->app->bind(PinoPay::class, function ($app) {
            $config = $app['config']['pino-pay'];

            return new PinoPay(
                $app->make(Factory::class),
                (string) $config['merchant_id'],
                (bool) $config['sandbox'],
                (string) $config['base_url'],
                (int) $config['timeout'],
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/pino-pay.php' => config_path('pino-pay.php')], 'pino-pay-config');
    }
}
