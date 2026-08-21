<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/topsis.php';
start_secure_session();
require_login();

$pdo = get_db_connection();

$profile = $_GET['profile'] ?? 'overall';
if (!array_key_exists($profile, WEIGHT_PROFILES) && $profile !== 'custom') {
    $profile = 'overall';
}

if ($profile === 'custom') {
    $rawWeights = [];
    foreach (TOPSIS_CRITERIA as $c) {
        $rawWeights[$c] = $_GET[$c] ?? '';
    }
    // Only treat it as a real custom submission if at least one weight was given
    $hasCustomInput = array_filter($rawWeights, fn($v) => $v !== '');
    $weights = $hasCustomInput
        ? normalize_custom_weights($rawWeights)
        : WEIGHT_PROFILES['overall'];
} else {
    $weights = WEIGHT_PROFILES[$profile];
}

$minRatings = 5; // threshold used by the Bayesian adjustment (see topsis.php)
$ranking = get_topsis_ranking($pdo, $weights, $minRatings);

// Also pull the raw averages so we can show the underlying numbers —
// this is what makes the ranking explainable/defensible in a viva.
$rawAverages = $pdo->query(
    'SELECT cid, cname, avg_price_rating, avg_quality_rating,
            avg_cleanliness_rating, avg_speed_rating, avg_hygiene_rating, total_ratings
     FROM canteen_criteria_avg'
)->fetchAll(PDO::FETCH_ASSOC);
$rawAveragesByCid = [];
foreach ($rawAverages as $r) {
    $rawAveragesByCid[$r['cid']] = $r;
}

// And the Bayesian-adjusted numbers, so the raw-vs-adjusted comparison
// table can show exactly how much each canteen's score shifted.
$bayesStmt = $pdo->prepare('SELECT cid, cname, bayes_price, bayes_quality, bayes_cleanliness, bayes_speed, bayes_hygiene, total_ratings FROM canteen_criteria_bayesian(:m)');
$bayesStmt->execute(['m' => $minRatings]);
$bayesAveragesByCid = [];
foreach ($bayesStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $bayesAveragesByCid[$r['cid']] = $r;
}

$criteriaLabels = [
    'avg_price_rating'       => 'Price',
    'avg_quality_rating'     => 'Quality',
    'avg_cleanliness_rating' => 'Cleanliness',
    'avg_speed_rating'       => 'Speed',
    'avg_hygiene_rating'     => 'Hygiene',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <title>Compare Canteens — TOPSIS Ranking</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container">
        <h1>Compare Canteens</h1>
        <p><a href="canteens.php">← All canteens</a></p>
        <p>Ranked using <strong>TOPSIS</strong>, a multi-criteria decision-making
           algorithm — it finds which canteen is closest to an "ideal" canteen
           (best on every criterion) and farthest from a "worst-case" one.</p>

        <p><em>Note: the ranking below uses <strong>Bayesian-adjusted</strong> averages,
           not raw averages. A canteen with very few ratings has its score pulled
           toward the overall average instead of being fully trusted — this
           corrects for sampling bias (e.g. a canteen that fewer students happened
           to rate). See the comparison table further down.</em></p>

        <form method="GET" action="compare.php">
            <label for="profile">What are you looking for?</label>
            <select id="profile" name="profile" onchange="this.form.submit()">
                <?php foreach (PROFILE_LABELS as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $key === $profile ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($profile === 'custom'): ?>
            <form method="GET" action="compare.php">
                <input type="hidden" name="profile" value="custom">
                <p>Set how much each criterion matters to you (any positive numbers — they'll be scaled to add up to 100%):</p>
                <?php foreach ($criteriaLabels as $key => $label): ?>
                    <label for="<?= $key ?>"><?= $label ?></label>
                    <input type="number" id="<?= $key ?>" name="<?= $key ?>" min="0" step="0.1"
                           value="<?= htmlspecialchars($_GET[$key] ?? '') ?>">
                <?php endforeach; ?>
                <button type="submit">Recalculate</button>
            </form>
        <?php endif; ?>

        <h2>Ranking — <?= htmlspecialchars(PROFILE_LABELS[$profile]) ?></h2>

        <?php if (empty($ranking)): ?>
            <p>No canteens have ratings yet — check back once students start rating.</p>
        <?php else: ?>
            <div class="token-row">
                <?php foreach ($ranking as $cid => $r): ?>
                    <div class="token-stub rank-<?= min($r['rank'], 3) ?>">
                        <div class="rank">No. <?= str_pad($r['rank'], 2, '0', STR_PAD_LEFT) ?></div>
                        <div class="cname"><?= htmlspecialchars($r['cname']) ?></div>
                        <div class="score">Score: <?= number_format($r['score'], 4) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2>Underlying average ratings (out of 5)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Canteen</th>
                        <?php foreach ($criteriaLabels as $label): ?><th><?= $label ?></th><?php endforeach; ?>
                        <th># ratings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $cid => $r): $raw = $rawAveragesByCid[$cid]; ?>
                        <tr>
                            <td><?= htmlspecialchars($raw['cname']) ?></td>
                            <?php foreach (array_keys($criteriaLabels) as $key): ?>
                                <td><?= htmlspecialchars($raw[$key]) ?></td>
                            <?php endforeach; ?>
                            <td><?= (int)$raw['total_ratings'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Raw vs. Bayesian-adjusted averages</h2>
            <p>Below <?= (int)$minRatings ?> ratings, a canteen's score is pulled toward the
               overall average rather than fully trusted. Watch how much a canteen's
               numbers shift — a bigger shift means that canteen's raw average was
               resting on a thinner (less trustworthy) sample.</p>
            <table>
                <thead>
                    <tr>
                        <th>Canteen</th>
                        <th># ratings</th>
                        <?php foreach ($criteriaLabels as $label): ?><th><?= $label ?>: raw → adjusted</th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $cid => $r): $raw = $rawAveragesByCid[$cid]; $bayes = $bayesAveragesByCid[$cid]; ?>
                        <tr>
                            <td><?= htmlspecialchars($raw['cname']) ?></td>
                            <td><?= (int)$raw['total_ratings'] ?></td>
                            <?php foreach (array_keys($criteriaLabels) as $key): ?>
                                <?php $bayesKey = str_replace('avg_', 'bayes_', str_replace('_rating', '', $key)); ?>
                                <td><?= htmlspecialchars($raw[$key]) ?> → <?= htmlspecialchars($bayes[$bayesKey]) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
