<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
start_secure_session();
require_role(['canteen_admin', 'super_admin']);

$pdo = get_db_connection();

// A canteen_admin always manages THEIR OWN canteen — we look this up from
// the DB ourselves rather than trusting a ?cid= query param, so there's
// no way to edit someone else's canteen by guessing/changing a URL.
// A super_admin can pick any canteen via ?cid=.
if ($_SESSION['role'] === 'canteen_admin') {
    $stmt = $pdo->prepare('SELECT cid, cname FROM canteens WHERE admin_user_id = :uid');
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $canteen = $stmt->fetch();

    if (!$canteen) {
        die('Your account is not assigned to manage any canteen. Contact the super admin.');
    }
    $cid = (int) $canteen['cid'];
} else {
    $cid = (int) ($_GET['cid'] ?? $_POST['cid'] ?? 0);
    $stmt = $pdo->prepare('SELECT cid, cname FROM canteens WHERE cid = :cid');
    $stmt->execute(['cid' => $cid]);
    $canteen = $stmt->fetch();

    if (!$canteen) {
        // Let a super_admin pick a canteen first if none specified
        $allCanteens = $pdo->query('SELECT cid, cname FROM canteens ORDER BY cname')->fetchAll();
        ?>
        <!DOCTYPE html><html><head><title>Choose a canteen</title>
        <link rel="stylesheet" href="../assets/style.css"></head><body>
        <main class="container">
            <h1>Choose a canteen to manage</h1>
            <ul>
                <?php foreach ($allCanteens as $c): ?>
                    <li><a href="menu.php?cid=<?= (int)$c['cid'] ?>"><?= htmlspecialchars($c['cname']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </main></body></html>
        <?php
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['item_name'] ?? '');
        $price = $_POST['price'] ?? '';

        if ($name === '' || !is_numeric($price) || (float)$price < 0) {
            $error = 'Please enter a valid item name and a non-negative price.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO menu_items (cid, item_name, price) VALUES (:cid, :name, :price)'
            );
            $stmt->execute(['cid' => $cid, 'name' => $name, 'price' => $price]);
            $success = 'Item added.';
        }

    } elseif ($action === 'update') {
        $itemid = (int) ($_POST['itemid'] ?? 0);
        $name   = trim($_POST['item_name'] ?? '');
        $price  = $_POST['price'] ?? '';
        $available = isset($_POST['is_available']) ? 'TRUE' : 'FALSE';

        if ($name === '' || !is_numeric($price) || (float)$price < 0) {
            $error = 'Please enter a valid item name and a non-negative price.';
        } else {
            // Ownership check: this item must belong to THIS canteen
            $stmt = $pdo->prepare(
                "UPDATE menu_items SET item_name = :name, price = :price, is_available = $available
                 WHERE itemid = :itemid AND cid = :cid"
            );
            $stmt->execute(['name' => $name, 'price' => $price, 'itemid' => $itemid, 'cid' => $cid]);

            if ($stmt->rowCount() === 0) {
                $error = 'Item not found in this canteen.';
            } else {
                $success = 'Item updated.';
            }
        }

    } elseif ($action === 'delete') {
        $itemid = (int) ($_POST['itemid'] ?? 0);

        // Same ownership check applies to delete
        $stmt = $pdo->prepare('DELETE FROM menu_items WHERE itemid = :itemid AND cid = :cid');
        $stmt->execute(['itemid' => $itemid, 'cid' => $cid]);

        $success = $stmt->rowCount() > 0 ? 'Item deleted.' : '';
        if ($stmt->rowCount() === 0) {
            $error = 'Item not found in this canteen.';
        }
    }
}

$stmt = $pdo->prepare('SELECT itemid, item_name, price, is_available, updated_at FROM menu_items WHERE cid = :cid ORDER BY item_name');
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
    <title>Manage <?= htmlspecialchars($canteen['cname']) ?> Menu</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Manage <?= htmlspecialchars($canteen['cname']) ?> Menu</h1>
        <p><a href="../dashboard.php">← Dashboard</a></p>

        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <h2>Current items</h2>
        <table>
            <thead>
                <tr><th>Item</th><th>Price (₹)</th><th>Available</th><th>Last updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <form method="POST" action="menu.php?cid=<?= (int)$cid ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cid" value="<?= (int)$cid ?>">
                            <input type="hidden" name="itemid" value="<?= (int)$item['itemid'] ?>">
                            <td><input type="text" name="item_name" value="<?= htmlspecialchars($item['item_name']) ?>" required></td>
                            <td><input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($item['price']) ?>" required></td>
                            <td><input type="checkbox" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>></td>
                            <td><?= htmlspecialchars($item['updated_at']) ?></td>
                            <td><button type="submit">Save</button></td>
                        </form>
                        <td>
                            <form method="POST" action="menu.php?cid=<?= (int)$cid ?>" onsubmit="return confirm('Delete this item?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="cid" value="<?= (int)$cid ?>">
                                <input type="hidden" name="itemid" value="<?= (int)$item['itemid'] ?>">
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6">No items yet — add one below.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>Add a new item</h2>
        <form method="POST" action="menu.php?cid=<?= (int)$cid ?>" class="form-stacked">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="cid" value="<?= (int)$cid ?>">
            <label for="item_name">Item name</label>
            <input type="text" id="item_name" name="item_name" required>
            <label for="price">Price (₹)</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required>
            <button type="submit">Add item</button>
        </form>
    </main>
</body>
</html>
