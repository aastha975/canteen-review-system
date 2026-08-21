<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
require_login();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <title>Dashboard — Canteen Comparison System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="dashboard-container">
        <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
        <p>Role: <?= htmlspecialchars($user['role']) ?></p>

        <?php if (in_array($user['role'], ['student', 'faculty'], true)): ?>
            <ul>
                <li><a href="canteens.php">Browse canteens</a></li>
                <li><a href="dish_compare.php">Compare a dish across canteens</a></li>
                <li><a href="compare.php">Compare canteens (TOPSIS ranking)</a></li>
                <li><a href="analytics.php">Analytics</a></li>
            </ul>
        <?php elseif ($user['role'] === 'canteen_admin'): ?>
            <ul>
                <li><a href="admin/menu.php">Manage my canteen's menu</a></li>
                <li><a href="analytics.php">Analytics</a></li>
            </ul>
        <?php elseif ($user['role'] === 'super_admin'): ?>
            <ul>
                <li><a href="admin/menu.php">Manage any canteen's menu</a></li>
                <li><a href="canteens.php">Browse canteens</a></li>
                <li><a href="analytics.php">Analytics</a></li>
            </ul>
        <?php endif; ?>

        <p><a href="logout.php">Log out</a></p>
    </main>
</body>
</html>
