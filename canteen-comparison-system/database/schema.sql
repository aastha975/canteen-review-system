-- ============================================================
-- Canteen Comparison System — Core Schema
-- Target: PostgreSQL, deployed on CentOS
-- ============================================================

-- Drop in dependency order (safe for repeated local setup/testing)
DROP TABLE IF EXISTS checkins CASCADE;
DROP TABLE IF EXISTS dish_ratings CASCADE;
DROP TABLE IF EXISTS votes CASCADE;
DROP TABLE IF EXISTS feedback CASCADE;
DROP TABLE IF EXISTS criteria_ratings CASCADE;
DROP TABLE IF EXISTS menu_items CASCADE;
DROP TABLE IF EXISTS canteens CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- ------------------------------------------------------------
-- users: students, faculty, canteen admins, super admin
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id       SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL
                  CHECK (role IN ('student', 'faculty', 'canteen_admin', 'super_admin')),
    department    VARCHAR(100),           -- relevant for student/faculty
    created_at    TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- canteens: the 3 canteens (Bappa, IMDR, Media)
-- admin_user_id ties a canteen to the user who manages its menu
-- ------------------------------------------------------------
CREATE TABLE canteens (
    cid           SERIAL PRIMARY KEY,
    cname         VARCHAR(50) NOT NULL,
    location      VARCHAR(100),
    admin_user_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- menu_items: per-canteen menu, managed by that canteen's admin
-- ------------------------------------------------------------
CREATE TABLE menu_items (
    itemid       SERIAL PRIMARY KEY,
    cid          INTEGER NOT NULL REFERENCES canteens(cid) ON DELETE CASCADE,
    item_name    VARCHAR(100) NOT NULL,
    price        NUMERIC(6,2) NOT NULL CHECK (price >= 0),
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at   TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- criteria_ratings: the 5-criteria numeric ratings that feed TOPSIS
-- one row per rating submission (a user can rate a canteen more than
-- once over time — this is a history, not a single overwritten value)
-- ------------------------------------------------------------
CREATE TABLE criteria_ratings (
    rating_id           SERIAL PRIMARY KEY,
    user_id             INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    cid                 INTEGER NOT NULL REFERENCES canteens(cid) ON DELETE CASCADE,
    price_rating        SMALLINT NOT NULL CHECK (price_rating BETWEEN 1 AND 5),
    quality_rating      SMALLINT NOT NULL CHECK (quality_rating BETWEEN 1 AND 5),
    cleanliness_rating  SMALLINT NOT NULL CHECK (cleanliness_rating BETWEEN 1 AND 5),
    speed_rating        SMALLINT NOT NULL CHECK (speed_rating BETWEEN 1 AND 5),
    hygiene_rating      SMALLINT NOT NULL CHECK (hygiene_rating BETWEEN 1 AND 5),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- feedback: free-text reviews, publicly visible
-- ------------------------------------------------------------
CREATE TABLE feedback (
    feedback_id   SERIAL PRIMARY KEY,
    user_id       INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    cid           INTEGER NOT NULL REFERENCES canteens(cid) ON DELETE CASCADE,
    comment_text  TEXT NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- votes: like/dislike on a piece of feedback
-- one vote per user per feedback item (enforced by UNIQUE below)
-- vote_type: 1 = like, -1 = dislike
-- ------------------------------------------------------------
CREATE TABLE votes (
    vote_id      SERIAL PRIMARY KEY,
    feedback_id  INTEGER NOT NULL REFERENCES feedback(feedback_id) ON DELETE CASCADE,
    user_id      INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    vote_type    SMALLINT NOT NULL CHECK (vote_type IN (1, -1)),
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (feedback_id, user_id)
);

-- ------------------------------------------------------------
-- dish_ratings: rating for a SPECIFIC menu item, not the canteen
-- as a whole. This is what answers "which canteen has the best
-- Vada Pav" — criteria_ratings can't, since it only rates a
-- canteen overall.
-- ------------------------------------------------------------
CREATE TABLE dish_ratings (
    dish_rating_id SERIAL PRIMARY KEY,
    user_id        INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    itemid         INTEGER NOT NULL REFERENCES menu_items(itemid) ON DELETE CASCADE,
    rating         SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- checkins: a lightweight "I'm here now" log, used to build
-- crowd-by-time-of-day patterns per canteen. Deliberately separate
-- from feedback/ratings — check-ins should be quick and frequent,
-- not tied to writing a review.
-- ------------------------------------------------------------
CREATE TABLE checkins (
    checkin_id     SERIAL PRIMARY KEY,
    user_id        INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    cid            INTEGER NOT NULL REFERENCES canteens(cid) ON DELETE CASCADE,
    checked_in_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- Helpful indexes for the queries TOPSIS and the feedback feed will run often
-- ------------------------------------------------------------
CREATE INDEX idx_criteria_ratings_cid ON criteria_ratings(cid);
CREATE INDEX idx_feedback_cid ON feedback(cid);
CREATE INDEX idx_votes_feedback_id ON votes(feedback_id);
CREATE INDEX idx_menu_items_cid ON menu_items(cid);
CREATE INDEX idx_dish_ratings_itemid ON dish_ratings(itemid);
CREATE INDEX idx_checkins_cid_time ON checkins(cid, checked_in_at);
