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
- personal-link authentication with member and organiser permissions

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

5. Follow Authentication and permissions below to configure HTTPS (or explicit loopback development access), create the first organiser link, and sign in through the `public` directory.

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

Sign in with your personal link, drag films into preference order, and submit. Each submission creates a new ballot revision rather than overwriting the old one. The results page calculates the authoritative RCV result from the latest submitted ballot for each user.

The browser also calculates a speculative result while you drag, using the committed ballots as loaded when the page opened plus your current draft. That is the first stepping stone toward the live animated election graph.

## Next likely steps

- improve ballot UI and movie cards/posters
- result graph and animation
- add movies and external metadata search
- majority removal votes (implemented)
- admin/user management (implemented)
- historical replay of ballot revisions

## Live round preview

The framework-free preview sits beside the ballot (below on narrow screens) and updates during dragging. It uses the page-load snapshot, replacing the signed-in user's ballot, never adding a second vote. Other users' submissions require an explicit reload. A successful local submission updates only that user's snapshot entry using the ranking actually sent. PHP remains authoritative; neither RCV engine's voting rules changed. Transfer counts come from consecutive engine round deltas; final survivors are labelled without inventing a tally.

Exercise: sign in, drag a film across another and watch totals before releasing. Submit and reload to compare with the authoritative result. Use a separate browser profile for a second member: their submission must not change the first browser's snapshot until reloaded. Test a narrow window as well.

## Future non-priority features

- Final polish: a non-interactive fake client-side AI commentator blob, reacting to events with canned sarcastic comments. No actual AI, API or network requests. Defer until after core work.
- Movie flyout discussions: optional nominator pitch plus at most one comment per user per movie; users can edit/delete their own comment. Discussions attach to movies and persist across elections. No priority change.
- Final visual polish / troll feature: colour-coded vertical bars with a deliberately meaningless trend line overlaid, or the closest practical effect. It may display a fictional trend value and trigger canned remarks from the fake AI commentator. This is decorative only: it must not affect RCV calculations, totals, thresholds or results. Keep parked until after core work.

## Animated round chart

The draft preview now includes a bar chart above the retained round tables. Use Previous round / Next round or Replay rounds (which becomes Pause replay). Candidate rows and the vote scale stay fixed across rounds. Reordering immediately updates the selected round and pauses replay. Reduced-motion preferences disable bar transitions. The engine and snapshot rules are unchanged.

## Movie cards and mystery nominations

Cards show posters when available and neutral artwork otherwise. Details opens a native modal flyout (Escape closes it). Movie search/import and one-comment-per-user discussions remain future work.

Run `php db/migrate.php` once when upgrading an existing database; this adds fields without replacing movies, elections or ballot revisions. Fresh `db/init.php` databases include the fields (init still resets the database). Test with `php tests/movies_test.php` and `npm test` in frontend.

Use **Nominate or reveal a film** while signed in to enter the real film details, optional pitch, and mystery alias. Mysteries require a pitch. New nominations belong to the ongoing pool, not the current election's frozen candidate list. They become eligible when a future election snapshots that pool.

A hidden mystery exposes only its alias, pitch and neutral artwork; PHP filters its title, year, synopsis and poster before rendering cards, bootstrap data and authoritative results. Sort by public title, never the hidden title. Revealing is a deliberate, confirmed action by the nominator on the nomination page; it works after an election closes and never runs automatically. Existing tabs must reload to see a reveal. The reveal is permanent and keeps the same movie ID and ballots.

Reveal ownership is checked on the server against the signed-in member, including for organisers. Apache must honor the supplied .htaccess rules, which block private database/source directories; other servers need equivalent restrictions. Metadata is never fetched for hidden films. Pitches and aliases are intentionally public: keep spoilers out of them.

## Browsing beyond the ballot

Collapsed Not in this election and Watched films lists sit beneath the ballot, with counts and empty states. Active films outside the election snapshot appear in the first; watched films outside it appear in the second. Snapshot membership takes precedence if a film’s status changes mid-election, so it stays on the ballot without duplication. Removed films are omitted. Both lists use the shared detail flyout and server-filtered mystery data. Browse-only films never enter rankings or RCV candidates. Reviews/comments remain future work and will use these same movie details. No database migration is needed.

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

### If you sign yourself out

Signing in consumes the personal link and creates a separate remembered-device session. Signing out removes that device session; revoking access removes all the member's device sessions and unused links. Neither makes an old link reusable. A sole organiser can recover using the CLI command above; keep the same existing user name to retain their ballots and nominations.

`--base-url` normally ends in `/public`, not `/public/login.php`. The command also accepts the sign-in page address and normalizes it. After running it, open or reload the newly written local HTML file and follow the new link; an already-open copy may still contain the previous link. The terminal prints the output file's full path. Running the command replaces earlier unused links, but leaves remembered devices signed in.

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
