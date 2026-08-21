<?php
// ============================================================
// topsis.php
// TOPSIS (Technique for Order of Preference by Similarity to
// Ideal Solution) — ranks canteens using the 5 rated criteria:
// price, quality, cleanliness, speed, hygiene.
//
// All 5 criteria here are "benefit" criteria (higher rating =
// better), since they're satisfaction scores out of 5, not raw
// cost — so there's no cost/benefit split to worry about, unlike
// textbook TOPSIS examples that mix ₹ price with ratings.
// ============================================================

require_once __DIR__ . '/db_connect.php';

const TOPSIS_CRITERIA = [
    'avg_price_rating',
    'avg_quality_rating',
    'avg_cleanliness_rating',
    'avg_speed_rating',
    'avg_hygiene_rating',
];

// Preset weight profiles for different "why am I asking" purposes.
// Each set of weights sums to 1.0.
const WEIGHT_PROFILES = [
    'overall' => [
        'avg_price_rating'       => 0.20,
        'avg_quality_rating'     => 0.20,
        'avg_cleanliness_rating' => 0.20,
        'avg_speed_rating'       => 0.20,
        'avg_hygiene_rating'     => 0.20,
    ],
    'hangout' => [
        'avg_price_rating'       => 0.10,
        'avg_quality_rating'     => 0.20,
        'avg_cleanliness_rating' => 0.35,
        'avg_speed_rating'       => 0.10,
        'avg_hygiene_rating'     => 0.25,
    ],
    'quick_bite' => [
        'avg_price_rating'       => 0.15,
        'avg_quality_rating'     => 0.15,
        'avg_cleanliness_rating' => 0.10,
        'avg_speed_rating'       => 0.50,
        'avg_hygiene_rating'     => 0.10,
    ],
    'budget' => [
        'avg_price_rating'       => 0.55,
        'avg_quality_rating'     => 0.15,
        'avg_cleanliness_rating' => 0.10,
        'avg_speed_rating'       => 0.10,
        'avg_hygiene_rating'     => 0.10,
    ],
];

const PROFILE_LABELS = [
    'overall'    => 'Overall best (balanced)',
    'hangout'    => 'Hangout (cleanliness & hygiene matter most)',
    'quick_bite' => 'Quick bite between classes (speed matters most)',
    'budget'     => 'Budget-conscious (price matters most)',
    'custom'     => 'Custom weights',
];

/**
 * Runs TOPSIS over a set of canteen rows.
 *
 * @param array $rows    keyed by cid => ['cname' => ..., criterion => value, ...]
 * @param array $weights keyed by criterion name => weight (should sum to ~1.0)
 * @return array cid => ['cname'=>, 'score'=>, 'rank'=>] sorted best-first
 */
function calculate_topsis(array $rows, array $weights): array {
    if (empty($rows)) {
        return [];
    }

    $criteria = array_keys($weights);

    // Step 1: vector-normalize each criterion column
    // (divide each value by the square root of the sum of squares of that column)
    $sumSquares = array_fill_keys($criteria, 0.0);
    foreach ($rows as $row) {
        foreach ($criteria as $c) {
            $val = (float) ($row[$c] ?? 0);
            $sumSquares[$c] += $val * $val;
        }
    }

    // Step 2: apply weights to the normalized values
    $weighted = [];
    foreach ($rows as $cid => $row) {
        foreach ($criteria as $c) {
            $val = (float) ($row[$c] ?? 0);
            $denom = sqrt($sumSquares[$c]);
            $normalized = $denom > 0 ? $val / $denom : 0.0;
            $weighted[$cid][$c] = $normalized * $weights[$c];
        }
    }

    // Step 3: find the ideal-best and ideal-worst vectors
    // (all 5 criteria are "benefit" type here, so best = max, worst = min)
    $idealBest = [];
    $idealWorst = [];
    foreach ($criteria as $c) {
        $col = array_column($weighted, $c);
        $idealBest[$c]  = max($col);
        $idealWorst[$c] = min($col);
    }

    // Step 4 + 5: Euclidean distance from ideal-best and ideal-worst,
    // then closeness score = distance_from_worst / (distance_from_best + distance_from_worst)
    $scored = [];
    foreach ($weighted as $cid => $vals) {
        $distBest = 0.0;
        $distWorst = 0.0;
        foreach ($criteria as $c) {
            $distBest  += ($vals[$c] - $idealBest[$c]) ** 2;
            $distWorst += ($vals[$c] - $idealWorst[$c]) ** 2;
        }
        $distBest  = sqrt($distBest);
        $distWorst = sqrt($distWorst);

        $denom = $distBest + $distWorst;
        $score = $denom > 0 ? $distWorst / $denom : 0.0;

        $scored[$cid] = [
            'cname' => $rows[$cid]['cname'],
            'score' => $score,
        ];
    }

    // Step 6: rank best (highest score) first
    uasort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    $rank = 1;
    foreach ($scored as $cid => &$entry) {
        $entry['rank'] = $rank++;
    }
    unset($entry);

    return $scored;
}

/**
 * Pulls BAYESIAN-ADJUSTED criteria averages from the DB and runs TOPSIS.
 * Using canteen_criteria_bayesian() instead of the raw
 * canteen_criteria_avg view corrects for sampling bias — a canteen
 * with few ratings (e.g. one under-reached in outreach) gets pulled
 * toward the overall average instead of letting a handful of
 * ratings swing its rank as if they were fully representative.
 * min_ratings=5 means a canteen needs ~5 ratings before its own
 * average is mostly trusted; below that, the global average pulls it back.
 * Canteens with zero ratings are excluded (nothing to rank them on).
 */
function get_topsis_ranking(PDO $pdo, array $weights, int $minRatings = 5): array {
    $stmt = $pdo->prepare(
        'SELECT cid, cname,
                bayes_price       AS avg_price_rating,
                bayes_quality     AS avg_quality_rating,
                bayes_cleanliness AS avg_cleanliness_rating,
                bayes_speed       AS avg_speed_rating,
                bayes_hygiene     AS avg_hygiene_rating,
                total_ratings
         FROM canteen_criteria_bayesian(:min_ratings)'
    );
    $stmt->execute(['min_ratings' => $minRatings]);
    $allRows = $stmt->fetchAll();

    $rows = [];
    foreach ($allRows as $r) {
        if ((int) $r['total_ratings'] === 0) {
            continue; // no ratings yet — nothing to rank this canteen on
        }
        $rows[$r['cid']] = $r;
    }

    return calculate_topsis($rows, $weights);
}

/**
 * Normalizes a set of custom weights (e.g. from form sliders that don't
 * add up to exactly 1) so they sum to 1.0. Falls back to equal weights
 * if the input is invalid or all zero.
 */
function normalize_custom_weights(array $rawWeights): array {
    $criteria = TOPSIS_CRITERIA;
    $clean = [];
    $sum = 0.0;

    foreach ($criteria as $c) {
        $v = max(0.0, (float) ($rawWeights[$c] ?? 0));
        $clean[$c] = $v;
        $sum += $v;
    }

    if ($sum <= 0) {
        return array_fill_keys($criteria, 1 / count($criteria));
    }

    foreach ($clean as $c => $v) {
        $clean[$c] = $v / $sum;
    }

    return $clean;
}
