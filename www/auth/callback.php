<?php
/***************
 * beopp callback.php — unified state handling (JSON state or base64 URL), session redirect fallback
 ***************/

// ==== Config (unchanged) ====
$clientId    = '1jl0f5e01ujffgddl0jejco6d4';
$region      = 'us-west-1';
$domain      = 'us-west-1zqoknog9t.auth.us-west-1.amazoncognito.com';
$redirectUri = 'https://www.beopp.com/auth/callback.php';

// ==== Logging helper ====
$logDir  = '/var/vhosts/beopp.com/storage/logs';
$logFile = $logDir . '/id_token_debug.log';

function dbg($label, $data = null) {
    global $logDir, $logFile;
    if (!is_dir($logDir)) { @mkdir($logDir, 0750, true); }
    $line  = date('c') . " [$label]\n";
    if ($data !== null) {
        if (is_string($data)) {
            $line .= $data . "\n";
        } else {
            $line .= print_r($data, true) . "\n";
        }
    }
    $line .= "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

// ==== Session ====
if (session_status() === PHP_SESSION_NONE) session_start();

// Optional: show debug in browser if ?debug=1
$showDebug = isset($_GET['debug']) && $_GET['debug'] == '1';

// Log incoming GET
dbg('GET params', $_GET);
if ($showDebug) {
    echo "<pre>GET params:\n" . htmlspecialchars(print_r($_GET, true)) . "</pre>";
}

// ==== Step 1: get auth code ====
if (empty($_GET['code'])) {
    dbg('ERROR', 'Authorization code not provided');
    http_response_code(400);
    exit('Authorization code not provided.');
}
$code = $_GET['code'];

// ==== Step 2: exchange code for tokens ====
$tokenUrl = "https://$domain/oauth2/token";
$postBody = http_build_query([
    'grant_type'   => 'authorization_code',
    'client_id'    => $clientId,
    'code'         => $code,
    'redirect_uri' => $redirectUri,
]);

$headers = [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postBody,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_TIMEOUT        => 20,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

dbg('Token POST body', $postBody);
dbg('Token HTTP status', $httpCode);
if ($curlErr) dbg('cURL error', $curlErr);
dbg('Token raw response', $response);

if ($response === false || $httpCode >= 400) {
    http_response_code(502);
    exit('Token request failed. See log for details.');
}

$tokens = json_decode($response, true);
dbg('Decoded tokens', $tokens);

if (empty($tokens['id_token'])) {
    http_response_code(502);
    exit('ID token not received. See log for details.');
}

// ==== Step 3: decode ID token claims ====
$parts = explode('.', $tokens['id_token']);
if (count($parts) !== 3) {
    dbg('ERROR', 'Invalid ID token format');
    http_response_code(502);
    exit('Invalid ID token format.');
}
$claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
dbg('ID token claims', $claims);

if ($showDebug) {
    echo "<pre>ID token claims:\n" . htmlspecialchars(print_r($claims, true)) . "</pre>";
}

if (empty($claims) || empty($claims['email'])) {
    http_response_code(500);
    exit('Email not found in ID token. Check the log for claims.');
}

$email = $claims['email'];
$sub   = $claims['sub'] ?? null;

// ==== Step 4: DB connect ====
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php'; // for Auth::consumePostLoginRedirect
$config = require __DIR__ . '/../../config/config.php';
$db = Database::connect($config['db']);

// ==== Step 5: upsert user ====
// Prefer a stable, unique id (sub). If your users table uses a different column name, adjust here.
$stmt = $db->prepare("SELECT id FROM users WHERE sub = ? LIMIT 1");
$stmt->execute([$sub]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $stmt = $db->prepare("INSERT INTO users (email, sub, created_at, last_login) VALUES (?, ?, NOW(), NOW())");
    $stmt->execute([$email, $sub]);
    $userId = (int)$db->lastInsertId();
    dbg('User created', ['id' => $userId, 'email' => $email, 'sub' => $sub]);
} else {
    $userId = (int)$user['id'];
    $stmt = $db->prepare("UPDATE users SET email = ?, last_login = NOW() WHERE id = ?");
    $stmt->execute([$email, $userId]);
    dbg('User exists', ['id' => $userId, 'email' => $email, 'sub' => $sub]);
}

// ==== Step 6: Session ====
$_SESSION['user'] = [
    'id'    => $userId,
    'email' => $email,
    'sub'   => $sub,
];

// ==== Step 7: state handling (JSON or base64 URL) + optional auto-vote ====
// Defaults
$redirectPath = '/';
$entryId = null;

if (!empty($_GET['state'])) {
    // Try base64 decode
    $raw = base64_decode($_GET['state'], true);
    dbg('State base64 decoded', $raw !== false ? $raw : '[decode failed]');

    // Case A: JSON object like {"redirect":"/x","entry_id":123}
    $maybeJson = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($maybeJson)) {
        dbg('State parsed as JSON', $maybeJson);
        if (!empty($maybeJson['redirect']) && is_string($maybeJson['redirect'])) {
            if (strpos($maybeJson['redirect'], '/') === 0) {
                $redirectPath = $maybeJson['redirect'];
            } elseif (preg_match('~^https?://~i', $maybeJson['redirect'])) {
                // allow only same host to avoid open redirects
                $host = parse_url($maybeJson['redirect'], PHP_URL_HOST);
                if ($host === ($_SERVER['HTTP_HOST'] ?? 'www.beopp.com')) {
                    $redirectPath = $maybeJson['redirect'];
                }
            }
        }
        if (!empty($maybeJson['entry_id'])) {
            $entryId = (int)$maybeJson['entry_id'];
        }
    }
    // Case B: a full URL or a path (from Auth::requireLogin currentUrl())
    elseif (is_string($raw) && $raw !== '') {
        $candidate = $raw;

        // If it's a full URL, only accept same host
        if (preg_match('~^https?://~i', $candidate)) {
            $host = parse_url($candidate, PHP_URL_HOST);
            if ($host === ($_SERVER['HTTP_HOST'] ?? 'www.beopp.com')) {
                $redirectPath = $candidate;
            }
        } elseif ($candidate[0] === '/') {
            $redirectPath = $candidate; // safe path
        }
    }
}

// Optional auto-vote if entry_id present (from your existing flow)
if ($entryId) {
    $stmt = $db->prepare("SELECT contest_id FROM entries WHERE id = ? LIMIT 1");
    $stmt->execute([$entryId]);
    $contestId = $stmt->fetchColumn();

    if ($contestId) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM votes WHERE entry_id = ? AND ip_address = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$entryId, $ip]);
        $alreadyVoted = (int)$stmt->fetchColumn();

        if ($alreadyVoted) {
            $_SESSION['vote_message'] = "You already voted for this entry today.";
        } else {
            $stmt = $db->prepare("INSERT INTO votes (entry_id, ip_address, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$entryId, $ip, $userId]);
            $_SESSION['vote_message'] = "Thanks for voting!";
        }

        // If no explicit redirect was set by state, at least go to the contest page
        if ($redirectPath === '/' || $redirectPath === '' || $redirectPath === null) {
            $redirectPath = "/contest/$contestId";
        }
    }
}

// If redirectPath is still default, try the session fallback set by Auth::requireLogin()
if ($redirectPath === '/' || $redirectPath === '' || $redirectPath === null) {
    $redirectPath = Auth::consumePostLoginRedirect('/');
}

// ==== Step 8: redirect ====
dbg('Final redirect', $redirectPath);
header("Location: $redirectPath");
exit;

