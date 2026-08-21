<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_login();

$cid = (int) ($_GET['cid'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT cid, cname, location FROM canteens WHERE cid = :cid');
$stmt->execute(['cid' => $cid]);
$canteen = $stmt->fetch();

if (!$canteen) {
    http_response_code(404);
    die('Canteen not found.');
}

$stmt = $pdo->prepare(
    'SELECT itemid, item_name, price FROM menu_items
     WHERE cid = :cid AND is_available = TRUE
     ORDER BY item_name'
);
$stmt->execute(['cid' => $cid]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <title><?= htmlspecialchars($canteen['cname']) ?> Menu</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1><?= htmlspecialchars($canteen['cname']) ?> — Menu</h1>
        <p><a href="canteens.php">← All canteens</a></p>

        <table>
            <thead>
                <tr><th>Item</th><th>Price (₹)</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['price']) ?></td>
                        <td>
                            <a href="rate_dish.php?itemid=<?= (int)$item['itemid'] ?>">Rate this dish</a> |
                            <a href="dish_compare.php?item=<?= urlencode($item['item_name']) ?>">Compare across canteens</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="3">No menu items yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
