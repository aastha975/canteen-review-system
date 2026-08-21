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

$feedbackId = (int) ($_POST['feedback_id'] ?? 0);
$cid        = (int) ($_POST['cid'] ?? 0);
$voteType   = (int) ($_POST['vote_type'] ?? 0);

if (!in_array($voteType, [1, -1], true) || $feedbackId <= 0) {
    http_response_code(400);
    die('Invalid vote.');
}

$pdo = get_db_connection();

// Upsert: if this user already voted on this feedback, switch their vote
// instead of creating a duplicate row (the UNIQUE(feedback_id, user_id)
// constraint in the schema is what makes this safe).
$stmt = $pdo->prepare(
    'INSERT INTO votes (feedback_id, user_id, vote_type)
     VALUES (:fid, :uid, :vtype)
     ON CONFLICT (feedback_id, user_id)
     DO UPDATE SET vote_type = EXCLUDED.vote_type, created_at = NOW()'
);
$stmt->execute([
    'fid'   => $feedbackId,
    'uid'   => $_SESSION['user_id'],
    'vtype' => $voteType,
]);

header('Location: /feedback.php?cid=' . $cid);
exit;
