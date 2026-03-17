<?php
// app/controllers/AdminController.php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Csrf.php';

class AdminController {
    private static function db(): PDO {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    }

    /* ===================
       Admin Dashboard
       =================== */
    public static function dashboard(): void {
        include __DIR__ . '/../views/admin/dashboard.php';
    }

    /* ===================
       Contests (list + hard delete via FK cascade)
       =================== */
    public static function listContests(): void {
        $db = self::db();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;
        $q       = trim($_GET['q'] ?? '');

        $where  = 'WHERE 1=1';
        $params = [];

        if ($q !== '') {
            // contests has `title` (not `name`)
            $where .= ' AND (c.title LIKE ? OR c.id = ?)';
            $params[] = "%{$q}%";
            $params[] = is_numeric($q) ? (int)$q : 0;
        }

        $sql = "SELECT
                    c.id,
                    c.title,
                    c.start_date,
                    c.end_date,
                    COUNT(e.id) AS entries_count
                FROM contests c
                LEFT JOIN entries e ON e.contest_id = c.id
                $where
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT $perPage OFFSET $offset";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../views/admin/contests.php';
    }

    public static function deleteContest(): void {
        // Mirror delete_user.php behavior: POST-only + CSRF 419 on failure
        Csrf::requireValidPost(); // handles 405/419 and exits on failure

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit('Bad Request: missing contest id');
        }

        $db = self::db();
        try {
            $db->beginTransaction();

            // FK cascades are present: deleting the contest will delete its entries,
            // and those deletions will cascade to votes.
            $db->prepare('DELETE FROM contests WHERE id = ?')->execute([$id]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            http_response_code(500);
            exit('Delete failed: ' . htmlspecialchars($e->getMessage()));
        }

        header('Location: /admin/contests?deleted=1');
        exit;
    }

    /* ===================
       Entries (list + hard delete via FK cascade)
       =================== */


public static function listEntries(): void {
    $db = self::db();

    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = 20;
    $offset    = ($page - 1) * $perPage;
    $q         = trim($_GET['q'] ?? '');
    $contestId = (int)($_GET['contest_id'] ?? 0);

    // Detect if entries.is_approved exists to keep things robust
    $hasApprovedCol = self::hasColumn($db, 'entries', 'is_approved');

    $where  = 'WHERE 1=1';
    $params = [];

    if ($q !== '') {
        if (is_numeric($q)) {
            $where .= ' AND e.id = ?';
            $params[] = (int)$q;
        } else {
            $where .= ' AND c.title LIKE ?';
            $params[] = "%{$q}%";
        }
    }

    if ($contestId > 0) {
        $where .= ' AND e.contest_id = ?';
        $params[] = $contestId;
    }

    $approvedSelect = $hasApprovedCol ? 'e.is_approved AS is_approved,' : '0 AS is_approved,';

    $sql = "SELECT
                e.id,
                e.contest_id,
                $approvedSelect
                c.title AS contest_title,
                NULL AS user_id,
                CONCAT('Entry #', e.id) AS entry_label
            FROM entries e
            LEFT JOIN contests c ON c.id = e.contest_id
            $where
            ORDER BY e.id DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    include __DIR__ . '/../views/admin/entries.php';
}

/* helper */
private static function hasColumn(PDO $db, string $table, string $column): bool {
    $q = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $q->execute([$table, $column]);
    return (int)$q->fetchColumn() > 0;
}

    

public static function createContest(): void {
    $db = self::db();

    // GET -> show form
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        $errors = [];
        $form = [
            'title'        => '',
            'description'  => '',
            'niche'        => '',
            'voting_type'  => 'free',
            'prize_type'   => 'none',
            'prize_value'  => '',
            'start_date'   => '',
            'end_date'     => '',
            'sponsor_name' => '',
            'sponsor_url'  => '',
            'slug'         => '',
            'is_approved'  => 0,
        ];
        include __DIR__ . '/../views/admin/contest_create.php';
        return;
    }

    // POST -> validate + insert
    Csrf::requireValidPost(); // 405/419 if bad

    $v = static function($k) { return trim($_POST[$k] ?? ''); };

    $title        = $v('title');
    $description  = $v('description');
    $niche        = $v('niche');
    $voting_type  = in_array($v('voting_type'), ['free','paid','both'], true) ? $v('voting_type') : 'free';
    $prize_type   = in_array($v('prize_type'),  ['sponsored','progressive','none'], true) ? $v('prize_type') : 'none';
    $prize_value  = $v('prize_value');
    $start_date   = $v('start_date');
    $end_date     = $v('end_date');
    $sponsor_name = $v('sponsor_name');
    $sponsor_url  = $v('sponsor_url');
    $slug         = $v('slug');
    $is_approved  = isset($_POST['is_approved']) ? 1 : 0;

    $errors = [];

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    // Date validation (optional fields)
    $isDate = static function($d) {
        if ($d === '') return true;
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    };
    if (!$isDate($start_date)) $errors[] = 'Start date must be YYYY-MM-DD.';
    if (!$isDate($end_date))   $errors[] = 'End date must be YYYY-MM-DD.';
    if ($start_date !== '' && $end_date !== '' && $end_date < $start_date) {
        $errors[] = 'End date must be after start date.';
    }

    // Slug
    if ($slug === '') $slug = self::slugify($title);
    $slug = self::uniqueSlug($db, $slug);

    if ($errors) {
        $form = compact('title','description','niche','voting_type','prize_type','prize_value','start_date','end_date','sponsor_name','sponsor_url','slug','is_approved');
        include __DIR__ . '/../views/admin/contest_create.php';
        return;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO contests
                (title, description, niche, voting_type, prize_type, prize_value, start_date, end_date, is_approved, sponsor_name, sponsor_url, slug)
            VALUES
                (?,     ?,           ?,     ?,           ?,          ?,           ?,          ?,        ?,           ?,            ?,           ?)
        ");
        $stmt->execute([
            $title, $description, $niche, $voting_type, $prize_type, $prize_value,
            $start_date ?: null, $end_date ?: null, $is_approved, $sponsor_name, $sponsor_url, $slug
        ]);

        header('Location: /admin/contests?created=1');
        exit;
    } catch (Throwable $e) {
        $errors[] = 'Insert failed: ' . htmlspecialchars($e->getMessage());
        $form = compact('title','description','niche','voting_type','prize_type','prize_value','start_date','end_date','sponsor_name','sponsor_url','slug','is_approved');
        include __DIR__ . '/../views/admin/contest_create.php';
        return;
    }
}

/* helpers */
private static function slugify(string $text): string {
    $text = strtolower($text);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false) $text = $t;
    }
    $text = preg_replace('/[^a-z0-9]+/','-',$text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'contest';
}

private static function uniqueSlug(PDO $db, string $slug): string {
    $base = $slug;
    $i = 1;
    $stmt = $db->prepare('SELECT 1 FROM contests WHERE slug = ? LIMIT 1');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) return $slug;
        $i++;
        $slug = $base . '-' . $i;
    }
}


public static function approveEntry($id): void {
    // Enforce POST + CSRF (same pattern as delete_user.php)
    Csrf::requireValidPost(); // returns 405/419 & exits on failure

    $id = (int)$id;
    if ($id <= 0) {
        http_response_code(400);
        exit('Bad Request: invalid entry id');
    }

    $redirect = $_POST['redirect'] ?? '/admin/entries?approved=1';

    $db = self::db();
    try {
        $db->beginTransaction();
        // Mark entry as approved (relies on entries.is_approved existing)
        $stmt = $db->prepare('UPDATE entries SET is_approved = 1 WHERE id = ?');
        $stmt->execute([$id]);
        $db->commit();
    } catch (PDOException $e) {
        // If the column doesn't exist, give a helpful hint
        if ($e->getCode() === '42S22') {
            http_response_code(500);
            exit("Approval failed: entries.is_approved column not found.\n\n"
               . "Add it with:\n"
               . "ALTER TABLE entries ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0;");
        }
        $db->rollBack();
        http_response_code(500);
        exit('Approval failed: ' . htmlspecialchars($e->getMessage()));
    } catch (Throwable $e) {
        $db->rollBack();
        http_response_code(500);
        exit('Approval failed: ' . htmlspecialchars($e->getMessage()));
    }

    header('Location: ' . $redirect);
    exit;
}



    public static function deleteEntry(): void {
        // Same guardrails as delete_user.php
        Csrf::requireValidPost(); // handles 405/419 and exits on failure

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit('Bad Request: missing entry id');
        }

        $redirect = $_POST['redirect'] ?? '/admin/entries?deleted=1';

        $db = self::db();
        try {
            $db->beginTransaction();

            // FK cascade on votes: deleting the entry deletes its votes automatically.
            $db->prepare('DELETE FROM entries WHERE id = ?')->execute([$id]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            http_response_code(500);
            exit('Delete failed: ' . htmlspecialchars($e->getMessage()));
        }

        header('Location: ' . $redirect);
        exit;
    }

    /* ===================
       Legacy/compat stubs
       =================== */
    public static function moderateContests(): void { self::listContests(); }
    public static function moderateEntries(): void  { self::listEntries(); }

    public static function approveContest($id): void { http_response_code(501); echo 'Not Implemented: approveContest'; }
//    public static function approveEntry($id): void   { http_response_code(501); echo 'Not Implemented: approveEntry'; }
//    public static function createContest(): void     { http_response_code(501); echo 'Not Implemented: createContest'; }
    public static function users(): void             { http_response_code(501); echo 'Not Implemented: users'; }
    public static function deleteUser(): void        { http_response_code(501); echo 'Not Implemented: deleteUser'; }
}

