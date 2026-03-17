<?php
// core/Auth.php
require_once __DIR__ . '/Database.php';

class Auth
{
    /** Ensure session started */
    private static function boot(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    /** Full current URL (used for state round-trip) */
    private static function currentUrl(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'www.beopp.com';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    /** Current user array from session (or null) */
    public static function user(): ?array {
        self::boot();
        return $_SESSION['user'] ?? null;
    }

    /** Current user id (or null) */
    public static function userId(): ?int {
        $u = self::user();
        return isset($u['id']) ? (int)$u['id'] : null;
    }

    /** Is someone logged in? */
    public static function isLoggedIn(): bool {
        return self::userId() !== null;
    }

    /**
     * Require login for a page.
     * Reuses your existing Cognito Hosted UI URL (same as voting) and appends ?state=<base64(currentUrl)>.
     * Falls back to /login if auth.login_url isn't configured.
     */
    public static function requireLogin(string $fallbackLoginPath = '/login'): void {
        self::boot();
        if (self::isLoggedIn()) return;

        // Session fallback if callback doesn't send state back
        $_SESSION['post_login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';

        $config   = require __DIR__ . '/../config/config.php';
        $loginUrl = $config['auth']['login_url'] ?? null;

        if ($loginUrl) {
            $state = base64_encode(self::currentUrl());
            $sep   = (strpos($loginUrl, '?') === false) ? '?' : '&';
            header('Location: ' . $loginUrl . $sep . 'state=' . urlencode($state));
        } else {
            // Safety: let legacy /login route handle it if configured
            header('Location: ' . $fallbackLoginPath);
        }
        exit;
    }

    /** Read & clear the stored post-login redirect (use after successful login callback) */
    public static function consumePostLoginRedirect(string $fallback = '/'): string {
        self::boot();
        $to = $_SESSION['post_login_redirect'] ?? $fallback;
        unset($_SESSION['post_login_redirect']);
        return $to;
    }

    /** Refresh admin flag from DB and cache it in session */
    private static function refreshAdminFromDb(): bool {
        self::boot();
        $u = $_SESSION['user'] ?? null;
        if (!$u) return false;

        $config = require __DIR__ . '/../config/config.php';
        $db = Database::connect($config['db']);

        if (!empty($u['id'])) {
            $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$u['id']]);
        } else {
            // Case-insensitive email compare
            $stmt = $db->prepare('SELECT is_admin FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute([strtolower($u['email'] ?? '')]);
        }

        $isAdmin = ((int)$stmt->fetchColumn()) === 1;
        $_SESSION['user']['is_admin'] = $isAdmin ? 1 : 0; // cache refreshed result
        return $isAdmin;
    }

    /** Check admin (uses cached flag if present; falls back to DB) */
    public static function isAdmin(): bool {
        self::boot();
        if (isset($_SESSION['user']['is_admin'])) {
            return (int)$_SESSION['user']['is_admin'] === 1;
        }
        return self::refreshAdminFromDb();
    }

    /** Require admin for a page, with DB verification */
    public static function requireAdmin(): void {
        self::boot();

        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            http_response_code(403);
            exit('Forbidden (admin only) - no session user');
        }

        // Fast path if session already has admin=1
        if (!empty($u['is_admin']) && (int)$u['is_admin'] === 1) return;

        // Otherwise verify against DB (authoritative)
        if (!self::refreshAdminFromDb()) {
            http_response_code(403);
            exit('Forbidden (admin only)');
        }
    }
}

/** Back-compat wrapper so existing code using require_admin() keeps working. */
function require_admin(): void {
    Auth::requireAdmin();
}

