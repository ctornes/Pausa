-- People who registered themselves and are waiting for an admin to activate them.
-- They are also inactive (active = 0), so they can't log in until approved.
ALTER TABLE users ADD COLUMN pending INTEGER NOT NULL DEFAULT 0;
