<?php
// app/views/admin/entries.php
include __DIR__ . '/_header.php';
require_once __DIR__ . '/../../../core/Csrf.php';
$csrf = Csrf::token();
$q = trim($_GET['q'] ?? '');
$contestId = (int)($_GET['contest_id'] ?? 0);
?>
<h1>Entries</h1>

<form method="get" action="/admin/entries" style="margin-bottom:12px">
  <input type="text" name="q" placeholder="Search by Entry ID or Contest title" value="<?= htmlspecialchars($q) ?>">
  <input type="number" min="1" name="contest_id" placeholder="Contest ID" value="<?= $contestId ?: '' ?>">
  <button class="btn" type="submit">Search</button>
</form>

<?php if (!empty($_GET['approved'])): ?>
  <p class="muted">Entry approved.</p>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
  <p class="muted">Entry deleted.</p>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Label</th>
      <th>Contest</th>
      <th>User</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!empty($entries)): ?>
    <?php foreach ($entries as $e): ?>
      <?php $approved = (int)($e['is_approved'] ?? 0) === 1; ?>
      <tr>
        <td><?= (int)$e['id'] ?></td>
        <td>
          <?= htmlspecialchars($e['entry_label'] ?? ('Entry #' . (int)$e['id'])) ?>
          <?php if ($approved): ?>
            <span class="muted" style="border:1px solid #9acd9a; padding:2px 6px; border-radius:12px; margin-left:6px; font-size:12px;">Approved</span>
          <?php endif; ?>
        </td>
        <td>#<?= (int)$e['contest_id'] ?> &mdash; <?= htmlspecialchars($e['contest_title'] ?? '') ?></td>
        <td><?= isset($e['user_id']) ? (int)$e['user_id'] : 0 ?></td>
        <td class="actions">
          <?php if (!$approved): ?>
            <!-- Approve button only if not approved -->
            <form method="post" action="/admin/entry/<?= (int)$e['id'] ?>/approve"
                  onsubmit="return confirmDelete('Approve entry #<?= (int)$e['id'] ?>?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
              <button class="btn" type="submit">Approve</button>
            </form>
          <?php endif; ?>

          <!-- Delete -->
          <form method="post" action="/admin/entries/delete"
                onsubmit="return confirmDelete('PERMANENTLY delete entry #<?= (int)$e['id'] ?> and its votes?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <button class="btn danger" type="submit">Delete permanently</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
      <tr><td colspan="5" class="muted">No entries found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/_footer.php';

