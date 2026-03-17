<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div style="background: #f0f0f0; padding: 10px;">
    <?php if (!empty($_SESSION['user']['email'])): ?>
        Welcome, 
        <a href="/user">
            <?php echo htmlspecialchars($_SESSION['user']['email']); ?>
        </a>
        |
        <?php if (!empty($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']): ?>
            <a href="/admin">Admin</a> |
        <?php endif; ?>
        <a href="/auth/logout.php">Logout</a>
    <?php else: ?>
        <a href="https://us-west-1zqoknog9t.auth.us-west-1.amazoncognito.com/login?client_id=1jl0f5e01ujffgddl0jejco6d4&response_type=code&scope=email+openid&redirect_uri=https://www.beopp.com/auth/callback.php">
            Login
        </a>
    <?php endif; ?>
</div>

