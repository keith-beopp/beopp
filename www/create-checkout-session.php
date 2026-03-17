<?php

file_put_contents(__DIR__ . '/../storage/stripe_debug.log', "TOP OF SCRIPT\n", FILE_APPEND);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/config.php';

$config = require __DIR__ . '/../config/config.php';

// ✅ Set the Stripe secret key
\Stripe\Stripe::setApiKey($config['stripe']['secret_key']);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if vote data is sent
$entryId    = $_POST['entry_id'] ?? null;
$contestId  = $_POST['contest_id'] ?? null;
$quantity   = max(1, (int) ($_POST['vote_quantity'] ?? 0));
$userId     = $_SESSION['user']['id'] ?? null;

if (!$entryId || !$contestId || !$quantity) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

try {
    file_put_contents(__DIR__ . '/../storage/stripe_debug.log', "TOP OF SCRIPT\n", FILE_APPEND);

    \Stripe\Stripe::setApiKey($config['stripe']['secret_key']);

    file_put_contents(__DIR__ . '/../storage/stripe_debug.log', "About to create session\n", FILE_APPEND);

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => 100,
                'product_data' => [
                    'name' => 'Vote for entry #' . $_POST['entry_id'],
                ],
            ],
            'quantity' => $_POST['vote_quantity'] ?? 1,
        ]],
        'mode' => 'payment',
	// 'success_url' => 'https://www.beopp.com/payment-success',
	'success_url' => 'https://www.beopp.com/payment-success?contest_id=' . urlencode($_POST['contest_id']),
 
        'cancel_url' => 'https://www.beopp.com/payment-cancel',
        'metadata' => [
            'entry_id' => $_POST['entry_id'] ?? 'missing',
            'contest_id' => $_POST['contest_id'] ?? 'missing',
	    'user_id' => $_SESSION['user']['id'] ?? 'guest',
	    'vote_quantity' => $_POST['vote_quantity'] ?? 1
        ]
    ]);

    // ✅ Optional: Clean debug confirmation
    file_put_contents(__DIR__ . '/../storage/stripe_debug.log', "Session ID: " . $session->id . "\n", FILE_APPEND);

    // ✅ The only output that goes to the browser:
    header('Content-Type: application/json');
    echo json_encode(['id' => $session->id]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    file_put_contents(__DIR__ . '/../storage/stripe_debug.log', "Stripe Fatal Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Error creating checkout session: ' . $e->getMessage()]);
    exit;
}

