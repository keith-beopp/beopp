<?php
class VotingRules {
    /**
     * Return ['available'=>bool, 'cooldown'=>string, 'seconds'=>int]
     * One free vote per CONTEST per 24h (by user_id if logged in, else by IP).
     */
    public static function freeVoteStatus(PDO $db, int $contestId, ?int $userId, string $ip): array {
        $sql = "
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
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'contest_id' => $contestId,
            'uid'        => $userId,
            'ip'         => $ip,
        ]);
        $last = $stmt->fetchColumn();

        if (!$last) {
            return ['available' => true, 'cooldown' => '', 'seconds' => 0];
        }

        $elapsed   = time() - strtotime($last);
        $remaining = max(0, (24 * 3600) - $elapsed);
        $hrs  = floor($remaining / 3600);
        $mins = floor(($remaining % 3600) / 60);
        $cool = ($hrs > 0)
            ? "{$hrs} hours and {$mins} minutes"
            : "{$mins} minutes";

        return ['available' => false, 'cooldown' => $cool, 'seconds' => $remaining];
    }
}

