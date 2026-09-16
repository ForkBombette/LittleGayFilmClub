# Roadmap

The core application is implemented and hosted. This page tracks current scope; detailed known bugs are in [BUGS.md](../BUGS.md), and current rules are in [BEHAVIOUR.md](BEHAVIOUR.md).

## Implemented

- Ranked ballots, revision history, PHP RCV and a matching live TypeScript preview.
- Candidate snapshots, election opening/closure, frozen results and cancellation archives.
- Posters, film details, mystery nominations/reveal and owner-only nomination editing.
- TMDB search/import for nominations and previously watched films.
- One editable comment per member per film.
- Removal by a majority of active members, including stale-ballot reconciliation.
- Single-use invitations, multiple remembered devices, permissions and member management.
- Watched records and direct/elected next-movie-night announcements.
- Ballot history stepping, vertical round charts, replay and the decorative trend line.
- The fake client-side AI commentator on the open ballot.
- Domain-root hosting, upload packages and CSRF/session diagnostics.

## Next phase: presentation

Tighten page layouts, labels, navigation and information density after using the core flows. Retain the detailed round tables for now. Changes should keep keyboard access, narrow-screen use and clear separation of drafts from committed results.

## Discussed change, not implemented

Exclude the currently announced film from the pool when opening a new election, while retaining existing election snapshots. Clearing the announcement would make it eligible again only if still active. This is Sophie's separate work in progress; it is not part of the documentation cleanup and should remain a separate change/commit.

## Remaining optional work

- Animated historical progression across ballot revisions.
- Exact removal/submission event chronology for faithful historical replay.
- More explicit animated vote transfers between candidates.
- Broader canned AI event coverage beyond the open ballot.

## Deliberately deferred bugs

- Duplicate catalogue entries when adding the same previously watched film.
- Re-nominating a removed real film under a new movie ID.

These share a film-identity problem. Matching remakes, concurrent nominations, repeat screenings and deliberate reinstatement need considered rules; do not infer those policies or clean existing data as a side effect. See BUGS.md for reproduction steps and starting points.

No change to the existing invitation/device workflow is planned.
