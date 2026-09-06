# AGENTS.md — Little Gay Film Club™

## Project intent

This is a small private film-club voting application. Keep it understandable, playful and maintainable. Do not introduce frameworks or infrastructure unless the existing code has demonstrated a real need for them.

## Architecture

- PHP owns authoritative persisted state and authoritative election results.
- TypeScript owns interactive browser behaviour and speculative/local election calculations.
- SQLite is the development/default database via PDO. Keep SQL conservative enough that migration to MariaDB/MySQL later is straightforward.
- No third-party identity provider.
- Initial development identity is a simple user selector. Later auth will use a unique secret URL which establishes a long-lived secure local cookie.

## Election rules

- Elections snapshot their eligible films in `election_movies` when opened/created.
- A film added later is not eligible for an already-open election.
- Never infer historical eligibility from the current movie list.
- Ballots are revisioned. Never overwrite or delete prior ballot revisions merely because a user changes their vote.
- The current committed ballot for a user/election is the latest ballot revision.
- RCV means single-winner instant-runoff voting.
- The PHP implementation is authoritative.
- The TypeScript RCV implementation should remain a pure function and should agree with PHP against shared fixtures.
- Tie behaviour must be deterministic and explicit. v0.1 eliminates the tied candidate with the numerically lowest candidate ID; this is a placeholder policy, not a constitutional truth.

## UX direction

Eventually:
- ranked movies are drag-and-drop cards with posters
- movie details can fly out from cards
- users can see speculative election results update while arranging their draft
- speculative calculations use the committed state as loaded when voting began, not silent mid-edit rebasing
- graph animation should make transfers/eliminations visible
- ballot history should support a comic timeline/replay showing how a result evolved

## Roadmap

Core:
- users
- movies
- rankings
- RCV winner

Nice:
- drag/drop + images
- movie info panels
- external movie search
- live/animated ranking graph
- removal votes
- eligibility freeze

Stretch-ish:
- URL + cookie auth
- user management console
- vote timeline/replay

## Coding style

- Prefer plain PHP with strict types, small functions/classes, PDO prepared statements, and explicit data shapes.
- Prefer vanilla TypeScript unless UI complexity genuinely justifies a library.
- Keep election calculation independent of HTTP/database/UI concerns.
- Add tests for election edge cases before altering algorithm behaviour.

## Live round preview

The framework-free preview sits beside the ballot (below on narrow screens) and updates during dragging. It uses the page-load snapshot, replacing the selected user's ballot, never adding a second vote. Other users' submissions require an explicit reload. A successful local submission updates only that user's snapshot entry using the ranking actually sent. PHP remains authoritative; neither RCV engine's voting rules changed. Transfer counts come from consecutive engine round deltas; final survivors are labelled without inventing a tally.

Exercise: choose a voter, drag a film across another and watch totals before releasing. Submit, switch voters, and reload to compare with the authoritative result. In a second tab submit another user's ballot: the first tab must stay stable until reloaded. Test a narrow window as well.

## Future non-priority features

- Final polish: a non-interactive fake client-side AI commentator blob, reacting to events with canned sarcastic comments. No actual AI, API or network requests. Defer until after core work.
- Movie flyout discussions: optional nominator pitch plus at most one comment per user per movie; users can edit/delete their own comment. Discussions attach to movies and persist across elections. No priority change.

## Movie cards and mystery nominations

Cards show posters when available and neutral artwork otherwise. Details opens a native modal flyout (Escape closes it). Movie search/import and one-comment-per-user discussions remain future work.

Run `php db/migrate.php` once when upgrading an existing database; this adds fields without replacing movies, elections or ballot revisions. Fresh `db/init.php` databases include the fields (init still resets the database). Test with `php tests/movies_test.php` and `npm test` in frontend.

Use **Nominate or reveal a film** to choose a development nominator and enter the real film details, optional pitch, and mystery alias. Mysteries require a pitch. New nominations belong to the ongoing pool, not the current election's frozen candidate list. They become eligible when a future election snapshots that pool.

A hidden mystery exposes only its alias, pitch and neutral artwork; PHP filters its title, year, synopsis and poster before rendering cards, bootstrap data and authoritative results. Sort by public title, never the hidden title. Revealing is a deliberate, confirmed action by the nominator on the nomination page; it works after an election closes and never runs automatically. Existing tabs must reload to see a reveal. The reveal is permanent and keeps the same movie ID and ballots.

The current user selector is development identity, not authentication: users can impersonate each other until login is implemented. Reveal ownership is checked on the server against that selected user. Keep this prototype private until authentication exists. Apache must honor the supplied .htaccess rules, which block private database/source directories; other servers need equivalent restrictions. Metadata is never fetched for hidden films. Pitches and aliases are intentionally public: keep spoilers out of them.
