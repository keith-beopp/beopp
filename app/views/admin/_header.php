<?php // app/views/admin/_header.php ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin</title>
  <style>
    body{font-family:sans-serif; margin:24px;}
    table{width:100%; border-collapse:collapse;}
    th,td{padding:8px; border-bottom:1px solid #ddd; text-align:left;}
    .actions{display:flex; gap:8px}
    form{display:inline}
    .danger{color:#b00020}
    .muted{color:#666}
    .topbar{display:flex; gap:12px; align-items:center; margin-bottom:16px}
    input[type=text], input[type=number]{padding:6px 8px;}
    .btn{padding:6px 10px; border:1px solid #ccc; background:#f8f8f8; cursor:pointer}
  </style>
  <script>
    function confirmDelete(msg){
      return confirm(msg || 'Are you sure? This cannot be undone.');
    }
  </script>
</head>
<body>
<div class="topbar">
  <a href="/admin">Dashboard</a>
  <a href="/admin/contests">Contests</a>
  <a href="/admin/entries">Entries</a>
  <a href="/admin/contest/create">Create Contest</a>
</div>

