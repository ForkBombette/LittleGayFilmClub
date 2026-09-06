# Little Gay Film Club™

A tiny, deliberately over-engineered democratic film-night app.

I came up with the concept, thought about the concept, extended the concept, and realised that it 
couldn't be Python if the plan was to just drop it on a server and run, but it was beyond my meagre 
PHP skills.
So it's become an experiment in "vibe coding", while I make sure that I understand what's happening
every step of the way.
Endless thanks and blame to Nicola whose objection to selections from the **undisputable** pinnacle of 
cinema (ie. 1980s sword-and-sorcery movies) formed the inspiration.

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

## Live round preview

The framework-free preview sits beside the ballot (below on narrow screens) and updates during dragging. It uses the page-load snapshot, replacing the selected user's ballot, never adding a second vote. Other users' submissions require an explicit reload. A successful local submission updates only that user's snapshot entry using the ranking actually sent. PHP remains authoritative; neither RCV engine's voting rules changed. Transfer counts come from consecutive engine round deltas; final survivors are labelled without inventing a tally.

Exercise: choose a voter, drag a film across another and watch totals before releasing. Submit, switch voters, and reload to compare with the authoritative result. In a second tab submit another user's ballot: the first tab must stay stable until reloaded. Test a narrow window as well.

## Future non-priority features

- Final polish: a non-interactive fake client-side AI commentator blob, reacting to events with canned sarcastic comments. No actual AI, API or network requests. Defer until after core work.
- Movie flyout discussions: optional nominator pitch plus at most one comment per user per movie; users can edit/delete their own comment. Discussions attach to movies and persist across elections. No priority change.

## Animated round chart

The draft preview now includes a bar chart above the retained round tables. Use Previous round / Next round or Replay rounds (which becomes Pause replay). Candidate rows and the vote scale stay fixed across rounds. Reordering immediately updates the selected round and pauses replay; changing voters starts at round one. Reduced-motion preferences disable bar transitions. The engine and snapshot rules are unchanged.

## Movie cards and mystery nominations

Cards show posters when available and neutral artwork otherwise. Details opens a native modal flyout (Escape closes it). Movie search/import and one-comment-per-user discussions remain future work.

Run `php db/migrate.php` once when upgrading an existing database; this adds fields without replacing movies, elections or ballot revisions. Fresh `db/init.php` databases include the fields (init still resets the database). Test with `php tests/movies_test.php` and `npm test` in frontend.

Use **Nominate or reveal a film** to choose a development nominator and enter the real film details, optional pitch, and mystery alias. Mysteries require a pitch. New nominations belong to the ongoing pool, not the current election's frozen candidate list. They become eligible when a future election snapshots that pool.

A hidden mystery exposes only its alias, pitch and neutral artwork; PHP filters its title, year, synopsis and poster before rendering cards, bootstrap data and authoritative results. Sort by public title, never the hidden title. Revealing is a deliberate, confirmed action by the nominator on the nomination page; it works after an election closes and never runs automatically. Existing tabs must reload to see a reveal. The reveal is permanent and keeps the same movie ID and ballots.

The current user selector is development identity, not authentication: users can impersonate each other until login is implemented. Reveal ownership is checked on the server against that selected user. Keep this prototype private until authentication exists. Apache must honor the supplied .htaccess rules, which block private database/source directories; other servers need equivalent restrictions. Metadata is never fetched for hidden films. Pitches and aliases are intentionally public: keep spoilers out of them.

## Browsing beyond the ballot

Collapsed Not in this election and Watched films lists sit beneath the ballot, with counts and empty states. Active films outside the election snapshot appear in the first; watched films outside it appear in the second. Snapshot membership takes precedence if a film’s status changes mid-election, so it stays on the ballot without duplication. Removed films are omitted. Both lists use the shared detail flyout and server-filtered mystery data. Browse-only films never enter rankings or RCV candidates. Reviews/comments remain future work and will use these same movie details. No database migration is needed.
