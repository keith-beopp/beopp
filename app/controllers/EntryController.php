<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../services/VotingRules.php'; // ✅ include

class EntryController {

    /** GET/POST /contest/{slug}/enter */
    public static function createBySlug(string $slug) {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        $stmt = $db->prepare("SELECT id, slug, end_date FROM contests WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $contest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contest) {
            http_response_code(404);
            echo "<h1>Contest Not Found</h1>";
            return;
        }

        self::create((int)$contest['id']);
    }

    /** GET/POST /contest/{id}/enter (back-compat) */
    public static function create(int $contestId) {
        Auth::requireLogin();

        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        // Strongly recommended: ensure we throw on DB errors
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT id, slug, end_date FROM contests WHERE id = ? LIMIT 1");
        $stmt->execute([$contestId]);
        $contest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contest) {
            http_response_code(404);
            echo "<h1>Contest Not Found</h1>";
            return;
        }

        // entries closed?
        $today = date('Y-m-d');
        if (!empty($contest['end_date']) && $today > $contest['end_date']) {
            echo "<h1>Entries Closed</h1><p>This contest is no longer accepting new entries.</p>";
            return;
        }

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//error_log("=== ENTRY UPLOAD DEBUG ===");
//error_log("FILES = " . print_r($_FILES, true));
//error_log("POST = " . print_r($_POST, true));
//echo "<pre>";
//echo "POST:\n";
//print_r($_POST);
//echo "\nFILES:\n";
//print_r($_FILES);
//echo "</pre>";
//exit;

		
            $name = trim($_POST['name'] ?? '');
            $bio  = trim($_POST['bio']  ?? '');
            if ($name === '') {
                echo "<p>Name is required.</p>";
                return;
            }

            $userId = Auth::userId();
            $slug   = self::uniqueSlug($db, (int)$contest['id'], $name);

            // --- Upload config ---
            $uploadRootFs = '/var/vhosts/beopp.com/www'; // filesystem root for public files
            $uploadDirRel = '/uploads/entries';
            $uploadDirFs  = rtrim($uploadRootFs, '/') . $uploadDirRel;

            if (!is_dir($uploadDirFs)) {
                @mkdir($uploadDirFs, 0775, true);
            }

            $primaryIndex = isset($_POST['primary_index']) && $_POST['primary_index'] !== '' ? (int)$_POST['primary_index'] : 0;
            $captions     = isset($_POST['captions']) && is_array($_POST['captions']) ? $_POST['captions'] : [];

            $allowedTypes = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize      = 10 * 1024 * 1024; // 10 MB

            // We will set image_path to cover later (legacy)
            $entryId = 0;

            // Use a transaction so entry insert + image inserts stay consistent
            $db->beginTransaction();

            try {
                // --- Insert entry row first (legacy image_path kept for back-compat) ---
                $ins = $db->prepare("
                    INSERT INTO entries (contest_id, user_id, name, bio, image_path, created_at, is_approved, slug)
                    VALUES (:contest_id, :user_id, :name, :bio, :image_path, NOW(), 0, :slug)
                ");

                $ins->execute([
                    'contest_id' => (int)$contest['id'],
                    'user_id'    => $userId,
                    'name'       => $name,
                    'bio'        => $bio,
                    'image_path' => null,
                    'slug'       => $slug,
                ]);

                // --- Get the entry id immediately and verify it ---
                $entryId = (int)$db->lastInsertId();

                // Fallback if lastInsertId is ever unreliable in your environment
                // (e.g., something else is inserting on same connection unexpectedly)
                if ($entryId <= 0) {
                    $idStmt = $db->prepare("
                        SELECT id FROM entries
                        WHERE contest_id = ? AND user_id = ? AND slug = ?
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $idStmt->execute([(int)$contest['id'], (int)$userId, $slug]);
                    $entryId = (int)$idStmt->fetchColumn();
                }

                if ($entryId <= 0) {
                    throw new RuntimeException("Could not resolve newly created entry id.");
                }

                // --- Prepare image insert ---
                $insertImg = $db->prepare("
                  INSERT INTO entry_images (entry_id, path, caption, sort_order, is_primary)
                  VALUES (:entry_id, :path, :caption, :sort_order, :is_primary)
                ");

                $savedAny = false;

                // --- Multi-image handling (new) ---
                if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                    $names  = $_FILES['images']['name'];
                    $tmp    = $_FILES['images']['tmp_name'];
                    $errors = $_FILES['images']['error'];
                    $types  = $_FILES['images']['type'];
                    $sizes  = $_FILES['images']['size'];

                    $count = count($names);

                    for ($i = 0; $i < $count; $i++) {
                        if (!isset($errors[$i]) || $errors[$i] !== UPLOAD_ERR_OK) continue;
                        if (!isset($sizes[$i]) || $sizes[$i] > $maxSize) continue;
                        if (empty($tmp[$i]) || !is_uploaded_file($tmp[$i])) continue;

                        // Prefer more reliable type detection
                        $detected = @mime_content_type($tmp[$i]) ?: ($types[$i] ?? '');
                        $detected = strtolower($detected);

                        if (!in_array($detected, $allowedTypes, true)) continue;

                        $ext = strtolower(pathinfo($names[$i] ?? '', PATHINFO_EXTENSION));
                        if (!$ext) {
                            if (strpos($detected,'jpeg') !== false) $ext = 'jpg';
                            elseif (strpos($detected,'png') !== false) $ext = 'png';
                            elseif (strpos($detected,'webp') !== false) $ext = 'webp';
                            elseif (strpos($detected,'gif')  !== false) $ext = 'gif';
                            else $ext = 'jpg';
                        }

                        // NOTE: entryId is resolved once and never changed after this point
                        $filename  = 'entry_' . $entryId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        $targetFs  = $uploadDirFs . '/' . $filename;
                        $targetRel = $uploadDirRel . '/' . $filename; // web path

                        if (move_uploaded_file($tmp[$i], $targetFs)) {
                            $savedAny = true;
                            $insertImg->execute([
                                'entry_id'   => $entryId,
                                'path'       => $targetRel,
                                'caption'    => $captions[$i] ?? null,
                                'sort_order' => $i,
                                'is_primary' => ($i === $primaryIndex) ? 1 : 0,
                            ]);
                        }
                    }

                    // Ensure we have exactly one primary (if any were saved)
                    if ($savedAny) {
                        $hasPrimary = $db->prepare("SELECT 1 FROM entry_images WHERE entry_id=? AND is_primary=1 LIMIT 1");
                        $hasPrimary->execute([$entryId]);
                        if (!$hasPrimary->fetchColumn()) {
                            $db->prepare("UPDATE entry_images SET is_primary=1 WHERE entry_id=? ORDER BY sort_order, id LIMIT 1")
                               ->execute([$entryId]);
                        }
                    }
                } else {
                    // Legacy single image field support (if present)
                    if (!empty($_FILES['image']['tmp_name']) && ($_FILES['image']['error'] ?? null) === UPLOAD_ERR_OK) {
                        if (is_uploaded_file($_FILES['image']['tmp_name']) && ($_FILES['image']['size'] ?? 0) <= $maxSize) {
                            $detected = @mime_content_type($_FILES['image']['tmp_name']) ?: ($_FILES['image']['type'] ?? '');
                            $detected = strtolower($detected);

                            if (in_array($detected, $allowedTypes, true)) {
                                $ext = strtolower(pathinfo($_FILES['image']['name'] ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
                                $filename  = 'entry_' . $entryId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $targetFs  = $uploadDirFs . '/' . $filename;
                                $targetRel = $uploadDirRel . '/' . $filename;

                                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFs)) {
                                    $db->prepare("INSERT INTO entry_images (entry_id, path, is_primary, sort_order) VALUES (?, ?, 1, 0)")
                                       ->execute([$entryId, $targetRel]);
                                }
                            }
                        }
                    }
                }

                // Optional: keep entries.image_path in sync with cover for legacy uses
                $coverStmt = $db->prepare("
                    SELECT path FROM entry_images
                    WHERE entry_id=? ORDER BY is_primary DESC, sort_order, id LIMIT 1
                ");
                $coverStmt->execute([$entryId]);
                $coverPath = $coverStmt->fetchColumn();

                if ($coverPath) {
                    $db->prepare("UPDATE entries SET image_path = :img WHERE id = :id")
                       ->execute(['img' => $coverPath, 'id' => $entryId]);
                }

                $db->commit();

            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                http_response_code(500);
                echo "<h1>Upload Failed</h1>";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                return;
            }

            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['entry_message'] = "Thank you! Your entry has been submitted.";

            $contestSlug = $contest['slug'] ?: (string)$contest['id'];
            $redirect = '/contest/' . rawurlencode($contestSlug) . '/e/' . rawurlencode($slug);
            header("Location: {$redirect}");
            exit;
        }

        include __DIR__ . '/../views/entry/create.php';
    }

    /** GET /contest/{contestSlug}/e/{entrySlug} */
    public static function showBySlugs(string $contestSlug, string $entrySlug) {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $c = $db->prepare("SELECT id, slug, title FROM contests WHERE slug = ? LIMIT 1");
        $c->execute([$contestSlug]);
        $contest = $c->fetch(PDO::FETCH_ASSOC);
        if (!$contest) {
            http_response_code(404);
            echo "<h1>Contest Not Found</h1>";
            return;
        }

        $q = $db->prepare("
            SELECT
              e.*,
              COUNT(v.id) AS votes
            FROM entries e
            LEFT JOIN votes v ON v.entry_id = e.id
            WHERE e.contest_id = ? AND e.slug = ?
            GROUP BY e.id
            LIMIT 1
        ");
        $q->execute([(int)$contest['id'], $entrySlug]);
        $entry = $q->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            http_response_code(404);
            echo "<h1>Entry Not Found</h1>";
            return;
        }

        // Hide unapproved unless owner/admin
        if ((int)$entry['is_approved'] !== 1) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $isOwner = !empty($_SESSION['user']['id']) && ((int)$_SESSION['user']['id'] === (int)$entry['user_id']);
            $isAdmin = !empty($_SESSION['user']['is_admin']);
            if (!$isOwner && !$isAdmin) {
                http_response_code(403);
                echo "This entry is pending review.";
                return;
            }
        }

        // --- Load images for gallery ---
        $imgStmt = $db->prepare("SELECT id, path, caption, is_primary, sort_order FROM entry_images WHERE entry_id=? ORDER BY is_primary DESC, sort_order, id");
        $imgStmt->execute([(int)$entry['id']]);
        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        // ------------ Layout variables ------------
        $contestTitle = $contest['title'] ?? ('Contest ' . $contest['slug']);
        $pageTitle    = $entry['name'] . ' in ' . $contestTitle;
        $shareUrl     = "https://www.beopp.com/contest/{$contest['slug']}/e/{$entry['slug']}";
        $canonicalUrl = $shareUrl;

        // Prefer the primary/first image from entry_images, fallback to legacy entries.image_path
        $coverPathRel = $images[0]['path'] ?? ($entry['image_path'] ?? '');
        $imageUrl     = $coverPathRel ? ((strpos($coverPathRel, '/') === 0 ? 'https://www.beopp.com' . $coverPathRel : $coverPathRel)) : '';

        $meta = [
            'og:type'        => 'website',
            'og:url'         => $shareUrl,
            'og:title'       => $pageTitle,
            'og:description' => "Vote for {$entry['name']} in {$contestTitle}!",
            'og:image'       => $imageUrl,
            'og:site_name'   => 'Beopp',
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $pageTitle,
            'twitter:description' => "Vote for {$entry['name']} in {$contestTitle}!",
            'twitter:image'       => $imageUrl,
        ];

        // ✅ FREE VOTE AVAILABILITY
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['user']['id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $voteStatus = VotingRules::freeVoteStatus($db, (int)$contest['id'], $userId, $ip);
        $freeVoteAvailable = $voteStatus['available'];
        $freeVoteCooldown  = $voteStatus['cooldown'];

        // ------------ Render body view ------------
        $contest_ref = ['id' => $contest['id'], 'slug' => $contest['slug'], 'title' => $contestTitle];

        ob_start();
        /** @var array $entry, $contest_ref, $freeVoteAvailable, $freeVoteCooldown, $images available to the view */
        include __DIR__ . '/../views/entry/show.php';
        $content = ob_get_clean();

        include __DIR__ . '/../views/entry/layout.php';
    }

    /** Unique slug per contest */
    private static function uniqueSlug(PDO $db, int $contestId, string $name): string {
        $base = preg_replace('~[^a-z0-9]+~i', '-', strtolower(trim($name)));
        $base = trim($base, '-') ?: 'entry';
        $slug = $base; $i = 2;

        $check = $db->prepare("SELECT 1 FROM entries WHERE contest_id = ? AND slug = ? LIMIT 1");
        while (true) {
            $check->execute([$contestId, $slug]);
            if (!$check->fetch()) return $slug;
            $slug = $base . '-' . $i++;
        }
    }
}

