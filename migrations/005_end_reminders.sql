-- Second reminder, shortly before the break ends. Each person can turn it off (1 = on).
ALTER TABLE users ADD COLUMN end_reminder INTEGER NOT NULL DEFAULT 1;

-- When the end-of-break reminder for this booking went out; NULL = not yet.
ALTER TABLE bookings ADD COLUMN end_reminded_at TEXT;
