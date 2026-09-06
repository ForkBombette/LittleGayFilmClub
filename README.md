# Little Gay Film Club™

A tiny, deliberately over-engineered democratic film-night app.

## v0.1 goals

- arbitrary users
- ongoing movie list
- rank eligible movies
- authoritative ranked-choice voting (RCV / instant-runoff) in PHP
- mirrored speculative RCV engine in TypeScript
- ballot revisions are retained historically
- election eligibility is snapshotted when an election opens
- intentionally simple development identity selector; no real auth yet

## Requirements

- PHP 8.1+
- PDO SQLite extension
- Node.js 18+ and npm (only for compiling/testing the TypeScript frontend)

## WAMP setup

1. Extract/copy this repository into your WAMP web root, e.g. `C:\\wamp64\\www\\little-gay-film-club`.
2. Open a terminal in the project root.
3. Initialise the database:

   ```bash
   php db/init.php
   ```

4. Build the TypeScript:

   ```bash
   cd frontend
   npm install
   npm run build
   cd ..
   ```

5. Browse to the `public` directory through WAMP, e.g. `http://localhost/little-gay-film-club/public/`.

The SQLite database is created at `var/lgfc.sqlite` and is ignored by Git.

## Tests

PHP:

```bash
php tests/rcv_test.php
```

TypeScript:

```bash
cd frontend
npm test
```

Both engines use the same conceptual fixtures in `tests/fixtures/rcv_cases.json`.

## Current deliberately ugly workflow

Choose a development user, drag films into preference order, and submit. Each submission creates a new ballot revision rather than overwriting the old one. The results page calculates the authoritative RCV result from the latest submitted ballot for each user.

The browser also calculates a speculative result while you drag, using the committed ballots as loaded when the page opened plus your current draft. That is the first stepping stone toward the live animated election graph.

## Next likely steps

- improve ballot UI and movie cards/posters
- result graph and animation
- election open/close UI and eligibility freezing controls
- add movies and external metadata search
- majority removal votes
- auth via unique URL -> persistent cookie
- admin/user management
- historical replay of ballot revisions
