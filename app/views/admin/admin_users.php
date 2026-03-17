<?php
// app/views/admin/admin_users.php
// Local-only Users Admin (list/search/delete)

// Render admin header nav
require __DIR__ . '/_header.php';
?>
<style>
  .flash {background:#eef; padding:10px; border:1px solid #ccd; margin:10px 0; border-radius:6px;}
  .searchbar {margin: 15px 0;}
  table {width:100%; border-collapse: collapse; background:#fff;}
  th, td {padding:10px; border-bottom:1px solid #eee; text-align:left;}
  th {background:#f7f7f7; font-weight:600;}
  .pill {font-size:12px; padding:2px 7px; border-radius:999px; background:#eee; display:inline-block;}
  .pill.admin {background:#d4f4d0;}
  .btn {padding:6px 10px; border:1px solid #ccc; border-radius:6px; background:#fafafa; cursor:pointer;}
  .btn-danger {background:#ffefef; border-color:#f5caca; color:#a11;}
  .pager a, .pager span {display:inline-block; margin:2px 4px; padding:6px 10px; border:1px solid #ddd; border-radius:6px; text-decoration:none;}
  .pager .current {background:#222; color:#fff; border-color:#222;}
</style>

<h1>Users</h1>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<form class="searchbar" method="get" action="/admin/users">
  <input type="text" name="q" placeholder="Search email or sub…" value="<?= htmlspecialchars($q ?? '') ?>" />
  <button class="btn" type="submit">Search</button>
  <?php if (!empty($q)): ?>
    <a class="btn" href="/admin/users">Clear</a>
  <?php endif; ?>
</form>

<table>
  <thead>
    <tr>
      <th style="width:70px;">ID</th>
      <th>Email</th>
      <th>sub</th>
      <th style="width:130px;">Created</th>
      <th style="width:90px;">Role</th>
      <th style="width:120px;">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($users)): ?>
    <tr><td colspan="6">No users found.</td></tr>
  <?php else: ?>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
        <td><span class="pill"><?= htmlspecialchars($u['sub'] ?? '') ?></span></td>
        <td><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 16)) ?></td>
        <td>
          <?php if (!empty($u['is_admin'])): ?>
            <span class="pill admin">admin</span>
          <?php else: ?>
            <span class="pill">user</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === (int)$u['id']): ?>
            <span class="pill">you</span>
          <?php else: ?>
            <form method="post" action="/admin/users/delete"
                  onsubmit="return confirm('Delete this user? This cannot be undone.');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>

<?php if (!empty($pages) && $pages > 1): ?>
  <div class="pager" style="margin-top:12px;">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php if ($p == ($page ?? 1)): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="/admin/users?<?= http_build_query(['page' => $p, 'q' => $q ?? '']) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
<?php endif; ?>

