<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |----------------------------------------------------------------------
    | Parent Activation Delivery
    |----------------------------------------------------------------------
    |
    | A szülői fiókaktiváló e-mail (a személyre szóló, jelszó-beállító
    | linkkel) alapból mindig ténylegesen kimegy a beállított MAIL_MAILER
    | driveren keresztül. A "debug link" (a link közvetlen megjelenítése a
    | felületen, e-mail helyett) KIZÁRÓLAG automatizált PHPUnit tesztfutás
    | alatt aktiválódik (ld. ParentAccountActivationService::
    | isDebugLinkMode()), soha nem normál böngészős használatnál - így
    | helyi fejlesztésnél és staging/éles környezetben is a valódi e-mail
    | megy ki (helyi fejlesztésnél tipikusan a "log" MAIL_MAILER-en
    | keresztül, ami a storage/logs/laravel.log fájlba írja a levelet).
    | A delivery_enabled kapcsoló csak vészleállító: false-ra állítva
    | (MAIL_PARENT_ACTIVATION_DELIVERY_ENABLED=false) a levél sosem megy
    | ki, de a token/link ilyenkor sem jelenik meg automatikusan sehol.
    |
    */

    'parent_activation_delivery_enabled' => env('MAIL_PARENT_ACTIVATION_DELIVERY_ENABLED', true),
    'parent_activation_debug_link_for_tests' => env('MAIL_PARENT_ACTIVATION_DEBUG_LINK_FOR_TESTS', false),

    /*
    |----------------------------------------------------------------------
    | Employee Activation Delivery
    |----------------------------------------------------------------------
    |
    | Ld. a fenti "Parent Activation Delivery" blokk - ugyanaz a minta,
    | csak a dolgozói fiókaktiváló e-mailhez (ld.
    | EmployeeAccountActivationService::isDebugLinkMode()).
    |
    */

    'employee_activation_delivery_enabled' => env('MAIL_EMPLOYEE_ACTIVATION_DELIVERY_ENABLED', true),
    'employee_activation_debug_link_for_tests' => env('MAIL_EMPLOYEE_ACTIVATION_DEBUG_LINK_FOR_TESTS', false),

];
