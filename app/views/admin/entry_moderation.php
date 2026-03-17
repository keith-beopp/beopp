<?php include __DIR__ . '/_header.php'; ?>
    <h1>Unapproved Entries</h1>
    <?php if (empty($entries)): ?>
        <p>No unapproved entries.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($entries as $entry): ?>
                <li>
                    <strong><?php echo htmlspecialchars($entry['name']); ?></strong>
                    in "<em><?php echo htmlspecialchars($entry['contest_title']); ?></em>"<br>
                    <a href="/contest/<?php echo $entry['contest_id']; ?>">View Contest</a> |
                    <a href="/admin/entry/<?php echo $entry['id']; ?>/approve">Approve</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>

