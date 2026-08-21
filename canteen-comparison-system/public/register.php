<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name       = $_POST['name'] ?? '';
    $email      = $_POST['email'] ?? '';
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? '';
    $department = trim($_POST['department'] ?? '') ?: null;

    try {
        register_user($name, $email, $password, $role, $department);
        $success = true;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
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
    <title>Register — Canteen Comparison System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="auth-container">
        <h1>Create an account</h1>

        <?php if ($success): ?>
            <p class="success">Account created! You can now <a href="login.php">log in</a>.</p>
        <?php else: ?>

            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="register.php" class="form-stacked">
                <?= csrf_field() ?>
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                <label for="password">Password (min 8 characters)</label>
                <input type="password" id="password" name="password" required minlength="8">

                <label for="role">I am a</label>
                <select id="role" name="role" required>
                    <option value="student" <?= (($_POST['role'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
                    <option value="faculty" <?= (($_POST['role'] ?? '') === 'faculty') ? 'selected' : '' ?>>Faculty</option>
                </select>

                <label for="department">Department</label>
                <input type="text" id="department" name="department"
                       value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">

                <button type="submit">Register</button>
            </form>
        <?php endif; ?>

        <p>Already have an account? <a href="login.php">Log in</a>.</p>
    </main>
</body>
</html>
