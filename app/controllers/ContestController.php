<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../services/VotingRules.php'; // ✅

class ContestController {

    /** -------- Helpers (slug) -------- */
    private static function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private static function uniqueSlug(PDO $db, string $base): string {
        $slug = $base;
        $i = 1;
        $stmt = $db->prepare("SELECT 1 FROM contests WHERE slug = :slug LIMIT 1");
        while (true) {
            $stmt->execute(['slug' => $slug]);
            if (!$stmt->fetchColumn()) return $slug;
            $slug = $base . '-' . $i++;
        }
    }

    /** -------- Create (stores slug + redirect to pretty URL) -------- */
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $config = require __DIR__ . '/../../config/config.php';
                $db = Database::connect($config['db']);

                // Inputs
                $title        = $_POST['title']        ?? '';
                $description  = $_POST['description']  ?? '';
                $niche        = $_POST['niche']        ?? '';
                $voting_type  = $_POST['voting_type']  ?? 'free';
                $prize_type   = $_POST['prize_type']   ?? 'none';
                $prize_value  = $_POST['prize_value']  ?? '';
                $start_date   = $_POST['start_date']   ?? null;
                $end_date     = $_POST['end_date']     ?? null;

                // Build & ensure unique slug
                $baseSlug = self::slugify($title ?: 'contest');
                $slug     = self::uniqueSlug($db, $baseSlug);

                // Insert including slug
                $sql = "INSERT INTO contests
                        (title, description, niche, voting_type, prize_type, prize_value, start_date, end_date, slug)
                        VALUES (:title, :description, :niche, :voting_type, :prize_type, :prize_value, :start_date, :end_date, :slug)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    'title'       => $title,
                    'description' => $description,
                    'niche'       => $niche,
                    'voting_type' => $voting_type,
                    'prize_type'  => $prize_type,
                    'prize_value' => $prize_value,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                    'slug'        => $slug,
                ]);

                // Redirect to pretty URL
                header("Location: /contest/" . urlencode($slug));
                exit;

            } catch (Exception $e) {
                echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }

        include __DIR__ . '/../views/contest/create.php';
    }

    /** -------- List -------- */
    public static function index() {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        $stmt = $db->query("SELECT * FROM contests WHERE is_approved = 1 ORDER BY start_date DESC");
        $contests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../views/contest/index.php';
    }

    /** -------- Show by numeric ID (back-compat) -------- */
    public static function show($id) {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        if (session_status() === PHP_SESSION_NONE) session_start();

        // Flash messages
        $entryMessage = '';
        $voteMessage  = '';
        if (!empty($_SESSION['entry_message'])) {
            $entryMessage = "<p style='background:#e0ffe0;padding:10px;border:1px solid #88cc88;color:#006600;'>"
                . htmlspecialchars($_SESSION['entry_message']) . "</p>";
            unset($_SESSION['entry_message']);
        }
        if (!empty($_SESSION['vote_message'])) {
            $voteMessage = "<p style='background:#e0ffe0;padding:10px;border:1px solid #88cc88;color:#006600;'>"
                . htmlspecialchars($_SESSION['vote_message']) . "</p>";
            unset($_SESSION['vote_message']);
        }

        // Contest
        $stmt = $db->prepare("SELECT * FROM contests WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $contest = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contest) {
            http_response_code(404);
            echo "<h1>Contest Not Found</h1>";
            return;
        }

        // Leaderboard (approved only) with cover_path
        $lb = $db->prepare("
          SELECT
            e.id, e.name, e.slug,
            COALESCE(
              (SELECT path FROM entry_images WHERE entry_id = e.id AND is_primary = 1 LIMIT 1),
              (SELECT path FROM entry_images WHERE entry_id = e.id ORDER BY sort_order, id LIMIT 1),
              e.image_path
            ) AS cover_path,
            COUNT(v.id) AS votes
          FROM entries e
          LEFT JOIN votes v ON v.entry_id = e.id
          WHERE e.contest_id = ? AND e.is_approved = 1
          GROUP BY e.id
          ORDER BY votes DESC, e.id ASC
          LIMIT 100
        ");
        $lb->execute([(int)$contest['id']]);
        $leaderboard = $lb->fetchAll(PDO::FETCH_ASSOC);

        // Entries grid (approved only) with cover_path
        $stmt = $db->prepare("
          SELECT
            e.*,
            COALESCE(
              (SELECT path FROM entry_images WHERE entry_id = e.id AND is_primary = 1 LIMIT 1),
              (SELECT path FROM entry_images WHERE entry_id = e.id ORDER BY sort_order, id LIMIT 1),
              e.image_path
            ) AS cover_path,
            COUNT(v.id) AS vote_count
          FROM entries e
          LEFT JOIN votes v ON v.entry_id = e.id
          WHERE e.contest_id = ? AND e.is_approved = 1
          GROUP BY e.id
          ORDER BY created_at DESC
        ");
        $stmt->execute([$id]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $isVotingClosed   = ($today > $contest['end_date']);
        $hasVotingStarted = ($today >= $contest['start_date']);

        // Free-vote availability
        $userId = $_SESSION['user']['id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $voteStatus = VotingRules::freeVoteStatus($db, (int)$contest['id'], $userId, $ip);
        $freeVoteAvailable = $voteStatus['available'];
        $freeVoteCooldown  = $voteStatus['cooldown'];

        include __DIR__ . '/../views/contest/show.php';
    }

    /** -------- Show by slug (pretty URL) -------- */
    public static function showBySlug(string $slug) {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        if (session_status() === PHP_SESSION_NONE) session_start();

        // Flash messages
        $entryMessage = '';
        $voteMessage  = '';
        if (!empty($_SESSION['entry_message'])) {
            $entryMessage = "<p style='background:#e0ffe0;padding:10px;border:1px solid #88cc88;color:#006600;'>"
                . htmlspecialchars($_SESSION['entry_message']) . "</p>";
            unset($_SESSION['entry_message']);
        }
        if (!empty($_SESSION['vote_message'])) {
            $voteMessage = "<p style='background:#e0ffe0;padding:10px;border:1px solid #88cc88;color:#006600;'>"
                . htmlspecialchars($_SESSION['vote_message']) . "</p>";
            unset($_SESSION['vote_message']);
        }

        // Contest by slug
        $stmt = $db->prepare("SELECT * FROM contests WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $contest = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contest) {
            http_response_code(404);
            echo "<h1>Contest Not Found</h1>";
            return;
        }

        // Leaderboard (approved only) with cover_path
        $lb = $db->prepare("
          SELECT
            e.id, e.name, e.slug,
            COALESCE(
              (SELECT path FROM entry_images WHERE entry_id = e.id AND is_primary = 1 LIMIT 1),
              (SELECT path FROM entry_images WHERE entry_id = e.id ORDER BY sort_order, id LIMIT 1),
              e.image_path
            ) AS cover_path,
            COUNT(v.id) AS votes
          FROM entries e
          LEFT JOIN votes v ON v.entry_id = e.id
          WHERE e.contest_id = ? AND e.is_approved = 1
          GROUP BY e.id
          ORDER BY votes DESC, e.id ASC
          LIMIT 100
        ");
        $lb->execute([(int)$contest['id']]);
        $leaderboard = $lb->fetchAll(PDO::FETCH_ASSOC);

        // Entries grid (approved only) with cover_path
        $stmt = $db->prepare("
          SELECT
            e.*,
            COALESCE(
              (SELECT path FROM entry_images WHERE entry_id = e.id AND is_primary = 1 LIMIT 1),
              (SELECT path FROM entry_images WHERE entry_id = e.id ORDER BY sort_order, id LIMIT 1),
              e.image_path
            ) AS cover_path,
            COUNT(v.id) AS vote_count
          FROM entries e
          LEFT JOIN votes v ON v.entry_id = e.id
          WHERE e.contest_id = :cid AND e.is_approved = 1
          GROUP BY e.id
          ORDER BY created_at DESC
        ");
        $stmt->execute(['cid' => $contest['id']]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $isVotingClosed   = ($today > $contest['end_date']);
        $hasVotingStarted = ($today >= $contest['start_date']);

        // Free-vote availability
        $userId = $_SESSION['user']['id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $voteStatus = VotingRules::freeVoteStatus($db, (int)$contest['id'], $userId, $ip);
        $freeVoteAvailable = $voteStatus['available'];
        $freeVoteCooldown  = $voteStatus['cooldown'];

        include __DIR__ . '/../views/contest/show.php';
    }
}

