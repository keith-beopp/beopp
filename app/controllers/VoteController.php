<?php
require_once __DIR__ . '/../../core/Database.php';

class VoteController {
    public static function submit() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // POST only
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Method not allowed";
            return;
        }

        // Validate entry id
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId <= 0) {
            http_response_code(400);
            echo "Bad Request: missing entry_id";
            return;
        }

        // Load entry + contest (to get slug + dates)
        $config = require __DIR__ . '/../../config/config.php';
        $db     = Database::connect($config['db']);

        $stmt = $db->prepare("
            SELECT
                e.id   AS entry_id,
                e.contest_id,
                c.id   AS cid,
                c.slug AS slug,
                c.start_date,
                c.end_date
            FROM entries e
            JOIN contests c ON c.id = e.contest_id
            WHERE e.id = :eid
            LIMIT 1
        ");
        $stmt->execute(['eid' => $entryId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            http_response_code(404);
            echo "<h1>Entry Not Found</h1>";
            return;
        }

        $contestId = (int)$info['contest_id'];
        $contestUrl = !empty($info['slug'])
            ? '/contest/' . rawurlencode($info['slug'])
            : '/contest/' . (int)$info['cid'];

        // If user not logged in, send to Cognito with slug redirect
        if (empty($_SESSION['user'])) {
            $_SESSION['pending_vote'] = ['entry_id' => $entryId];

            $statePayload = [
                'redirect' => $contestUrl,
                'entry_id' => $entryId,
            ];
            $state = urlencode(base64_encode(json_encode($statePayload)));

            $loginUrl = "https://us-west-1zqoknog9t.auth.us-west-1.amazoncognito.com/oauth2/authorize"
                . "?client_id=1jl0f5e01ujffgddl0jejco6d4"
                . "&response_type=code"
                . "&scope=email+openid"
                . "&redirect_uri=https://www.beopp.com/auth/callback.php"
                . "&state={$state}";

            header("Location: $loginUrl");
            exit;
        }

        // Voting window checks
        $today = date('Y-m-d');
        if (!empty($info['start_date']) && $today < $info['start_date']) {
            $_SESSION['vote_message'] = "Voting hasn't started yet.";
            header("Location: $contestUrl");
            exit;
        }
        if (!empty($info['end_date']) && $today > $info['end_date']) {
            $_SESSION['vote_message'] = "Voting is closed.";
            header("Location: $contestUrl");
            exit;
        }

        // ----- Per-CONTEST 24h guard -----
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $userId = $_SESSION['user']['id'] ?? null;

        // Find the most recent vote this user/IP cast in THIS contest within the last 24 hours
        $checkSql = "
            SELECT MAX(v.created_at) AS last_vote_at
            FROM votes v
            JOIN entries e2 ON e2.id = v.entry_id
            WHERE e2.contest_id = :contest_id
              AND v.created_at >= (NOW() - INTERVAL 24 HOUR)
              AND (
                    (:uid IS NOT NULL AND v.user_id = :uid)
                 OR (:uid IS NULL  AND v.user_id IS NULL AND v.ip_address = :ip)
              )
        ";
        $check = $db->prepare($checkSql);
        $check->execute([
            'contest_id' => $contestId,
            'uid'        => $userId, // may be null
            'ip'         => $ip,
        ]);
        $lastVoteAt = $check->fetchColumn();

        if ($lastVoteAt) {
            // Compute time remaining in a friendly way
            $elapsed   = time() - strtotime($lastVoteAt);
            $remaining = max(0, (24 * 3600) - $elapsed);
            $hrs = floor($remaining / 3600);
            $mins = floor(($remaining % 3600) / 60);
            $timeLeft = ($hrs > 0)
                ? "{$hrs}h " . str_pad((string)$mins, 2, '0', STR_PAD_LEFT) . "m"
                : "{$mins}m";

            $_SESSION['vote_message'] = "You already used your free vote for this contest. Come back in {$timeLeft}.";
            header("Location: $contestUrl");
            exit;
        }

        // Insert the vote (still records per-entry; the limit is enforced per-contest)
        $ins = $db->prepare("
            INSERT INTO votes (entry_id, ip_address, user_id)
            VALUES (:entry, :ip, :uid)
        ");
        $ins->execute([
            'entry' => $entryId,
            'ip'    => $ip,
            'uid'   => $userId,
        ]);

        $_SESSION['vote_message'] = "Thanks for voting!";
        header("Location: $contestUrl");
        exit;
    }
}

