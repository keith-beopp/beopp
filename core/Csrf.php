<?php
// core/Csrf.php
class Csrf {
    public static function token(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    // Validate a provided token string
    public static function validate(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
    }

    // Convenience: read token from POST ('csrf' or 'csrf_token') and validate
    public static function validateRequest(): bool {
        $token = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';
        return self::validate($token);
    }

    // Same as delete_user.php behavior: 419 on failure
    public static function requireValidPost(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method not allowed');
        }
        if (!self::validateRequest()) {
            http_response_code(419);
            exit('Invalid CSRF token');
        }
    }
}

