<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
start_secure_session();
require_login();

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

// Only students/faculty can post; admins are blocked at the DB level too
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!in_array($_SESSION['role'], ['student', 'faculty'], true)) {
        $error = 'Only students and faculty can post feedback.';
    } else {
        $comment = trim($_POST['comment_text'] ?? '');

        if ($comment === '') {
            $error = 'Feedback cannot be empty.';
        } elseif (strlen($comment) > 2000) {
            $error = 'Feedback is too long (max 2000 characters).';
        } else {
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO feedback (user_id, cid, comment_text) VALUES (:uid, :cid, :comment)'
                );
                $insert->execute([
                    'uid'     => $_SESSION['user_id'],
                    'cid'     => $cid,
                    'comment' => $comment,
                ]);
                $success = true;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'cannot post feedback on their own canteen')
                    ? 'Canteen admins cannot post feedback on their own canteen.'
                    : 'Could not submit feedback. Please try again.';
            }
        }
    }
}

// Sort feedback by net votes (likes - dislikes) so the most useful rises up,
// with newest first as a tiebreaker
$stmt = $pdo->prepare(
    'SELECT feedback_id, user_name, user_role, comment_text, created_at, likes, dislikes
     FROM feedback_with_votes
     WHERE cid = :cid
     ORDER BY (likes - dislikes) DESC, created_at DESC'
);
$stmt->execute(['cid' => $cid]);
$feedbackList = $stmt->fetchAll();

// Which feedback items has the current user already voted on, and how —
// used to show "you liked this" instead of letting them vote twice
$stmt = $pdo->prepare(
    'SELECT feedback_id, vote_type FROM votes WHERE user_id = :uid
     AND feedback_id IN (SELECT feedback_id FROM feedback WHERE cid = :cid)'
);
$stmt->execute(['uid' => $_SESSION['user_id'], 'cid' => $cid]);
$myVotes = [];
foreach ($stmt->fetchAll() as $row) {
    $myVotes[$row['feedback_id']] = (int) $row['vote_type'];
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
    <title>Feedback — <?= htmlspecialchars($canteen['cname']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Feedback — <?= htmlspecialchars($canteen['cname']) ?></h1>
        <p><a href="canteens.php">← All canteens</a></p>

        <?php if ($success): ?>
            <p class="success">Your feedback was posted.</p>
        <?php elseif ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['student', 'faculty'], true)): ?>
            <form method="POST" action="feedback.php" class="form-stacked">
                <?= csrf_field() ?>
                <input type="hidden" name="cid" value="<?= (int)$cid ?>">
                <label for="comment_text">Share your feedback</label>
                <textarea id="comment_text" name="comment_text" rows="3" required maxlength="2000"></textarea>
                <button type="submit">Post feedback</button>
            </form>
        <?php endif; ?>

        <h2>All feedback</h2>
        <?php if (empty($feedbackList)): ?>
            <p>No feedback yet — be the first!</p>
        <?php endif; ?>

        <?php foreach ($feedbackList as $f): ?>
            <article class="feedback-item">
                <p><strong><?= htmlspecialchars($f['user_name']) ?></strong>
                   (<?= htmlspecialchars($f['user_role']) ?>) —
                   <?= htmlspecialchars($f['created_at']) ?></p>
                <p><?= nl2br(htmlspecialchars($f['comment_text'])) ?></p>
                <p>
                    👍 <?= (int)$f['likes'] ?> &nbsp; 👎 <?= (int)$f['dislikes'] ?>
                    <?php $myVote = $myVotes[$f['feedback_id']] ?? null; ?>

                    <form method="POST" action="vote.php" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="feedback_id" value="<?= (int)$f['feedback_id'] ?>">
                        <input type="hidden" name="cid" value="<?= (int)$cid ?>">
                        <input type="hidden" name="vote_type" value="1">
                        <button type="submit" <?= $myVote === 1 ? 'disabled' : '' ?>>
                            <?= $myVote === 1 ? 'Liked' : 'Like' ?>
                        </button>
                    </form>

                    <form method="POST" action="vote.php" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="feedback_id" value="<?= (int)$f['feedback_id'] ?>">
                        <input type="hidden" name="cid" value="<?= (int)$cid ?>">
                        <input type="hidden" name="vote_type" value="-1">
                        <button type="submit" <?= $myVote === -1 ? 'disabled' : '' ?>>
                            <?= $myVote === -1 ? 'Disliked' : 'Dislike' ?>
                        </button>
                    </form>
                </p>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>
