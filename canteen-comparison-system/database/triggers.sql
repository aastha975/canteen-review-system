-- ============================================================
-- Triggers for the Canteen Comparison System
-- (Rating bounds are already enforced via CHECK constraints in
--  schema.sql — the triggers here handle logic CHECK constraints
--  can't express: cross-table business rules and bookkeeping.)
-- ============================================================

-- ------------------------------------------------------------
-- 1. Auto-update menu_items.updated_at whenever price/name/availability changes
--    (so "last updated" is always accurate for admins and for any
--    future menu-change audit log)
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_menu_item_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_menu_items_updated_at ON menu_items;

CREATE TRIGGER trg_menu_items_updated_at
BEFORE UPDATE ON menu_items
FOR EACH ROW
EXECUTE FUNCTION set_menu_item_updated_at();


-- ------------------------------------------------------------
-- 2. Prevent a canteen admin from submitting a criteria_rating for
--    their OWN canteen (obvious conflict of interest / gaming the
--    TOPSIS ranking). This is a cross-table rule a plain CHECK
--    constraint cannot express, so it needs a trigger.
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION prevent_admin_self_rating()
RETURNS TRIGGER AS $$
DECLARE
    is_own_canteen BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM canteens
        WHERE cid = NEW.cid
        AND admin_user_id = NEW.user_id
    ) INTO is_own_canteen;

    IF is_own_canteen THEN
        RAISE EXCEPTION 'Canteen admins cannot rate their own canteen (user_id=%, cid=%)',
            NEW.user_id, NEW.cid;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_prevent_admin_self_rating ON criteria_ratings;

CREATE TRIGGER trg_prevent_admin_self_rating
BEFORE INSERT ON criteria_ratings
FOR EACH ROW
EXECUTE FUNCTION prevent_admin_self_rating();


-- ------------------------------------------------------------
-- 3. Same rule, applied to feedback text (an admin shouldn't be able
--    to post glowing reviews of their own canteen either)
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION prevent_admin_self_feedback()
RETURNS TRIGGER AS $$
DECLARE
    is_own_canteen BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM canteens
        WHERE cid = NEW.cid
        AND admin_user_id = NEW.user_id
    ) INTO is_own_canteen;

    IF is_own_canteen THEN
        RAISE EXCEPTION 'Canteen admins cannot post feedback on their own canteen (user_id=%, cid=%)',
            NEW.user_id, NEW.cid;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_prevent_admin_self_feedback ON feedback;

CREATE TRIGGER trg_prevent_admin_self_feedback
BEFORE INSERT ON feedback
FOR EACH ROW
EXECUTE FUNCTION prevent_admin_self_feedback();


-- ------------------------------------------------------------
-- 4. Rate-limit check-ins: block a user from checking into the
--    SAME canteen again within 15 minutes. Without this, one
--    person spam-clicking "I'm here" would fake a crowd spike and
--    corrupt the crowd-by-hour data everyone else relies on.
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION prevent_checkin_spam()
RETURNS TRIGGER AS $$
DECLARE
    recent_checkin_exists BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM checkins
        WHERE user_id = NEW.user_id
        AND cid = NEW.cid
        AND checked_in_at > NOW() - INTERVAL '15 minutes'
    ) INTO recent_checkin_exists;

    IF recent_checkin_exists THEN
        RAISE EXCEPTION 'You already checked in to this canteen recently (user_id=%, cid=%)',
            NEW.user_id, NEW.cid;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_prevent_checkin_spam ON checkins;

CREATE TRIGGER trg_prevent_checkin_spam
BEFORE INSERT ON checkins
FOR EACH ROW
EXECUTE FUNCTION prevent_checkin_spam();
