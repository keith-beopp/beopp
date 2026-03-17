<?php
// delete_user.php
// POST-only; deletes a user from Cognito (and your local DB).

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
if (empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    exit('Forbidden');
}
if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? null)) {
    http_response_code(419);
    exit('Invalid CSRF token');
}

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    http_response_code(400);
    exit('Missing username');
}

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

// --- CONFIG ---
$userPoolId = 'us-west-1_Zqoknog9t'; // <-- your pool id
$region     = getenv('AWS_REGION') ?: 'us-west-1';

// --- AWS CLIENT ---
$cognito = new CognitoIdentityProviderClient([
    'version' => '2016-04-18',
    'region'  => $region,
]);

// 1) Delete from Cognito
try {
    $cognito->adminDeleteUser([
        'UserPoolId' => $userPoolId,
        'Username'   => $username,
    ]);
} catch (Throwable $e) {
    // If Cognito user is already gone, you might still want to clean local DB.
    // You can choose to stop here or continue.
    error_log("adminDeleteUser failed for {$username}: " . $e->getMessage());
}

// 2) Delete from your local DB (users table keyed by 'sub' or 'username')
try {
    $config = require __DIR__ . '/../../config/config.php';
    $db = Database::connect($config['db']);

    // If you store Cognito 'sub' (preferred), you can map username === sub for federated users.
    // Otherwise you may store by email; adapt this query to your schema.
    $stmt = $db->prepare("DELETE FROM users WHERE sub = ? OR email IN (
                            SELECT value FROM (
                              SELECT ? as value
                            ) as t
                          )");
    $stmt->execute([$username, $username]);
} catch (Throwable $e) {
    error_log("Local DB cleanup failed for {$username}: " . $e->getMessage());
}

// 3) Optionally cascade cleanup (votes, entries, etc.) — or implement soft delete instead.

// Redirect back
header('Location: admin_users.php');
exit;

