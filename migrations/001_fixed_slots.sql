-- Recurring breaks assigned by an admin: the same person in the same slot on the chosen weekdays.
CREATE TABLE fixed_slots (
    id         INTEGER PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    slot_start TEXT    NOT NULL,                     -- HH:MM
    weekdays   TEXT    NOT NULL,                     -- ISO weekday digits, '12345' = Monday to Friday
    starts_on  TEXT    NOT NULL,                     -- YYYY-MM-DD, first day it applies
    created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, slot_start)
);

-- Days on which a fixed slot has already been turned into a booking. Without it, an admin
-- removing a fixed break for one day would see it recreated on the next page load.
CREATE TABLE fixed_applied (
    fixed_id INTEGER NOT NULL REFERENCES fixed_slots (id) ON DELETE CASCADE,
    date     TEXT    NOT NULL,
    PRIMARY KEY (fixed_id, date)
);

-- Bookings created from a fixed slot are locked: the person can't cancel or move them.
ALTER TABLE bookings ADD COLUMN fixed_id INTEGER REFERENCES fixed_slots (id) ON DELETE SET NULL;
