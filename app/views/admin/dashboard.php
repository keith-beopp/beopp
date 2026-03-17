<?php // app/views/admin/dashboard.php
include __DIR__ . '/_header.php'; ?>
<h1>Admin</h1>
<p class="muted">Hard delete is permanent. Deleting a contest deletes its entries and their votes (via FK cascade).</p>
<?php include __DIR__ . '/_footer.php';

