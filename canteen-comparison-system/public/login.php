<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

// Already logged in? Send them straight to their dashboard.
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (attempt_login($email, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Incorrect email or password.';
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
    <title>Login — Canteen Comparison System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="auth-container">
        <h1>Log in</h1>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="form-stacked">
            <?= csrf_field() ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Log in</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register here</a>.</p>
    </main>
</body>
</html>
