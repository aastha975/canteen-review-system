-- ============================================================
-- Views for the Canteen Comparison System
-- ============================================================

-- ------------------------------------------------------------
-- 1. canteen_criteria_avg
-- One row per canteen with the average of each of the 5 criteria,
-- plus how many ratings that average is based on. This is the
-- ONLY table the TOPSIS engine reads from — it never touches raw
-- criteria_ratings directly, so the algorithm stays fast even as
-- the ratings table grows.
--
-- Also includes average menu price per canteen (from menu_items)
-- as an alternate/cross-check for the "price" criterion, since
-- price can be judged both by student ratings AND actual menu cost.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW canteen_criteria_avg AS
SELECT
    c.cid,
    c.cname,
    ROUND(AVG(cr.price_rating)::numeric, 2)       AS avg_price_rating,
    ROUND(AVG(cr.quality_rating)::numeric, 2)     AS avg_quality_rating,
    ROUND(AVG(cr.cleanliness_rating)::numeric, 2) AS avg_cleanliness_rating,
    ROUND(AVG(cr.speed_rating)::numeric, 2)       AS avg_speed_rating,
    ROUND(AVG(cr.hygiene_rating)::numeric, 2)     AS avg_hygiene_rating,
    COUNT(cr.rating_id)                           AS total_ratings
FROM canteens c
LEFT JOIN criteria_ratings cr ON cr.cid = c.cid
GROUP BY c.cid, c.cname;


-- ------------------------------------------------------------
-- 2. canteen_avg_menu_price
-- Average listed menu price per canteen — a separate, objective
-- price signal (not self-reported), useful to compare against
-- avg_price_rating from the view above.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW canteen_avg_menu_price AS
SELECT
    c.cid,
    c.cname,
    ROUND(AVG(m.price)::numeric, 2) AS avg_item_price,
    COUNT(m.itemid)                 AS total_items
FROM canteens c
LEFT JOIN menu_items m ON m.cid = c.cid AND m.is_available = TRUE
GROUP BY c.cid, c.cname;


-- ------------------------------------------------------------
-- 3. feedback_with_votes
-- Feedback joined with like/dislike counts, ready for the
-- feedback feed's ranking logic (Wilson score / net-votes sort
-- happens in the application layer using these counts).
-- ------------------------------------------------------------
-- ------------------------------------------------------------
-- 4. dish_ratings_by_canteen
-- For a given dish name, shows how it's rated at each canteen
-- that sells it, along with its price. This is what powers
-- "which canteen has the best Vada Pav" — a simple filter on
-- item_name against this view, sorted by avg_rating desc.
-- Also computes a rating-per-rupee "value_score" for a
-- "best value" sort option.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW dish_ratings_by_canteen AS
SELECT
    m.itemid,
    m.item_name,
    c.cid,
    c.cname,
    m.price,
    ROUND(AVG(dr.rating)::numeric, 2) AS avg_rating,
    COUNT(dr.dish_rating_id)          AS total_ratings,
    ROUND((AVG(dr.rating) / NULLIF(m.price, 0))::numeric, 4) AS value_score
FROM menu_items m
JOIN canteens c ON c.cid = m.cid
LEFT JOIN dish_ratings dr ON dr.itemid = m.itemid
WHERE m.is_available = TRUE
GROUP BY m.itemid, m.item_name, c.cid, c.cname, m.price;


-- ------------------------------------------------------------
-- 5. canteen_crowd_by_hour
-- Check-ins bucketed by canteen and hour-of-day (0–23), so the
-- app can answer "when is this canteen usually less crowded."
-- Pure aggregation — no ML involved.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW canteen_crowd_by_hour AS
SELECT
    c.cid,
    c.cname,
    EXTRACT(HOUR FROM ch.checked_in_at)::int AS hour_of_day,
    COUNT(ch.checkin_id)                     AS checkin_count
FROM canteens c
LEFT JOIN checkins ch ON ch.cid = c.cid
GROUP BY c.cid, c.cname, EXTRACT(HOUR FROM ch.checked_in_at)
ORDER BY c.cid, hour_of_day;


-- ------------------------------------------------------------
-- 6. canteen_criteria_bayesian(min_ratings)
-- Bayesian-adjusted ("shrinkage") averages — the fix for sampling
-- bias where some canteens have far fewer ratings than others
-- (e.g. IMDR being under-sampled in the original field survey).
--
-- Formula: weighted_rating = (v/(v+m)) * R + (m/(v+m)) * C
--   R = canteen's own average for that criterion
--   v = number of ratings that canteen has
--   m = minimum-ratings threshold (how many ratings you'd want
--       before fully trusting a canteen's own average)
--   C = global average for that criterion, across ALL canteens
--
-- A canteen with few ratings gets pulled toward the global average
-- (its small sample isn't fully trusted yet). As ratings accumulate
-- (v grows), the formula trusts that canteen's own number more.
-- This is the same technique IMDb uses for its "weighted rating."
--
-- Passed as a function (not a plain view) so min_ratings (m) is
-- adjustable, e.g. SELECT * FROM canteen_criteria_bayesian(10);
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION canteen_criteria_bayesian(min_ratings INT DEFAULT 5)
RETURNS TABLE (
    cid                INTEGER,
    cname              VARCHAR,
    bayes_price        NUMERIC,
    bayes_quality      NUMERIC,
    bayes_cleanliness  NUMERIC,
    bayes_speed        NUMERIC,
    bayes_hygiene      NUMERIC,
    total_ratings      BIGINT
) AS $$
BEGIN
    RETURN QUERY
    WITH global_avg AS (
        SELECT
            AVG(price_rating)::numeric       AS g_price,
            AVG(quality_rating)::numeric     AS g_quality,
            AVG(cleanliness_rating)::numeric AS g_cleanliness,
            AVG(speed_rating)::numeric       AS g_speed,
            AVG(hygiene_rating)::numeric     AS g_hygiene
        FROM criteria_ratings
    )
    SELECT
        a.cid,
        a.cname,
        ROUND(
            ((a.total_ratings::numeric / (a.total_ratings + min_ratings)) * COALESCE(a.avg_price_rating, 0)) +
            ((min_ratings::numeric / (a.total_ratings + min_ratings)) * g.g_price)
        , 3) AS bayes_price,
        ROUND(
            ((a.total_ratings::numeric / (a.total_ratings + min_ratings)) * COALESCE(a.avg_quality_rating, 0)) +
            ((min_ratings::numeric / (a.total_ratings + min_ratings)) * g.g_quality)
        , 3) AS bayes_quality,
        ROUND(
            ((a.total_ratings::numeric / (a.total_ratings + min_ratings)) * COALESCE(a.avg_cleanliness_rating, 0)) +
            ((min_ratings::numeric / (a.total_ratings + min_ratings)) * g.g_cleanliness)
        , 3) AS bayes_cleanliness,
        ROUND(
            ((a.total_ratings::numeric / (a.total_ratings + min_ratings)) * COALESCE(a.avg_speed_rating, 0)) +
            ((min_ratings::numeric / (a.total_ratings + min_ratings)) * g.g_speed)
        , 3) AS bayes_speed,
        ROUND(
            ((a.total_ratings::numeric / (a.total_ratings + min_ratings)) * COALESCE(a.avg_hygiene_rating, 0)) +
            ((min_ratings::numeric / (a.total_ratings + min_ratings)) * g.g_hygiene)
        , 3) AS bayes_hygiene,
        a.total_ratings
    FROM canteen_criteria_avg a
    CROSS JOIN global_avg g;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE VIEW feedback_with_votes AS
SELECT
    f.feedback_id,
    f.cid,
    c.cname,
    f.user_id,
    u.name AS user_name,
    u.role AS user_role,
    f.comment_text,
    f.created_at,
    COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0)  AS likes,
    COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) AS dislikes
FROM feedback f
JOIN canteens c ON c.cid = f.cid
JOIN users u ON u.user_id = f.user_id
LEFT JOIN votes v ON v.feedback_id = f.feedback_id
GROUP BY f.feedback_id, f.cid, c.cname, f.user_id, u.name, u.role,
         f.comment_text, f.created_at;
