<?php include __DIR__ . '/_header.php'; ?>


    <h1>Unapproved Contests</h1>
    <?php if (empty($contests)): ?>
        <p>No unapproved contests.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($contests as $contest): ?>
                <li>
                    <strong><?php echo htmlspecialchars($contest['title']); ?></strong><br>
                    <a href="/contest/<?php echo $contest['id']; ?>">View</a> |
                    <a href="/admin/contest/<?php echo $contest['id']; ?>/approve">Approve</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>

