INSERT INTO users (display_name) VALUES
    ('Sophie'),
    ('Nicola'),
    ('Arthur'),
    ('Alice');

INSERT INTO movies (title, release_year) VALUES
    ('Willow', 1988),
    ('Ladyhawke', 1985),
    ('Conan the Destroyer', 1984),
    ('My Little Pony: The Movie', 1986),
    ('The Princess Bride', 1987),
    ('Labyrinth', 1986);

INSERT INTO elections (name, status) VALUES ('First Democratic Movie Night', 'open');

INSERT INTO election_movies (election_id, movie_id)
SELECT 1, id FROM movies WHERE status = 'active';
