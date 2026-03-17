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

    // Stripe
    'stripe' => [
        'secret_key' => 'sk_test_CHANGE_ME',
        'webhook_secret' => 'whsec_CHANGE_ME',
    ],
];

