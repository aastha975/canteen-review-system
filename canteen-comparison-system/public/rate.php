<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_role(['student', 'faculty']); // admins are blocked from rating at the DB level too

$cid = (int) ($_GET['cid'] ?? $_POST['cid'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT cid, cname FROM canteens WHERE cid = :cid');
$stmt->execute(['cid' => $cid]);
$canteen = $stmt->fetch();

if (!$canteen) {
    http_response_code(404);
    die('Canteen not found.');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $criteria = ['price_rating', 'quality_rating', 'cleanliness_rating', 'speed_rating', 'hygiene_rating'];
    $values = [];
    $valid = true;

    foreach ($criteria as $key) {
        $val = (int) ($_POST[$key] ?? 0);
        if ($val < 1 || $val > 5) {
            $valid = false;
        }
        $values[$key] = $val;
    }

    if (!$valid) {
        $error = 'Please give a rating from 1 to 5 for every criterion.';
    } else {
        try {
            $insert = $pdo->prepare(
                'INSERT INTO criteria_ratings
                 (user_id, cid, price_rating, quality_rating, cleanliness_rating, speed_rating, hygiene_rating)
                 VALUES (:uid, :cid, :price, :quality, :clean, :speed, :hygiene)'
            );
            $insert->execute([
                'uid'     => $_SESSION['user_id'],
                'cid'     => $cid,
                'price'   => $values['price_rating'],
                'quality' => $values['quality_rating'],
                'clean'   => $values['cleanliness_rating'],
                'speed'   => $values['speed_rating'],
                'hygiene' => $values['hygiene_rating'],
            ]);
            $success = true;
        } catch (PDOException $e) {
            // Covers the DB trigger blocking admin self-rating, and any
            // other constraint violation, without leaking raw SQL errors.
            $error = 'Could not submit rating. ' .
                     (str_contains($e->getMessage(), 'cannot rate their own canteen')
                        ? 'Canteen admins cannot rate their own canteen.'
                        : 'Please check your input and try again.');
        }
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
    <title>Rate <?= htmlspecialchars($canteen['cname']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Rate <?= htmlspecialchars($canteen['cname']) ?></h1>
        <p><a href="canteens.php">← All canteens</a></p>

        <?php if ($success): ?>
            <p class="success">Thanks! Your rating was submitted.</p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="rate.php" class="form-stacked">
                <?= csrf_field() ?>
                <input type="hidden" name="cid" value="<?= (int)$cid ?>">

                <?php
                $labels = [
                    'price_rating'       => 'Price',
                    'quality_rating'     => 'Food quality',
                    'cleanliness_rating' => 'Cleanliness',
                    'speed_rating'       => 'Speed of service',
                    'hygiene_rating'     => 'Hygiene',
                ];
                foreach ($labels as $key => $label):
                ?>
                    <label for="<?= $key ?>"><?= $label ?> (1–5)</label>
                    <select id="<?= $key ?>" name="<?= $key ?>" required>
                        <option value="">Select</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                <?php endforeach; ?>

                <button type="submit">Submit rating</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
