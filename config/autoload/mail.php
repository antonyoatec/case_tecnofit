<?php

declare(strict_types=1);

return [
    'default' => env('MAIL_DRIVER', 'smtp'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@pixwithdrawal.com'),
        'name' => env('MAIL_FROM_NAME', 'PIX Withdrawal Service'),
    ],
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'mailhog'),
            'port' => env('MAIL_PORT', 1025),
            'encryption' => env('MAIL_ENCRYPTION'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],
    ],
];