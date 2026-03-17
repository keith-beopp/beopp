<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <?php
  // Normalize variables to avoid notices / accidental echoes
  $pageTitle    = isset($pageTitle)    ? $pageTitle    : 'Beopp';
  $canonicalUrl = isset($canonicalUrl) ? $canonicalUrl : '';
  $meta         = (isset($meta) && is_array($meta)) ? $meta : [];
  $headExtra    = isset($headExtra)    ? $headExtra    : '';
  ?>

  <title><?= htmlspecialchars($pageTitle) ?></title>

  <?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
  <?php endif; ?>

 <?php foreach ($meta as $name => $metaContent): ?>
  <?php if (strpos($name, 'og:') === 0): ?>
    <meta property="<?= htmlspecialchars($name) ?>" content="<?= htmlspecialchars($metaContent) ?>">
  <?php else: ?>
    <meta name="<?= htmlspecialchars($name) ?>" content="<?= htmlspecialchars($metaContent) ?>">
  <?php endif; ?>
<?php endforeach; ?>
 

  <!-- Global stylesheet -->
  <link rel="stylesheet" href="/assets/style.css">

  <!-- ShareThis -->
  <script src="https://platform-api.sharethis.com/js/sharethis.js#property=68cc4680a2b5394c24554d8d&product=inline-share-buttons" async></script>

  <!-- Optional per-page head HTML -->
  <?= $headExtra ?>
</head>
<body>
  <?= isset($content) ? $content : '' ?>
</body>
</html>

