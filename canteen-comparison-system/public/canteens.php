<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_login();

$pdo = get_db_connection();
$canteens = $pdo->query('SELECT cid, cname, location FROM canteens ORDER BY cname')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <title>Canteens — Canteen Comparison System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Canteens</h1>
        <p><a href="dashboard.php">← Dashboard</a> | <a href="dish_compare.php">Compare a specific dish</a> | <a href="compare.php">Compare canteens (TOPSIS)</a></p>

        <?php if (($_GET['checkin'] ?? '') === 'checked_in'): ?>
            <p class="success">Checked in!</p>
        <?php elseif (($_GET['checkin'] ?? '') === 'already_checked_in'): ?>
            <p class="error">You already checked in here recently.</p>
        <?php endif; ?>


        <ul class="canteen-list">
            <?php foreach ($canteens as $c): ?>
                <li>
                    <h2><?= htmlspecialchars($c['cname']) ?></h2>
                    <p><?= htmlspecialchars($c['location'] ?? '') ?></p>
                    <a href="menu.php?cid=<?= (int)$c['cid'] ?>">View menu</a> |
                    <a href="rate.php?cid=<?= (int)$c['cid'] ?>">Rate this canteen</a> |
                    <a href="feedback.php?cid=<?= (int)$c['cid'] ?>">Feedback</a> |
                    <form method="POST" action="checkin.php" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="cid" value="<?= (int)$c['cid'] ?>">
                        <button type="submit">I'm here now</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </main>
</body>
</html>
