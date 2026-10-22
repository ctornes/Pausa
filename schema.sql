CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY,
    name            TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    pin_hash        TEXT    NOT NULL,
    is_admin        INTEGER NOT NULL DEFAULT 0,
    active          INTEGER NOT NULL DEFAULT 1,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until    INTEGER NOT NULL DEFAULT 0,      -- unix timestamp
    created_at      TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- One row per occupied seat. UNIQUE (date, slot_start, seat) makes it impossible
-- for two people to grab the same seat, even when they click at the same instant.
CREATE TABLE IF NOT EXISTS bookings (
    id         INTEGER PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    date       TEXT    NOT NULL,                     -- YYYY-MM-DD
    slot_start TEXT    NOT NULL,                     -- HH:MM
    seat       INTEGER NOT NULL CHECK (seat >= 1),
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (date, slot_start, seat),
    UNIQUE (date, slot_start, user_id)
);

CREATE INDEX IF NOT EXISTS bookings_user_date ON bookings (user_id, date);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('windows', '10:00-12:00' || char(10) || '15:00-17:30'),
    ('slot_minutes', '10'),
    ('capacity', '2'),
    ('max_per_day', '1'),
    ('self_signup', '1');
