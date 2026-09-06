PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS movies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    release_year INTEGER NULL,
    image_url TEXT NULL,
    summary TEXT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'watched', 'removed')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS elections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed')),
    opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS election_movies (
    election_id INTEGER NOT NULL,
    movie_id INTEGER NOT NULL,
    PRIMARY KEY (election_id, movie_id),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS ballot_revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    election_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS ballot_revision_choices (
    ballot_revision_id INTEGER NOT NULL,
    movie_id INTEGER NOT NULL,
    rank INTEGER NOT NULL CHECK (rank > 0),
    PRIMARY KEY (ballot_revision_id, movie_id),
    UNIQUE (ballot_revision_id, rank),
    FOREIGN KEY (ballot_revision_id) REFERENCES ballot_revisions(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_ballot_revision_lookup
    ON ballot_revisions (election_id, user_id, id DESC);
