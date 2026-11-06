<?php

return [

    // The key of your site, from the "درگاه‌ها" page of your Pino Pay panel. Keep it on the server only.
    'merchant_id' => env('PINO_PAY_MERCHANT_ID'),

    // true sends every call to the sandbox: no money moves and the customer picks the outcome.
    // Never leave this on in production, or orders can be completed without paying.
    'sandbox' => (bool) env('PINO_PAY_SANDBOX', false),

    'base_url' => env('PINO_PAY_BASE_URL', 'https://pay.pinosite.ir'),

    // Seconds to wait for an answer.
    'timeout' => (int) env('PINO_PAY_TIMEOUT', 20),

];
