<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_login();

$pdo = get_db_connection();

// Populate the dropdown with every distinct item name across all canteens
$itemNames = $pdo->query('SELECT DISTINCT item_name FROM menu_items ORDER BY item_name')->fetchAll(PDO::FETCH_COLUMN);

$selectedItem = $_GET['item'] ?? '';
$results = [];

if ($selectedItem !== '') {
    $sortBy = ($_GET['sort'] ?? 'rating') === 'value' ? 'value_score' : 'avg_rating';

    $stmt = $pdo->prepare(
        "SELECT cname, price, avg_rating, total_ratings, value_score
         FROM dish_ratings_by_canteen
         WHERE item_name = :item
         ORDER BY $sortBy DESC NULLS LAST"
    );
    $stmt->execute(['item' => $selectedItem]);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <title>Compare a dish across canteens</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Compare a dish across canteens</h1>
        <p><a href="canteens.php">← All canteens</a></p>

        <form method="GET" action="dish_compare.php" class="form-stacked">
            <label for="item">Dish</label>
            <select id="item" name="item" required>
                <option value="">Select a dish</option>
                <?php foreach ($itemNames as $name): ?>
                    <option value="<?= htmlspecialchars($name) ?>" <?= $name === $selectedItem ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="sort">Sort by</label>
            <select id="sort" name="sort">
                <option value="rating" <?= ($_GET['sort'] ?? '') !== 'value' ? 'selected' : '' ?>>Highest rated</option>
                <option value="value" <?= ($_GET['sort'] ?? '') === 'value' ? 'selected' : '' ?>>Best value (rating per ₹)</option>
            </select>

            <button type="submit">Compare</button>
        </form>

        <?php if ($selectedItem !== ''): ?>
            <h2><?= htmlspecialchars($selectedItem) ?> — across canteens</h2>
            <?php if (empty($results)): ?>
                <p>No canteens currently sell this item.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Canteen</th><th>Price (₹)</th><th>Avg rating</th><th># ratings</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['cname']) ?></td>
                                <td><?= htmlspecialchars($r['price']) ?></td>
                                <td><?= $r['avg_rating'] !== null ? htmlspecialchars($r['avg_rating']) : 'No ratings yet' ?></td>
                                <td><?= (int)$r['total_ratings'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
