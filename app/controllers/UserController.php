<?php
require_once __DIR__ . '/../../core/Database.php';

class UserController {

    private static function requireLogin(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            http_response_code(403);
            echo "Forbidden: please <a href=\"/login\">sign in</a>.";
            exit;
        }
        return $_SESSION['user'];
    }

    private static function contestUrl(array $c): string {
        // Prefer slug if you have one; fall back to id
        if (!empty($c['slug'])) return "/contest/" . urlencode($c['slug']);
        return "/contest/" . intval($c['id']);
    }

    private static function partitionByDates(array $contests): array {
        $now = new DateTimeImmutable('now');
        $upcoming = [];
        $current  = [];
        $past     = [];

        foreach ($contests as $c) {
            $start = new DateTimeImmutable($c['start_date']);
            $end   = new DateTimeImmutable($c['end_date']);
            if ($start > $now) {
                $upcoming[] = $c;
            } elseif ($end < $now) {
                $past[] = $c;
            } else {
                $current[] = $c;
            }
        }

        // Optional: sort each group by dates
        usort($upcoming, fn($a,$b)=>strcmp($a['start_date'],$b['start_date']));
        usort($current,  fn($a,$b)=>strcmp($a['end_date'],$b['end_date']));
        usort($past,     fn($a,$b)=>strcmp($b['end_date'],$a['end_date']));

        return [$upcoming, $current, $past];
    }

    public static function profile() {
        $user = self::requireLogin();

        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        // ---- Contests the user ENTERED ----
        // Assumes entries(user_id, contest_id, created_at, approved etc.)
        $sqlEntered = "
            SELECT DISTINCT c.id, c.title, c.slug, c.start_date, c.end_date
            FROM contests c
            JOIN entries e ON e.contest_id = c.id
            WHERE e.user_id = :uid
            ORDER BY c.end_date DESC
        ";
        $stmt = $db->prepare($sqlEntered);
        $stmt->execute([':uid' => $user['id']]);
        $entered = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ---- Contests the user VOTED IN ----
        // Assumes votes(user_id, entry_id, created_at) and entries(contest_id)
        $sqlVoted = "
            SELECT DISTINCT c.id, c.title, c.slug, c.start_date, c.end_date
            FROM contests c
            JOIN entries  en ON en.contest_id = c.id
            JOIN votes    v  ON v.entry_id   = en.id
            WHERE v.user_id = :uid
            ORDER BY c.end_date DESC
        ";
        $stmt = $db->prepare($sqlVoted);
        $stmt->execute([':uid' => $user['id']]);
        $voted = $stmt->fetchAll(PDO::FETCH_ASSOC);

        [$enteredUpcoming, $enteredCurrent, $enteredPast] = self::partitionByDates($entered);
        [$votedUpcoming,   $votedCurrent,   $votedPast]   = self::partitionByDates($voted);

        // ---- Render simple view ----
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>My Dashboard</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                .section{margin:22px 0}
                .pill{display:block;padding:10px 12px;border:1px solid #ddd;border-radius:8px;margin:6px 0;text-decoration:none}
                .muted{color:#666;font-size:0.9em}
                h2{margin-bottom:6px}
            </style>
        </head>
        <body>
        <div style="background:#f4f4f4;padding:8px 10px;border-radius:4px;margin:10px 0;">
            Welcome, <strong><?php echo htmlspecialchars($user['email']); ?></strong> |
            <a href="/logout">Logout</a> |
            <a href="/">Home</a>
        </div>

        <h1>My Dashboard</h1>

        <div class="section">
            <h2>Contests I Entered</h2>
            <h3>Current</h3>
            <?php if (!$enteredCurrent): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($enteredCurrent as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Ends <?php echo htmlspecialchars($c['end_date']); ?>)</span>
                </a>
            <?php endforeach; ?>

            <h3>Upcoming</h3>
            <?php if (!$enteredUpcoming): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($enteredUpcoming as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Starts <?php echo htmlspecialchars($c['start_date']); ?>)</span>
                </a>
            <?php endforeach; ?>

            <h3>Past</h3>
            <?php if (!$enteredPast): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($enteredPast as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Ended <?php echo htmlspecialchars($c['end_date']); ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="section">
            <h2>Contests I Voted In</h2>
            <h3>Current</h3>
            <?php if (!$votedCurrent): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($votedCurrent as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Ends <?php echo htmlspecialchars($c['end_date']); ?>)</span>
                </a>
            <?php endforeach; ?>

            <h3>Upcoming</h3>
            <?php if (!$votedUpcoming): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($votedUpcoming as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Starts <?php echo htmlspecialchars($c['start_date']); ?>)</span>
                </a>
            <?php endforeach; ?>

            <h3>Past</h3>
            <?php if (!$votedPast): ?><div class="muted">None</div><?php endif; ?>
            <?php foreach ($votedPast as $c): ?>
                <a class="pill" href="<?php echo self::contestUrl($c); ?>">
                    <?php echo htmlspecialchars($c['title']); ?>
                    <span class="muted"> (Ended <?php echo htmlspecialchars($c['end_date']); ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        </body>
        </html>
        <?php
    }
}

