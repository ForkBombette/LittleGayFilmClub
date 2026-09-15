# AGENTS.md — Little Gay Film Club™

## Project intent

This is a small private film-club voting application. Keep it understandable, playful and maintainable. Do not introduce frameworks or infrastructure unless the existing code has demonstrated a real need for them.

## Architecture

- PHP owns authoritative persisted state and authoritative election results.
- TypeScript owns interactive browser behaviour and speculative/local election calculations.
- SQLite is the development/default database via PDO. Keep SQL conservative enough that migration to MariaDB/MySQL later is straightforward.
- No third-party identity provider.
- Identity comes from a single-use personal link and a server-validated remembered-device cookie. All writes derive the actor from authentication, never request user IDs.

## Election rules

- Elections snapshot their eligible films in `election_movies` when opened/created.
- A film added later is not eligible for an already-open election.
- Never infer historical eligibility from the current movie list. Majority removals use election_removals as explicit exclusions; never delete the original snapshot rows.
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
- external movie search (implemented; requires TMDB configuration)
- live/animated ranking graph
- removal votes (implemented)
- eligibility freeze

Stretch-ish:
- user management console (implemented)
- vote timeline/replay

## Coding style

- Prefer plain PHP with strict types, small functions/classes, PDO prepared statements, and explicit data shapes.
- Prefer vanilla TypeScript unless UI complexity genuinely justifies a library.
- Keep election calculation independent of HTTP/database/UI concerns.
- Add tests for election edge cases before altering algorithm behaviour.

## Live round preview

The framework-free preview sits beside the ballot (below on narrow screens) and updates during dragging. It uses the page-load snapshot, replacing the signed-in user's ballot, never adding a second vote. Other users' submissions require an explicit reload. A successful local submission updates only that user's snapshot entry using the ranking actually sent. PHP remains authoritative; neither RCV engine's voting rules changed. Transfer counts come from consecutive engine round deltas; final survivors are labelled without inventing a tally.

Exercise: sign in, drag a film across another and watch totals before releasing. Submit and reload to compare with the authoritative result. Use a separate browser profile for a second member: their submission must not change the first browser's snapshot until reloaded. Test a narrow window as well.

## Future non-priority features

- Final polish: a non-interactive fake client-side AI commentator blob, reacting to events with canned sarcastic comments. No actual AI, API or network requests. Defer until after core work.
- Movie flyout discussions are implemented: optional nominator pitch plus one editable/deletable comment per member per movie, persisting across elections.
- Vertical colour-coded bars and the deliberately meaningless trend line/index are implemented in the live round chart. The index is decorative only and cannot affect RCV results. Future fake-AI remarks about the trend remain parked.

## Movie cards and mystery nominations

Cards show posters when available and neutral artwork otherwise. Details opens a native modal flyout (Escape closes it). Movie search/import is available when configured; one-comment-per-user discussions are available in the shared flyout.

Run `php db/migrate.php` once when upgrading an existing database; this adds fields without replacing movies, elections or ballot revisions. Fresh `db/init.php` databases include the fields (init still resets the database). Test with `php tests/movies_test.php` and `npm test` in frontend.

Use **Nominate or reveal a film** while signed in to enter the real film details, optional pitch, and mystery alias. Mysteries require a pitch. New nominations belong to the ongoing pool, not the current election's frozen candidate list. They become eligible when a future election snapshots that pool.

A hidden mystery exposes only its alias, pitch and neutral artwork; PHP filters its title, year, synopsis and poster before rendering cards, bootstrap data and authoritative results. Sort by public title, never the hidden title. Revealing is a deliberate, confirmed action by the nominator on the nomination page; it works after an election closes and never runs automatically. Existing tabs must reload to see a reveal. The reveal is permanent and keeps the same movie ID and ballots.

Reveal ownership is checked on the server against the signed-in member, including for organisers. Apache must honor the supplied .htaccess rules, which block private database/source directories; other servers need equivalent restrictions. Metadata is never fetched for hidden films. Pitches and aliases are intentionally public: keep spoilers out of them.

## Browsing beyond the ballot

Collapsed Not in this election and Watched films lists sit beneath the ballot, with counts and empty states. Active films outside the election snapshot appear in the first; watched films outside it appear in the second. Snapshot membership takes precedence if a film’s status changes mid-election, so it stays on the ballot without duplication. Removed films are omitted. Both lists use the shared detail flyout and server-filtered mystery data. Browse-only films never enter rankings or RCV candidates. Comments are now available in these same movie details. No database migration is needed.

## Election lifecycle

Use **Election controls and history** to open a named election from the active film pool or deliberately close the current election. Only one election may be open. Its eligible movie IDs are snapshotted at opening; later nominations wait for the next election. Each election starts with fresh ballots, and revisions still give each voter one current vote.

Closing stores the authoritative PHP result (winner and rounds) plus the exact latest ballot revision IDs. The server serializes open, close and submit operations using SQLite BEGIN IMMEDIATE, checking election status inside the write transaction. Stale submissions receive HTTP 409 and create no revision. Repeated closure is harmless; reopening a closed election is not supported. Closing with zero submissions stores no winner. Neither RCV engine was changed.

Closed results are read-only at index.php?electionId=ID and remain accessible after a new election opens. With no open election, the default page shows the latest closed election; with no elections at all it shows the browsable pool and links to nominate/open. Mystery identities remain hidden until explicitly revealed; result records contain IDs, and display names use the normal public movie filter. Closing does not mark the winner watched.

Upgrade with php db/migrate.php (no reset). It creates election_results and backfills any legacy closed elections from their retained latest ballots once. Fresh databases include the table. Run php tests/elections_test.php, php tests/movies_test.php, php tests/rcv_test.php and npm test in frontend. Election writes require an authenticated organiser and CSRF protection; all members can read history.

## Watched records

Record watched films supports existing catalogue entries and adding a past film directly as watched, without any election or ballot. A watched date is optional (unknown stays unknown); a related election is optional and may have a different winner or candidate list. A closed result links to this form with its winner preselected, which can be changed. One record per movie can be corrected without duplicating it. Recording is atomic and sets status to watched, excluding the film from future election snapshots while preserving existing snapshots, revisions and stored results. Mystery reveal is separate, including after watching. Only organisers can record or correct watched films; the server records the signed-in organiser as the actor. Watched history keeps aliases private and links to related elections. Run php db/migrate.php to add movie_watches without changing existing data; legacy watched films remain visible with unknown dates. Tests: php tests/watched_test.php.

## Authentication and permissions

Personal links are single-use and expire after seven days. Redeeming one remembers that device for 90 days. Link and device tokens are random secrets stored only as SHA-256 hashes in SQLite. The link secret is a URL fragment: the login page removes it from the address bar and submits it only when Sign in is pressed. Opening a link alone does not consume it.

| Action | Member | Organiser |
| --- | --- | --- |
| Browse films, results and watched history | Yes | Yes |
| Submit/revise own ballot and nominate | Yes | Yes |
| Reveal own mystery nomination | Yes | Yes |
| Reveal someone else's mystery | No | No |
| Open/close elections and record/correct watched films | No | Yes |
| Generate personal links and revoke devices/links | No | Yes |
| Add/rename members, change roles and activate/deactivate | No | Yes |

Each request reloads active status and role from the database. Submitted user IDs never select the voter, nominator or watch recorder. State-changing requests require CSRF protection. Account provides sign-out for the current device; organiser revocation removes all devices and unused links for that member, preserving their ballots. Creating a new link replaces unused links but keeps existing devices signed in.

### Install or upgrade

1. Run `php db/migrate.php` to add authentication tables and roles without resetting existing data. Existing members default to `member`.
2. Serve over HTTPS. Cookies use HttpOnly and SameSite=Strict, with Secure on HTTPS. For local Apache/hosts-file development only, create the empty ignored file `var/allow-local-http`; it permits HTTP only when PHP sees the direct client as `127.0.0.1` or `::1`. Forwarded headers cannot enable this exception. Do not deploy the marker.
3. Choose an existing member as initial organiser and run the command below, substituting their exact display name and the site's public-directory URL. Open the generated private HTML file locally and follow its link. The same CLI command supports organiser recovery; it is never accessible through HTTP.

```text
php db/create-login.php --user "MEMBER NAME" --base-url "https://YOUR_HOST/public" --output "var/organiser-login.html"
```

4. Open Account → Manage members and login links to create links for other members and share them privately. Each extra device needs a fresh link. Delete the private bootstrap HTML once used; it is ignored by Git and its directory is denied by Apache.

Apache must honor the supplied `.htaccess` rules; another server needs equivalent protection for private directories. Organisers can create and manage members through Account → Manage members and login links. No external identity provider, email delivery or API is involved.

Validation: `php tests/auth_test.php`, the existing elections/watched/movies/RCV PHP tests, and `npm test` in `frontend`. HTTP checks should use an isolated copy/database and separate cookie sessions: anonymous requests redirect (ballot API returns 401), members receive 403 for organiser writes and management, missing CSRF gives 403, forged actor IDs cannot impersonate, one-time links cannot be replayed, and revocation invalidates an existing login. Confirm that signing in restores only that member's current ballot and preserves the draft snapshot rules.

## Nomination management

The nominations page now has a Your nominations list and owner-only Edit nomination links. An active nominator can correct title, release year, poster, synopsis, pitch and an existing mystery alias, including after watching or an election closes. Corrections update the shared movie metadata shown in history; nominate a new film rather than replacing an existing film's identity.

Private editor records are available only to their nominator, never to other members or organisers. Public catalogue data still uses the mystery filter. Saving cannot change ownership, status, eligibility, ballots or reveal state. Public films cannot be hidden retroactively, and revealed mysteries cannot be re-hidden. Reveal remains the separate confirmed action.

An editor carries a fingerprint of the movie it loaded; saving checks it inside a serialized write transaction. A newer edit, reveal or watched-status change rejects a stale save. Validation failures retain typed text. Reload a stale editor after copying any unsaved text you want to keep.

No database migration is required. Test with `php tests/nominations_test.php` and `php tests/movies_test.php`. Exercise by signing in, choosing Nominations → Your nominations, editing a pitch and checking it in Details. Try opening the editor twice and saving in both; the second save must report a stale edit. Other users must receive 403 if they request that editor directly. Majority removal votes are described below.

## Majority removal votes

Open Removal votes from voting or nominations. Any active member can propose removal with a public reason; this counts as their support. Each active member has one changeable Support removal / Keep film response. The request passes immediately when support reaches floor(active members / 2) + 1, counting all active members rather than only respondents. Pending support and threshold use current active membership, evaluated on each response. Completed decisions retain their passing totals and cannot be reversed by changing a response. Organisers have no extra voting weight.

Passing marks the movie removed and records exclusions only for currently open elections containing it. Original election_movies rows and every ballot revision remain untouched. Both RCV engines already skip candidates absent from their eligible set, so existing preferences transfer naturally. New elections exclude removed films. Closed elections and stored results remain unchanged, even if the same film is removed later. A watched film still on the open ballot can be removed by vote. No reveal is triggered, and request lists use public mystery metadata. Reasons are public and must not contain spoilers. Removing every candidate leaves no winner and disables ballot submission.

A stale ballot POST receives HTTP 409 / candidates_changed with current eligible IDs and creates no revision. The browser removes those films from its current draft, retains the relative order, rebuilds the round preview, and asks for review and a second submission. It does not fetch newer votes or replace the committed baseline. Another intervening removal causes another review. Closure still rejects submissions with election_closed. The page-load committed-result section remains explicitly a snapshot.

Upgrade with php db/migrate.php; this adds removal_requests, removal_votes and election_removals without changing existing data. Fresh schema includes them. Removal, election lifecycle and ballot writes share BEGIN IMMEDIATE to serialize races. Tests: php tests/removals_test.php, the existing PHP suites, and npm test in frontend (including candidate reconciliation). To exercise, use separate signed-in members to reach a majority while another tab holds a draft; its first submit must request review without saving, and its next submit must save only the remaining ranking. Open an earlier closed election to confirm its result is unchanged.

## Member management

Account → Manage members and login links lets organisers add members, rename accounts, change roles, deactivate/reactivate membership, and manage invitations/devices. New accounts start as active members; promotion is an explicit edit. Names must be nonempty, at most 100 characters, and cannot duplicate an existing account (including inactive accounts; ASCII case variants compare equal).

Updates retain the same user ID and all ballots, nominations and removal responses. Deactivation deletes login links and remembered sessions; reactivation requires a fresh link and never restores the old credentials. Role and name changes are reflected on the next authenticated request. No accounts are deleted. Existing election ballots remain counted; active membership affects pending removal counts and thresholds under the existing next-response rule. Completed removal decisions and closed results stay unchanged.

Every create/update checks organiser permission inside BEGIN IMMEDIATE. Member edits include a version fingerprint so stale tabs cannot overwrite newer changes. The final active organiser cannot be demoted or deactivated; assign another active organiser first. Inactive organisers do not satisfy that safeguard. Self-demotion redirects to Account; self-deactivation signs out. Explicit device revocation/sign-out can still require CLI recovery for a sole organiser.

No database migration is required. Tests: php tests/members_test.php and php tests/auth_test.php. Exercise using an isolated account: add it, create its personal link, rename it, deactivate and verify its signed-in device loses access, then reactivate and issue a fresh link. Try demoting the only active organiser and saving two stale edits; both must be rejected without changing records.

## Movie search and metadata import

The nomination page can search TMDB by title and optional release year, then copy a selected result's title, year, synopsis and poster into the editable form. The first 20 results are shown; refine the title/year if needed. Import preserves the member's pitch and mystery settings. Selection performs no database write; only Save nomination persists a movie. Missing fields stay editable and manual entry remains available when search is unconfigured or unavailable. Validation errors retain the draft.

### Configure TMDB

Apply for developer API access in your TMDB account settings, then use the API Read Access Token (the bearer token, not the shorter v3 API key). Put only that token in var/tmdb-token.txt, as plain UTF-8 text without quotes. Refresh the nominations page. The file is ignored by Git and blocked from HTTP by the existing var/.htaccess rule. Keep your production copy private too. No database migration is required; PHP cURL with working HTTPS certificate verification is required for live search.

Search is explicitly requested by a signed-in member through a CSRF-protected POST. PHP contacts the fixed TMDB HTTPS endpoint with the bearer token in a header; the browser never receives the credential. Requests have connection/overall timeouts, bounded response size and no redirects. Error responses do not expose provider bodies or credentials. Search terms are sent to TMDB only when the user presses Search; existing catalogue entries, including hidden mysteries, are never searched or refreshed automatically. The importer is available for new nominations and organiser-only historical watched entries. Existing film metadata can still be corrected through the owner-only editor.

Imported metadata is copied into the ordinary movie fields, not kept in sync with TMDB. Mystery filtering continues to remove private title/year/poster/synopsis from shared views. No external provider ID is exposed for hidden films. The Credits page includes TMDB's approved logo and required attribution; retain it when using their data/images. The local logo is the unmodified Primary long (blue) SVG from their official branding page.

Provider references: [authentication](https://developer.themoviedb.org/docs/authentication-application), [movie search](https://developer.themoviedb.org/reference/search-movie), [image URLs](https://developer.themoviedb.org/docs/image-basics), and [attribution](https://developer.themoviedb.org/docs/faq).

Tests: php tests/metadata_test.php, php tests/movies_test.php and npm test in frontend. Fixtures cover provider failures, missing data, safe poster paths, credential handling and import field boundaries. The browser search/import/mystery-save flow is checked with an isolated fake provider; live search has also been verified with the locally configured TMDB token.

## Importing previously watched films

Under Watched films → Add a film we already watched, organisers can use the same TMDB search and import flow. Selection fills the new film's title, year, synopsis and poster while preserving the chosen watched date and optional election link. Review the fields, then Add to watched films; searching and importing alone write nothing. Use Record an existing film when the movie is already in the catalogue.

The film and watched record are saved together through the existing watched service. No ballots are invented, no election candidates are added, and the movie is immediately excluded from future elections. Manual entry remains available; validation errors retain the new-entry draft. Regular members can still browse watched history but cannot see or submit the organiser forms. No migration or additional API setup is needed. Tests cover watched metadata persistence, date/election preservation, validation rollback and the shared importer.

## Known bugs

See [BUGS.md](BUGS.md). Duplicate real-film entries through the previously watched new-film flow are deliberately parked at low priority. Leave the implementation and existing duplicate records intact until that fix is explicitly taken on.

## Ballot history

Explore ballot history from an election’s result or the All elections list. All signed-in members can step through submissions, inspect each ranking and calculated RCV rounds, and see how revisions replace the same member’s vote. Step zero shows no ballots. Submission IDs provide deterministic order even when timestamps share a second. Reload explicitly to see new submissions; the voting-page draft baseline is unaffected.

History reads a consistent database snapshot and uses the existing PHP RCV engine. Calculations use that election’s remaining candidates throughout: if films were removed, the page explicitly describes these as recalculations rather than exact pre-removal outcomes. Precise removal/ballot event replay remains future work because existing timestamps do not establish an order within a second. Closed history ends at each voter’s frozen revision, and the stored final result remains authoritative. Names use current member names and public movie labels; hidden mysteries stay hidden. No schema migration is needed.

Tests: `php tests/history_test.php` plus the existing election, removal and movie tests. Exercise with two members and several revisions, step back to zero, inspect a removed film’s original ranking, and compare a closed election with its frozen result. Animation remains parked.

## Navigation and presentation

All signed-in pages share four navigation areas: Vote & results, Films, Elections, and Club. Films contains nominations, watched records and removal votes; Elections contains the election list, controls and ballot history; Club contains the account, credits and organiser-only member directory. The current area is highlighted, with related pages beneath it. Context-specific links retain their election or movie IDs. Navigation is rendered by `src/Navigation.php`; authentication and permissions remain enforced by each page.

Long nominations, watched and member pages have On this page shortcuts. Member forms use native disclosures, opening automatically after a rejected edit so the entered values stay visible. Keyboard users can skip the shared navigation and open disclosures with standard controls. Layout and navigation wrap on narrow screens without JavaScript. The existing ballot preview, graph, round tables and snapshot behaviour remain in place.

Validation: PHP lint for the changed pages; existing auth, members and nominations suites; isolated organiser/member HTTP checks across all signed-in views, including rejected member edits; desktop and phone-width browser checks for navigation and voting. No migration or frontend build is required. Fake AI remains parked; duplicate watched-film entries remain documented in BUGS.md.

## Catalogue details and vertical round graph

The film catalogue shows a poster (or neutral artwork) and clickable Title (year) when a year is known. Clicking opens the same native detail flyout used by the ballot, with pitch and synopsis. Mystery entries retain their alias, neutral artwork and hidden year/synopsis. Edit and reveal actions remain separate. The shared dialog markup lives in src/movie-dialog.php; catalogue JSON contains only Movies::publicView data.

The live draft chart now uses vertical bars with stable film colours and positions, a consistent scale across rounds, and clickable film labels. Previous/Next and Replay/Pause retain their existing behaviour. Bars animate in height; reduced-motion preferences disable transitions. On narrow screens or with many candidates, only the graph scrolls horizontally. The detailed round tables remain available below.

The dashed Club trend line and index are deliberately unscientific, derived independently of ballots and vote totals. They never feed into the RCV engine. Exact counts, elimination states and transfer explanations still come from the existing round data. A final surviving candidate is labelled as winner without inventing a further tally.

Validation: npm test in frontend (including replay cancellation, fixed scale, final survivor and decorative-trend independence), PHP movie tests, and isolated desktop/mobile browser checks for catalogue details, mystery filtering, graph labels and live draft dragging. Run npm run build in frontend after changing TypeScript; no database migration is needed. Clickable Title (year) links now extend across lists and historical results.

## Film details and discussions

Film titles are consistently displayed as Title (year), omitting unknown or hidden years. Titles in ballots, graph/round results, nomination lists, watched history, removal requests and election history open the shared detail flyout. Native film selectors keep their normal selection behaviour, with a separate Details of selected film button. Edit actions are separate from reading details.

The flyout retains the optional nominator pitch and adds one comment per member per movie (up to 2,000 characters). Members can save, edit or delete only their own comment; organisers have no moderation override. Comments belong to the movie ID and survive watching, removal, election closure and new elections. Deactivated members’ existing comments remain visible. Duplicate movie identities remain the separate deferred issue in BUGS.md.

Opening a flyout fetches public film details and comments without rebasing the voting page’s ballot snapshot. Comment writes require an authenticated active member and CSRF, with ownership derived from the session. A composite key enforces one comment per movie/member. Version tokens reject stale updates/deletes and simultaneous first submissions; refreshing after a conflict preserves the local draft for review. Drafts also survive switching films within the same page, but are not persisted across page reloads/navigation. Slow responses from a previous film cannot replace the current discussion. Comments are plain text and public to the club; mystery metadata remains filtered by Movies::publicView.

Upgrade with php db/migrate.php (never db/init.php on an existing database), then npm run build in frontend. The additive migration creates movie_comments; it does not modify elections, ballots, nominations or removal decisions. Fresh databases include the table. Validate with php tests/comments_test.php and npm test in frontend, plus the existing PHP suites. Browser checks should cover a member’s own save/edit/delete, a competing edit in another tab, stale drafts, mystery films, and details from watched and historical results.

## Next movie night announcements

A shared banner on signed-in pages shows the announced date and film, distinguishing an election winner from a direct organiser choice. Vote & results → Next movie night lets organisers announce an election winner, choose a catalogue film directly, change only the date, or clear the banner. Existing election controls also accept an optional movie-night date when closing: with a date they close and announce; without one they retain the previous close-only behaviour. A closed, non-cancelled election can be announced later. An election with no winner cannot be announced.

A direct choice replaces the announcement and cancels the currently open election in the same BEGIN IMMEDIATE transaction. Its status becomes closed and an election_cancellations record distinguishes cancellation from a completed vote. Exact ballot revisions and the tally at cancellation are retained for reference, with cancellation explicitly labelled in election/results/history views. Cancelled elections cannot subsequently supply a democratic announcement. New ballot submissions are rejected after cancellation. Direct scheduling does not change film voting status, including for watched/removed catalogue entries; it does not reinstate them in the nomination pool.

Announcements are independent of watched records, film eligibility and mystery reveals. Date-only changes preserve the original selection source and do not cancel voting. Clear the announcement when no longer needed; clearing never reopens an election or marks a film watched. Past dates are labelled Last announced movie night rather than presented as upcoming. New dates must be today or later in Europe/London. Names/years/posters/synopses continue to use public mystery filtering.

Writes require an active organiser and CSRF. Submitted announcement and open-election versions are checked under the write lock, so stale forms cannot overwrite a newer plan or cancel an election opened since page load. Announcement records are retained as an audit trail, including replacements and clearing. A winner announcement freezes the result and creates the plan atomically; failure rolls both back. Elections::closeInTransaction is an internal composition method and must only be called while the caller holds the existing BEGIN IMMEDIATE transaction.

Upgrade with php db/migrate.php; the additive movie-nights.sql migration creates movie_night_announcements and election_cancellations. Do not run db/init.php on an existing database. No frontend build is needed. Tests: php tests/movie_nights_test.php plus existing PHP suites; isolated HTTP/browser checks cover democratic closure, a direct override with cancellation, stale submissions, permissions, CSRF and mobile banner layout.
