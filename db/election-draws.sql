CREATE TABLE IF NOT EXISTS election_draws (
 election_id INTEGER PRIMARY KEY REFERENCES elections(id),
 requested_size INTEGER NOT NULL CHECK(requested_size IN (5,8))
);
CREATE TABLE IF NOT EXISTS election_draw_movies (
 election_id INTEGER NOT NULL REFERENCES election_draws(election_id),
 movie_id INTEGER NOT NULL REFERENCES movies(id),
 skipped_before INTEGER NOT NULL CHECK(skipped_before >= 0),
 selection TEXT NOT NULL CHECK(selection IN ('random','priority','guaranteed','skipped')),
 PRIMARY KEY(election_id,movie_id)
);
