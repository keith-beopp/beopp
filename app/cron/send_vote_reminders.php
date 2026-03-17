<?php
/**
 * send_vote_reminders.php (flexible)
 * Flags:
 *   --dry-run         : do not send, just list targets
 *   --force           : ignore vote_reminders 24h suppression
 *   --window=<hours>  : use a custom inactivity window (default 24)
 */

declare(strict_types=1);
ini_set('display_errors', '0'); error_reporting(E_ALL);

// -------- Configure these --------
const SES_REGION_HOST = 'email-smtp.us-west-1.amazonaws.com'; // <-- your SES region host
const SES_SMTP_USER   = 'AKIAVXL56EKN3J7WGOJS';
const SES_SMTP_PASS   = 'BFcFp8K8I44D2kX3lc+5mLtA/QiR7rtMfKDlUJAXfq58';
const FROM_EMAIL      = 'no-reply@beopp.com';
const FROM_NAME       = 'Beopp';
const MAX_PER_RUN     = 500;
// ---------------------------------

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/Database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ---- Parse flags
$dryRun  = in_array('--dry-run', $argv, true);
$force   = in_array('--force',   $argv, true);
$windowH = 24;
foreach ($argv as $a) {
  if (preg_match('/^--window=(\d{1,3})$/', $a, $m)) { $windowH = max(1, (int)$m[1]); }
}

$logFile = '/var/vhosts/beopp.com/storage/cron_vote_reminders.log';
function slog(string $m) {
  global $logFile; file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $m\n", FILE_APPEND);
}

slog('--- send_vote_reminders: start --- flags='.implode(' ', array_slice($argv,1))." window={$windowH}h");

try {
  // DB connect
  $config = require __DIR__ . '/../../config/config.php';
  $db = Database::connect($config['db']);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Ensure helper table exists
  $db->exec("
    CREATE TABLE IF NOT EXISTS vote_reminders (
      user_id    INT NOT NULL,
      contest_id INT NOT NULL,
      last_sent  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (user_id, contest_id)
    ) ENGINE=InnoDB
  ");

  // Build base target list (last vote per (user,contest) in ACTIVE contests)
  $targetsSql = "
    SELECT
      u.id      AS user_id,
      u.email   AS email,
      c.id      AS contest_id,
      c.title   AS contest_title,
      c.slug    AS contest_slug,
      t.last_vote_at
    FROM (
      SELECT v.user_id, en.contest_id, MAX(v.created_at) AS last_vote_at
      FROM votes v
      JOIN entries  en ON en.id = v.entry_id
      JOIN contests c  ON c.id = en.contest_id
      WHERE v.user_id IS NOT NULL
        AND c.start_date <= CURDATE()
        AND c.end_date   >= CURDATE()
      GROUP BY v.user_id, en.contest_id
    ) t
    JOIN users u    ON u.id = t.user_id
    JOIN contests c ON c.id = t.contest_id
  ";

  // Inactivity window filter (hasn't voted in X hours)
  $targetsSql .= " WHERE t.last_vote_at < (NOW() - INTERVAL {$windowH} HOUR)";

  // Suppression filter (unless --force)
  if (!$force) {
    $targetsSql .= "
      AND NOT EXISTS (
        SELECT 1 FROM vote_reminders r
        WHERE r.user_id = t.user_id
          AND r.contest_id = t.contest_id
          AND r.last_sent >= (NOW() - INTERVAL 24 HOUR)
      )
    ";
  }

  $targetsSql .= " ORDER BY c.end_date ASC LIMIT ".(int)MAX_PER_RUN;

  $targets = $db->query($targetsSql)->fetchAll(PDO::FETCH_ASSOC);
  slog('targets='.count($targets));

  if ($dryRun) {
    foreach ($targets as $row) {
      slog("DRY: {$row['email']} contest={$row['contest_id']} title=\"{$row['contest_title']}\" last_vote_at={$row['last_vote_at']}");
    }
    slog('--- send_vote_reminders: end (dry-run) ---'); exit(0);
  }

  if (!$targets) { slog('no targets, exiting'); slog('--- send_vote_reminders: end ---'); exit(0); }

  // Prepare mailer
  $mailer = new PHPMailer(true);
  $mailer->isSMTP();
  $mailer->Host       = SES_REGION_HOST;
  $mailer->SMTPAuth   = true;
  $mailer->Username   = SES_SMTP_USER;
  $mailer->Password   = SES_SMTP_PASS;
  $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mailer->Port       = 587;
  $mailer->CharSet    = 'UTF-8';
  $mailer->setFrom(FROM_EMAIL, FROM_NAME);

  // Upsert for suppression
  $upsert = $db->prepare("
    INSERT INTO vote_reminders (user_id, contest_id, last_sent)
    VALUES (:uid, :cid, NOW())
    ON DUPLICATE KEY UPDATE last_sent = VALUES(last_sent)
  ");

  // URL helper
  $contestUrl = fn($r) =>
    !empty($r['contest_slug'])
      ? 'https://www.beopp.com/contest/'.rawurlencode($r['contest_slug'])
      : 'https://www.beopp.com/contest/'.(int)$r['contest_id'];

  $sent = 0;
  foreach ($targets as $row) {
    $to    = $row['email'];
    $title = $row['contest_title'];
    $link  = $contestUrl($row);

    $subject = "Your daily vote is ready for “{$title}”";
    $html = "
      <div style='font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.45'>
        <p>Hi there,</p>
        <p>It’s been over {$windowH} hours since your last vote in <strong>{$title}</strong>.</p>
        <p><a href='{$link}' style='display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;'>Vote again</a></p>
        <p style='color:#666;font-size:13px;margin-top:20px'>You’re receiving this because you voted in this contest on Beopp.</p>
      </div>
    ";

    try {
      $mailer->clearAllRecipients();
      $mailer->Subject = $subject;
      $mailer->Body    = $html;
      $mailer->isHTML(true);
      $mailer->addAddress($to);
      $mailer->send();
      $sent++;

      // record send (even if --force, we still stamp last_sent)
      $upsert->execute([':uid'=>$row['user_id'], ':cid'=>$row['contest_id']]);

      slog("sent ok -> {$to} (contest_id={$row['contest_id']})");
    } catch (MailException $e) {
      slog("send FAIL -> {$to} (contest_id={$row['contest_id']}): ".$e->getMessage());
    }
  }

  slog("run complete: sent={$sent}");
  slog('--- send_vote_reminders: end ---');

} catch (Throwable $e) {
  slog('FATAL: '.$e->getMessage());
  exit(1);
}

