<?php
// app/views/admin/contests.php
include __DIR__ . '/_header.php';
require_once __DIR__ . '/../../../core/Csrf.php';
$csrf = Csrf::token();
$q = trim($_GET['q'] ?? '');
?>
<h1>Contests</h1>

<form method="get" action="/admin/contests" style="margin-bottom:12px">
  <input type="text" name="q" placeholder="Search by title or id" value="<?= htmlspecialchars($q) ?>">
  <button class="btn" type="submit">Search</button>
</form>

<?php if (!empty($_GET['deleted'])): ?>
  <p class="muted">Contest deleted.</p>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Title</th>
      <th>Dates</th>
      <th>Entries</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!empty($contests)): ?>
    <?php foreach ($contests as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= htmlspecialchars($c['title'] ?? '') ?></td>
        <td><?= htmlspecialchars($c['start_date'] ?? '') ?> &rarr; <?= htmlspecialchars($c['end_date'] ?? '') ?></td>
        <td><?= (int)$c['entries_count'] ?></td>
        <td class="actions">
          <form method="post" action="/admin/contests/delete"
                onsubmit="return confirmDelete('PERMANENTLY delete contest #<?= (int)$c['id'] ?> and all related entries/votes?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn danger" type="submit">Delete permanently</button>
          </form>
          <a class="btn" href="/admin/entries?contest_id=<?= (int)$c['id'] ?>">View entries</a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
      <tr><td colspan="5" class="muted">No contests found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/_footer.php';

