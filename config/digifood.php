<?php

return [
    'business_timezone' => env('BUSINESS_TIMEZONE', 'Europe/Budapest'),
    'cancellation_horizon_days' => 400,
    'maximum_bulk_cancellation_days' => 366,
    'saas_billing_summary_recipient' => env('SAAS_BILLING_SUMMARY_EMAIL', 'info@digifood.hu'),
    'saas_billing_summary_day_of_month' => 5,
];
