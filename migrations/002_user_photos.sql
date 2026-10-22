-- Version of the person's profile photo (NULL = no photo). Bumped on every change so
-- browsers fetch the new image instead of a cached one.
ALTER TABLE users ADD COLUMN photo_version INTEGER;
