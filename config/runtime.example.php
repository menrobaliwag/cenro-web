<?php
declare(strict_types=1);

return [
    'app_url' => 'https://mrf.cityenro.com',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u123456789_newcityenro',
        'user' => 'u123456789_dbuser',
        'pass' => 'change-me',
    ],
    'mail' => [
        'host' => 'smtp.hostinger.com',
        'port' => 587,
        'secure' => 'tls',
        'auth' => true,
        'user' => 'no-reply@mrf.cityenro.com',
        'pass' => 'change-me',
        'sender' => 'no-reply@mrf.cityenro.com',
        'sender_name' => 'CENRO Support',
    ],
    'security' => [
        'data_key_current' => 'base64:GENERATE_32_BYTE_KEY_AND_PASTE_HERE',
        'email_hash_key_current' => 'GENERATE_LONG_RANDOM_HASH_KEY_AND_PASTE_HERE',
    ],
    'brevo' => [
        'api_key' => '',
        'smtp_user' => '',
        'smtp_pass' => '',
        'sender' => 'no-reply@mrf.cityenro.com',
        'sender_name' => 'CENRO Support',
    ],
];
