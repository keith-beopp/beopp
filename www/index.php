<?php
?>
<!-- TEST DEPLOYMENT -->
<div style="background:#ffef96;padding:10px;text-align:center;">
Local deployment test working
</div>
<?php

// Enable full error reporting (disable in production)

// Canonical host: redirect bare apex to www in production, but allow staging and local hosts.
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($host === 'beopp.com') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    header('Location: ' . $scheme . '://www.beopp.com' . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

// Session cookie for both www and apex (must be before any session_start anywhere)
ini_set('session.cookie_domain', '.beopp.com'); // works for beopp.com + www.beopp.com
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';   // require_admin()
require_once __DIR__ . '/../core/Csrf.php';   // CSRF token helpers
require_once __DIR__ . '/../app/controllers/ContestController.php';
require_once __DIR__ . '/../app/controllers/EntryController.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';

// Parse the request URI
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Route requests
switch (true) {

    // --- Auth ---
    case $uri === '/login':
        AuthController::login();
        break;

    // --- Home ---
    case $uri === '/' || $uri === '/index.php':
        require_once __DIR__ . '/../app/controllers/HomeController.php';
        HomeController::index();
        break;

case $uri === '/user':
  UserController::profile();
  break;

    // --- Contest list ---
    case $uri === '/contests':
        ContestController::index();
        break;

    // ============================================================
    // Entry page (MUST be before generic /contest/{slug} route)
    // /contest/{contestSlug}/e/{entrySlug}
    // ============================================================
    case preg_match('#^/contest/([a-z0-9\-]+)/e/([a-z0-9\-]+)$#i', $uri, $m):
        EntryController::showBySlugs($m[1], $m[2]);
        break;

    // --- Enter form via contest slug (GET & POST) ---
    case preg_match('#^/contest/([a-z0-9\-]+)/enter$#i', $uri, $m):
        EntryController::createBySlug($m[1]);
        break;

    // --- Generic contest page by slug (keep AFTER entry/enter routes) ---
    case preg_match('#^/contest/([a-z0-9\-]+)$#i', $uri, $m):
        ContestController::showBySlug($m[1]);
        break;

    // --- Numeric routes (legacy/back-compat) ---
    case preg_match('#^/contest/(\d+)/enter$#', $uri, $matches):
        EntryController::create((int)$matches[1]);
        break;

    case preg_match('#^/contest/(\d+)$#', $uri, $matches):
        ContestController::show((int)$matches[1]); // should 301 to slug inside controller
        break;

    // --- Voting (keep your existing handler) ---
    case $uri === '/vote':
        require_once __DIR__ . '/../app/controllers/VoteController.php';
        VoteController::submit();
        break;

    // =========================
    // Admin
    // =========================
    case $uri === '/admin' && $method === 'GET':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::dashboard();
        break;

    // Contests list (GET)
    case $uri === '/admin/contests' && $method === 'GET':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::listContests();
        break;

    // Contest hard delete (POST)
    case $uri === '/admin/contests/delete':
        if ($method !== 'POST') {
            http_response_code(405);
            echo "Method not allowed";
            break;
        }
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::deleteContest();
        break;

    // Entries list (GET)
    case $uri === '/admin/entries' && $method === 'GET':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::listEntries();
        break;

    // Entry hard delete (POST)
    case $uri === '/admin/entries/delete':
        if ($method !== 'POST') {
            http_response_code(405);
            echo "Method not allowed";
            break;
        }
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::deleteEntry();
        break;

    case preg_match('#^/admin/contest/(\d+)/approve$#', $uri, $matches):
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::approveContest((int)$matches[1]);
        break;

    case $uri === '/admin/entries' && $method === 'POST':
        http_response_code(405);
        echo "Method not allowed";
        break;

    case preg_match('#^/admin/entry/(\d+)/approve$#', $uri, $matches):
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::approveEntry((int)$matches[1]);
        break;

    case $uri === '/admin/contest/create':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::createContest();
        break;

    case $uri === '/admin/users':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        require_admin();
        AdminController::users();
        break;

    case $uri === '/admin/users/delete':
        if ($method === 'POST') {
            require_once __DIR__ . '/../app/controllers/AdminController.php';
            require_admin();
            AdminController::deleteUser();
        } else {
            http_response_code(405);
            echo "Method not allowed";
        }
        break;

    default:
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        echo "<p>The page you requested doesn't exist.</p>";
        break;
}

