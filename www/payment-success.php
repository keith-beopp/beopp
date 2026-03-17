<?php
session_start();

// Auto-redirect to contest page if contest_id is present
if (!empty($_GET['contest_id'])) {
    header('Location: /contest/' . urlencode($_GET['contest_id']));
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Thank you for your vote</title>
</head>
<body>
    <h1>✅ Thank you!</h1>
    <p>Your payment was successful and your vote will be counted.</p>
    <p><a href="/">Return to homepage</a></p>
</body>
</html>

