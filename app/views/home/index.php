<!DOCTYPE html>
<html>
<head>
    <title>Beopp Contests</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        h1, h2 {
            color: #333;
        }

        .contest-list {
            margin-bottom: 40px;
        }

        .contest-card {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        .contest-card a {
            font-weight: bold;
            font-size: 1.1em;
            text-decoration: none;
            color: #0066cc;
        }

        .contest-card small {
            color: #777;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>


    <h1>Current Contests</h1>
    <div class="contest-list">
        <?php if (empty($currentContests)): ?>
            <p>No current contests.</p>
        <?php else: ?>
            <?php foreach ($currentContests as $contest): ?>
                <div class="contest-card">
<a href="/contest/<?= htmlspecialchars($contest['slug']) ?>">

                        <?php echo htmlspecialchars($contest['title']); ?>
                    </a><br>
                    <small>Ends: <?php echo $contest['end_date']; ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2>Upcoming Contests</h2>
    <div class="contest-list">
        <?php if (empty($upcomingContests)): ?>
            <p>No upcoming contests.</p>
        <?php else: ?>
            <?php foreach ($upcomingContests as $contest): ?>
                <div class="contest-card">
<a href="/contest/<?= htmlspecialchars($contest['slug']) ?>">

                        <?php echo htmlspecialchars($contest['title']); ?>
                    </a><br>
                    <small>Starts: <?php echo $contest['start_date']; ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2>Past Contests</h2>
    <div class="contest-list">
        <?php if (empty($pastContests)): ?>
            <p>No past contests yet.</p>
        <?php else: ?>
            <?php foreach ($pastContests as $contest): ?>
                <div class="contest-card">
<a href="/contest/<?= htmlspecialchars($contest['slug']) ?>">

		    <?php echo htmlspecialchars($contest['title']); ?>
                    </a><br>
                    <small>Ended: <?php echo $contest['end_date']; ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>

