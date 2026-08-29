<?php

return [
    'merchant_url' => env('CIB_MERCHANT_URL', 'https://ekit.cib.hu/market.saki'),
    'customer_url' => env('CIB_CUSTOMER_URL', 'https://ekit.cib.hu/customer.saki'),
    'default_pid' => env('CIB_DEFAULT_PID'),
    'default_secret_key_base64' => env('CIB_DEFAULT_SECRET_KEY_BASE64'),
    'default_secret_key_file' => env('CIB_DEFAULT_SECRET_KEY_FILE'),
    'default_return_url' => env('CIB_DEFAULT_RETURN_URL'),
    'poll_interval_seconds' => (int) env('CIB_POLL_INTERVAL_SECONDS', 90),
    'request_timeout_seconds' => (int) env('CIB_REQUEST_TIMEOUT_SECONDS', 20),
];
