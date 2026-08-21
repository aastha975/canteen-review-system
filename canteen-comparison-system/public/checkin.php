<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /canteens.php');
    exit;
}

require_csrf();

$cid = (int) ($_POST['cid'] ?? 0);
$pdo = get_db_connection();

$message = '';

try {
    $stmt = $pdo->prepare('INSERT INTO checkins (user_id, cid) VALUES (:uid, :cid)');
    $stmt->execute(['uid' => $_SESSION['user_id'], 'cid' => $cid]);
    $message = 'checked_in';
} catch (PDOException $e) {
    // The 15-minute anti-spam trigger fires here — treat it as a soft
    // no-op from the user's perspective, not a hard error page.
    $message = 'already_checked_in';
}

header('Location: /canteens.php?checkin=' . $message);
exit;
