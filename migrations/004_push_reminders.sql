-- Devices that agreed to receive break reminders (one row per browser/phone).
CREATE TABLE push_subscriptions (
    id         INTEGER PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    endpoint   TEXT    NOT NULL UNIQUE,
    p256dh     TEXT    NOT NULL,
    auth       TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- How many minutes before a break each person wants the reminder (1, 2 or 5).
ALTER TABLE users ADD COLUMN reminder_minutes INTEGER NOT NULL DEFAULT 2;

-- When the reminder for this booking went out; NULL = not yet. Keeps the scheduled task from repeating it.
ALTER TABLE bookings ADD COLUMN reminded_at TEXT;
