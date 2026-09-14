CREATE TABLE IF NOT EXISTS removal_requests (
    movie_id INTEGER PRIMARY KEY REFERENCES movies(id),
    proposed_by INTEGER NOT NULL REFERENCES users(id),
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    passed_at TEXT NULL,
    support_at_pass INTEGER NULL,
    threshold_at_pass INTEGER NULL
);
CREATE TABLE IF NOT EXISTS removal_votes (
    movie_id INTEGER NOT NULL REFERENCES removal_requests(movie_id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    support INTEGER NOT NULL CHECK(support IN (0,1)),
    PRIMARY KEY(movie_id,user_id)
);
CREATE TABLE IF NOT EXISTS election_removals (
    election_id INTEGER NOT NULL,
    movie_id INTEGER NOT NULL,
    removed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(election_id,movie_id),
    FOREIGN KEY(election_id,movie_id) REFERENCES election_movies(election_id,movie_id)
);
