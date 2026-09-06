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
