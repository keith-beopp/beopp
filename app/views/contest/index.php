<!DOCTYPE html>
<html>
<head>
    <title>All Contests</title>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>


    <h1>Contests</h1>
    <?php if (empty($contests)): ?>
        <p>No contests found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($contests as $contest): ?>
                <li>
		    <a href="/contest/<?php echo $contest['id']; ?>">
                        <?php echo htmlspecialchars($contest['title']); ?>
                    </a>
                    <br>
                    <small><?php echo htmlspecialchars($contest['niche']); ?> | Ends: <?php echo $contest['end_date']; ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>

