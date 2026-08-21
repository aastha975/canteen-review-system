<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_role(['student', 'faculty']);

$itemid = (int) ($_GET['itemid'] ?? $_POST['itemid'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare(
    'SELECT m.itemid, m.item_name, c.cname
     FROM menu_items m JOIN canteens c ON c.cid = m.cid
     WHERE m.itemid = :itemid'
);
$stmt->execute(['itemid' => $itemid]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    die('Menu item not found.');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $rating = (int) ($_POST['rating'] ?? 0);

    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5.';
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO dish_ratings (user_id, itemid, rating) VALUES (:uid, :itemid, :rating)'
        );
        $insert->execute([
            'uid'    => $_SESSION['user_id'],
            'itemid' => $itemid,
            'rating' => $rating,
        ]);
        $success = true;
    }
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
    <title>Rate <?= htmlspecialchars($item['item_name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Rate <?= htmlspecialchars($item['item_name']) ?> at <?= htmlspecialchars($item['cname']) ?></h1>
        <p><a href="menu.php?cid=<?= (int)($_GET['cid'] ?? '') ?>">← Back to menu</a></p>

        <?php if ($success): ?>
            <p class="success">Thanks! Your dish rating was submitted.</p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="rate_dish.php" class="form-stacked">
                <?= csrf_field() ?>
                <input type="hidden" name="itemid" value="<?= (int)$itemid ?>">
                <label for="rating">Rating (1–5)</label>
                <select id="rating" name="rating" required>
                    <option value="">Select</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit">Submit</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
