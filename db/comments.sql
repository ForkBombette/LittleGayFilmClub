CREATE TABLE IF NOT EXISTS movie_comments (
    movie_id INTEGER NOT NULL REFERENCES movies(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    body TEXT NOT NULL,
    version TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (movie_id, user_id)
);
