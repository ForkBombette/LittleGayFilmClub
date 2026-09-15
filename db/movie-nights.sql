CREATE TABLE IF NOT EXISTS movie_night_announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    movie_id INTEGER NULL REFERENCES movies(id),
    scheduled_on TEXT NULL,
    source TEXT NOT NULL CHECK(source IN ('election','direct','cleared')),
    election_id INTEGER NULL REFERENCES elections(id),
    organised_by INTEGER NOT NULL REFERENCES users(id),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS election_cancellations (
    election_id INTEGER PRIMARY KEY REFERENCES elections(id),
    announcement_id INTEGER NOT NULL REFERENCES movie_night_announcements(id),
    cancelled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
