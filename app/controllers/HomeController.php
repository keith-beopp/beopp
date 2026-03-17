<?php
require_once __DIR__ . '/../../core/Database.php';

class HomeController {
    public static function index() {
        $config = require __DIR__ . '/../../config/config.php';
        $db = Database::connect($config['db']);

        $today = date('Y-m-d');

        // Current contests: approved, today is between start and end
        $stmt = $db->prepare("SELECT * FROM contests WHERE is_approved = 1 AND start_date <= ? AND end_date >= ? ORDER BY start_date DESC");
        $stmt->execute([$today, $today]);
        $currentContests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Upcoming contests: start date in future
        $stmt = $db->prepare("SELECT * FROM contests WHERE is_approved = 1 AND start_date > ? ORDER BY start_date ASC");
        $stmt->execute([$today]);
        $upcomingContests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Past contests: end date in the past
        $stmt = $db->prepare("SELECT * FROM contests WHERE is_approved = 1 AND end_date < ? ORDER BY end_date DESC");
        $stmt->execute([$today]);
        $pastContests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../views/home/index.php';
    }
}

