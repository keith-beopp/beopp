<?php
// app/controllers/AuthController.php
class AuthController {
    public static function login() {
        $config = require __DIR__ . '/../../config/config.php';

        // Put your Cognito Hosted UI login URL in config['auth']['login_url']
        // Example format:
        // https://<your-domain>.auth.<region>.amazoncognito.com/login
        //   ?client_id=xxx
        //   &response_type=code
        //   &scope=openid+email+profile
        //   &redirect_uri=https%3A%2F%2Fwww.beopp.com%2Fauth%2Fcognito%2Fcallback
        $loginUrl = $config['auth']['login_url'] ?? null;

        if (!$loginUrl) {
            http_response_code(500);
            echo "Login not configured. Set \$config['auth']['login_url'] in config.php";
            return;
        }

        header('Location: ' . $loginUrl);
        exit;
    }
}

