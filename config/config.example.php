<?php

// Copy this file to `config.php` and fill in real values.
// IMPORTANT: `config/config.php` is intentionally ignored by Git.

return [
    // Database
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'beopp',
        'user' => 'beopp',
        'pass' => 'CHANGE_ME',
    ],

    // Stripe (test keys for local/staging only)
    'stripe' => [
        'secret_key'     => 'sk_test_CHANGE_ME',
        'webhook_secret' => 'whsec_CHANGE_ME',
    ],

    // Email — provider-agnostic settings
    'email' => [
        'provider'   => 'smtp',              // e.g. 'smtp', 'sendgrid', 'mailgun'
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'Beopp',

        // SMTP-style provider settings
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'username'   => 'SMTP_USER_CHANGE_ME',
            'password'   => 'SMTP_PASS_CHANGE_ME',
            'encryption' => 'tls',          // or 'ssl'
        ],

        // Example for API-based provider (fill in only if you use it)
        'sendgrid' => [
            'api_key' => 'SENDGRID_API_KEY_CHANGE_ME',
        ],
    ],
];

