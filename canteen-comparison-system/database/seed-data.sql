-- ============================================================
-- Seed data for the Canteen Comparison System
--
-- Canteens and menu items below are your REAL data from
-- FP_JSON_FINAL.json (last year's field project).
--
-- Users, criteria_ratings, feedback, and votes are SAMPLE data
-- for testing — last year's project only captured a categorical
-- "reason" field (e.g. "hygiene", "affordable prices"), not
-- numeric 1–5 scores per criterion, so there's no real historical
-- data to migrate for those. These sample rows just give you a
-- working dataset to test TOPSIS and the feedback feed against
-- before real students start using the site.
--
-- All sample users have the password: password123
-- (bcrypt-hashed below — change immediately in any real deployment)
-- ============================================================

-- ------------------------------------------------------------
-- Canteens (real data)
-- ------------------------------------------------------------
INSERT INTO canteens (cid, cname, location) VALUES
(1, 'BAPPA', 'Main building'),
(2, 'IMDR', 'Gate no 1'),
(3, 'MEDIA', 'Gate no 4');

-- Keep the sequence in sync since we inserted explicit IDs
SELECT setval('canteens_cid_seq', (SELECT MAX(cid) FROM canteens));


-- ------------------------------------------------------------
-- Menu items (real data — 15 items × 3 canteens)
-- ------------------------------------------------------------
INSERT INTO menu_items (itemid, cid, item_name, price) VALUES
(101, 1, 'Vada Pav', 20), (102, 2, 'Vada Pav', 20), (103, 3, 'Vada Pav', 20),
(104, 1, 'Samosa', 20), (105, 2, 'Samosa', 25), (106, 3, 'Samosa', 20),
(107, 1, 'Dosa', 50), (108, 2, 'Dosa', 70), (109, 3, 'Dosa', 50),
(110, 1, 'Cold Coffee', 60), (111, 2, 'Cold Coffee', 50), (112, 3, 'Cold Coffee', 40),
(113, 1, 'Tea', 20), (114, 2, 'Tea', 20), (115, 3, 'Tea', 15),
(116, 1, 'Soft Drink', 30), (117, 2, 'Soft Drink', 25), (118, 3, 'Soft Drink', 50),
(119, 1, 'Paratha', 80), (120, 2, 'Paratha', 70), (121, 3, 'Paratha', 45),
(122, 1, 'Wada Sambar', 60), (123, 2, 'Wada Sambar', 55), (124, 3, 'Wada Sambar', 55),
(125, 1, 'Sandwich', 60), (126, 2, 'Sandwich', 70), (127, 3, 'Sandwich', 50),
(128, 1, 'Poha', 25), (129, 2, 'Poha', 25), (130, 3, 'Poha', 25),
(131, 1, 'Sabudana Khichdi', 60), (132, 2, 'Sabudana Khichdi', 55), (133, 3, 'Sabudana Khichdi', 40),
(134, 1, 'Peri-Peri Fries', 90), (135, 2, 'Peri-Peri Fries', 100), (136, 3, 'Peri-Peri Fries', 80),
(137, 1, 'Edli Chatani', 40), (138, 2, 'Edli Chatani', 50), (139, 3, 'Edli Chatani', 40),
(140, 1, 'Fried Rice', 130), (141, 2, 'Fried Rice', 150), (142, 3, 'Fried Rice', 90),
(143, 1, 'Pav Bhaji', 90), (144, 2, 'Pav Bhaji', 85), (145, 3, 'Pav Bhaji', 60);

SELECT setval('menu_items_itemid_seq', (SELECT MAX(itemid) FROM menu_items));


-- ------------------------------------------------------------
-- Sample users (all passwords: password123)
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, department) VALUES
('Bappa Admin',   'admin.bappa@college.edu', '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'canteen_admin', NULL),
('IMDR Admin',    'admin.imdr@college.edu',  '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'canteen_admin', NULL),
('Media Admin',   'admin.media@college.edu', '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'canteen_admin', NULL),
('Aastha S',      'aastha@college.edu',      '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'student', 'CS'),
('Rohan K',       'rohan@college.edu',       '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'student', 'CS'),
('Priya N',       'priya@college.edu',       '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'student', 'Electronics'),
('Sameer T',      'sameer@college.edu',      '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'student', 'Statistics'),
('Dr. Mehta',     'mehta@college.edu',       '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'faculty', 'Chemistry'),
('Dr. Rao',       'rao@college.edu',         '$2b$12$mA8G/W40SdbMRhs/XHQpIu57eI3a6hV8CMWe4X3IGb2G0yjuj6z6a', 'faculty', 'Computer Science');

-- Assign each canteen admin to their canteen
UPDATE canteens SET admin_user_id = (SELECT user_id FROM users WHERE email = 'admin.bappa@college.edu') WHERE cid = 1;
UPDATE canteens SET admin_user_id = (SELECT user_id FROM users WHERE email = 'admin.imdr@college.edu')  WHERE cid = 2;
UPDATE canteens SET admin_user_id = (SELECT user_id FROM users WHERE email = 'admin.media@college.edu') WHERE cid = 3;


-- ------------------------------------------------------------
-- Sample criteria_ratings (synthetic, loosely reflects last
-- year's "reason" trends — e.g. BAPPA was frequently praised for
-- cleanliness/convenience, MEDIA for being less crowded/hygiene)
-- ------------------------------------------------------------
INSERT INTO criteria_ratings (user_id, cid, price_rating, quality_rating, cleanliness_rating, speed_rating, hygiene_rating) VALUES
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 1, 4, 4, 5, 4, 4),
((SELECT user_id FROM users WHERE email='rohan@college.edu'),  1, 4, 5, 4, 3, 4),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  1, 3, 4, 4, 4, 3),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 2, 3, 3, 4, 3, 3),
((SELECT user_id FROM users WHERE email='mehta@college.edu'),  2, 3, 3, 4, 3, 4),
((SELECT user_id FROM users WHERE email='rao@college.edu'),    2, 4, 3, 3, 3, 3),
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 3, 5, 4, 4, 5, 5),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  3, 4, 4, 5, 4, 5),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 3, 4, 3, 4, 5, 4);

-- ------------------------------------------------------------
-- Extra ratings for BAPPA (cid=1) and MEDIA (cid=3) ONLY, to
-- realistically mirror the actual sampling bias from last year's
-- field project — IMDR was under-reached and stays sparse (3
-- ratings) while BAPPA/MEDIA are well-sampled (8 each). This is
-- what canteen_criteria_bayesian() is meant to correct for.
-- ------------------------------------------------------------
INSERT INTO criteria_ratings (user_id, cid, price_rating, quality_rating, cleanliness_rating, speed_rating, hygiene_rating) VALUES
((SELECT user_id FROM users WHERE email='rohan@college.edu'),  1, 3, 4, 4, 4, 4),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 1, 4, 4, 5, 3, 4),
((SELECT user_id FROM users WHERE email='mehta@college.edu'),  1, 4, 5, 4, 4, 5),
((SELECT user_id FROM users WHERE email='rao@college.edu'),    1, 3, 4, 4, 3, 4),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  1, 4, 4, 5, 4, 4),
((SELECT user_id FROM users WHERE email='rohan@college.edu'),  3, 5, 4, 4, 5, 5),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 3, 4, 3, 5, 4, 5),
((SELECT user_id FROM users WHERE email='mehta@college.edu'),  3, 5, 4, 4, 5, 4),
((SELECT user_id FROM users WHERE email='rao@college.edu'),    3, 4, 4, 5, 5, 5),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  3, 5, 3, 4, 4, 5);


-- ------------------------------------------------------------
-- Sample feedback + votes
-- ------------------------------------------------------------
INSERT INTO feedback (feedback_id, user_id, cid, comment_text) VALUES
(1, (SELECT user_id FROM users WHERE email='aastha@college.edu'), 1, 'Bappa is clean and quick between lectures, but gets crowded around 1 PM.'),
(2, (SELECT user_id FROM users WHERE email='rohan@college.edu'),  1, 'Best vada pav on campus, hands down.'),
(3, (SELECT user_id FROM users WHERE email='sameer@college.edu'), 2, 'IMDR has good seating but the wait times need work.'),
(4, (SELECT user_id FROM users WHERE email='priya@college.edu'),  3, 'Media is the least crowded and always feels hygienic.');

SELECT setval('feedback_feedback_id_seq', (SELECT MAX(feedback_id) FROM feedback));

INSERT INTO votes (feedback_id, user_id, vote_type) VALUES
(1, (SELECT user_id FROM users WHERE email='rohan@college.edu'),  1),
(1, (SELECT user_id FROM users WHERE email='priya@college.edu'),  1),
(2, (SELECT user_id FROM users WHERE email='aastha@college.edu'), 1),
(3, (SELECT user_id FROM users WHERE email='mehta@college.edu'),  -1),
(4, (SELECT user_id FROM users WHERE email='sameer@college.edu'), 1),
(4, (SELECT user_id FROM users WHERE email='rohan@college.edu'),  1);


-- ------------------------------------------------------------
-- Sample dish_ratings (Vada Pav rated at all 3 canteens, itemids
-- 101/102/103 from the real menu data above, so "best Vada Pav"
-- has something to compare)
-- ------------------------------------------------------------
INSERT INTO dish_ratings (user_id, itemid, rating) VALUES
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 101, 5), -- Vada Pav @ BAPPA
((SELECT user_id FROM users WHERE email='rohan@college.edu'),  101, 5),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  101, 4),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 102, 3), -- Vada Pav @ IMDR
((SELECT user_id FROM users WHERE email='mehta@college.edu'),  102, 4),
((SELECT user_id FROM users WHERE email='rao@college.edu'),    103, 4), -- Vada Pav @ MEDIA
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 103, 5);


-- ------------------------------------------------------------
-- Sample checkins (spread across different hours so
-- canteen_crowd_by_hour has something meaningful to aggregate)
-- ------------------------------------------------------------
INSERT INTO checkins (user_id, cid, checked_in_at) VALUES
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 1, CURRENT_DATE + INTERVAL '13 hours'),
((SELECT user_id FROM users WHERE email='rohan@college.edu'),  1, CURRENT_DATE + INTERVAL '13 hours 10 minutes'),
((SELECT user_id FROM users WHERE email='priya@college.edu'),  1, CURRENT_DATE + INTERVAL '9 hours'),
((SELECT user_id FROM users WHERE email='sameer@college.edu'), 2, CURRENT_DATE + INTERVAL '11 hours'),
((SELECT user_id FROM users WHERE email='mehta@college.edu'),  2, CURRENT_DATE + INTERVAL '13 hours'),
((SELECT user_id FROM users WHERE email='rao@college.edu'),    3, CURRENT_DATE + INTERVAL '16 hours'),
((SELECT user_id FROM users WHERE email='aastha@college.edu'), 3, CURRENT_DATE + INTERVAL '16 hours 5 minutes');
