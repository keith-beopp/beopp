<?php

// Log the fact the webhook was hit
file_put_contents(__DIR__ . '/../storage/webhook_debug.log', "✅ Stripe webhook hit at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/config.php';

// Load keys from config
$config = require __DIR__ . '/../config/config.php';
\Stripe\Stripe::setApiKey($config['stripe']['secret_key']);
$endpointSecret = $config['stripe']['webhook_secret'];

// Retrieve raw body and Stripe signature
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('❌ Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('❌ Invalid signature');
}

// Handle the checkout session completion
if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    $meta = $session->metadata;

    file_put_contents(
        __DIR__ . '/../storage/webhook_debug.log',
        "Metadata: " . print_r($meta, true) . "\n",
        FILE_APPEND
    );

    $entryId      = $meta->entry_id ?? null;
    $contestId    = $meta->contest_id ?? null;
    $userId       = $meta->user_id ?? null;
    $voteQty      = (int)($meta->vote_quantity ?? 1); // Default to 1 if missing
    $ipAddress    = 'webhook'; // Since this is server-side, not client

    if ($entryId && $voteQty > 0) {
        try {
            $db = Database::connect($config['db']);
            $stmt = $db->prepare("INSERT INTO votes (entry_id, user_id, ip_address) VALUES (?, ?, ?)");

            for ($i = 0; $i < $voteQty; $i++) {
                $stmt->execute([$entryId, $userId, $ipAddress]);
            }

            file_put_contents(
                __DIR__ . '/../storage/webhook_debug.log',
                "✅ Inserted $voteQty vote(s) for entry $entryId by user $userId\n",
                FILE_APPEND
            );
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/../storage/webhook_debug.log',
                "❌ DB Error: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }
    } else {
        file_put_contents(
            __DIR__ . '/../storage/webhook_debug.log',
            "⚠️ Missing entry_id or vote quantity — no votes inserted.\n",
            FILE_APPEND
        );
    }
}

http_response_code(200);
echo '✅ Webhook received.';

