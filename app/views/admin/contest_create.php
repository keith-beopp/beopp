<?php
// app/views/admin/contest_create.php
include __DIR__ . '/_header.php';
require_once __DIR__ . '/../../../core/Csrf.php';
$csrf = Csrf::token();

$form = $form ?? [];
$val = fn($k,$d='') => htmlspecialchars($form[$k] ?? $d);
$sel = fn($k,$v)    => (($form[$k] ?? '') === $v) ? 'selected' : '';
$chk = fn($k)       => !empty($form[$k]) ? 'checked' : '';
?>
<h1>Create Contest</h1>

<?php if (!empty($errors)): ?>
  <div class="danger">
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" action="/admin/contest/create" style="display:grid; gap:10px; max-width:720px">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

  <label>Title* <br>
    <input type="text" name="title" value="<?= $val('title') ?>" required>
  </label>

  <label>Description <br>
    <textarea name="description" rows="4"><?= $val('description') ?></textarea>
  </label>

  <label>Niche <br>
    <input type="text" name="niche" value="<?= $val('niche') ?>">
  </label>

  <div style="display:flex; gap:12px">
    <label>Voting type<br>
      <select name="voting_type">
        <option value="free" <?= $sel('voting_type','free') ?>>free</option>
        <option value="paid" <?= $sel('voting_type','paid') ?>>paid</option>
        <option value="both" <?= $sel('voting_type','both') ?>>both</option>
      </select>
    </label>

    <label>Prize type<br>
      <select name="prize_type">
        <option value="none" <?= $sel('prize_type','none') ?>>none</option>
        <option value="sponsored" <?= $sel('prize_type','sponsored') ?>>sponsored</option>
        <option value="progressive" <?= $sel('prize_type','progressive') ?>>progressive</option>
      </select>
    </label>

    <label>Prize value<br>
      <input type="text" name="prize_value" value="<?= $val('prize_value') ?>">
    </label>
  </div>

  <div style="display:flex; gap:12px">
    <label>Start date<br>
      <input type="date" name="start_date" value="<?= $val('start_date') ?>">
    </label>
    <label>End date<br>
      <input type="date" name="end_date" value="<?= $val('end_date') ?>">
    </label>
  </div>

  <div style="display:flex; gap:12px">
    <label>Sponsor name<br>
      <input type="text" name="sponsor_name" value="<?= $val('sponsor_name') ?>">
    </label>
    <label>Sponsor URL<br>
      <input type="url" name="sponsor_url" value="<?= $val('sponsor_url') ?>">
    </label>
  </div>

  <label>Slug (optional; auto from title if blank)<br>
    <input type="text" name="slug" value="<?= $val('slug') ?>">
  </label>

  <label>
    <input type="checkbox" name="is_approved" value="1" <?= $chk('is_approved') ?>>
    Approve now
  </label>

  <div>
    <button class="btn" type="submit">Create contest</button>
    <a class="btn" href="/admin/contests">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/_footer.php';

