# Application behaviour

This describes the implemented application. See the [roadmap](ROADMAP.md) for future work and [contributor guide](../CONTRIBUTING.md) for the code map.

## Elections and ballots

There is at most one open election. Opening it snapshots the active film IDs into `election_movies`; later nominations wait for a subsequent election. Each election starts with fresh ballots. New submissions append revisions, and only each member's latest revision counts as their current vote.

Counting uses single-winner instant-runoff voting (RCV). PHP is authoritative; TypeScript mirrors it for previews. A tied lowest total eliminates the candidate with the lowest numeric ID. This is the current deterministic policy, not a rule to change casually. An election with no submitted ballots has no authoritative winner.

The draft preview replaces the signed-in member's ballot in the page-load snapshot; it never adds a second vote. Other members' newer submissions appear only after an explicit reload. A successful submission updates the local baseline using the ranking actually sent, even if the draft was changed while saving. The committed-result section remains the result at page load.

Closing stores the exact result and latest ballot revision IDs. Closure is permanent; repeated closure is harmless. Open, close, submission and removal writes are serialized with SQLite transactions. Submitting to a closed election returns HTTP 409 and saves no revision. Closing does not reveal a mystery or mark a film watched.

## Films, mystery nominations and discussion

Nominations belong to the ongoing catalogue. Members can edit only their own nominations, including title, year, synopsis, poster, pitch and an existing mystery alias. A stale edit is rejected if the record changed since loading. Editing cannot change ownership, status, eligibility or reveal state.

A mystery requires a pitch and exposes only its public alias, pitch and neutral artwork. PHP removes the true title, year, poster and synopsis before sending shared data to the browser. Sort using public labels. Reveal is a separate, permanent action by the nominator; organisers cannot reveal another member's film. Election closure and watching never reveal it automatically. Public films cannot be made secret retroactively.

Shared detail flyouts show Title (year) when known/public, the optional nomination pitch and one comment per member per movie. Members can edit/delete only their own comment, up to 2,000 characters. Organisers have no comment override. Comments persist across elections, watching and removal; inactive members' existing comments remain visible.

Comment versions reject stale updates/deletes and competing first submissions. Refreshing after conflict preserves the local draft for review. Drafts survive switching films within a page but not page reloads. Fetching film details/comments does not refresh the voting baseline. Pitches, comments and removal reasons are public to the club; avoid mystery spoilers.

TMDB search is explicit and server-side. Import fills editable form fields; it does not save a movie, overwrite the pitch/mystery settings or continually synchronise metadata. Manual entry works without TMDB. Provider IDs are not currently stored, and duplicate identity handling remains deferred.

## Majority removal

Any active member can propose a removal with a reason, which counts as their support. Each active member has one changeable response. Passing requires `floor(active members / 2) + 1` supporters: a majority of all active members, not just people who responded. Organisers have no extra weight.

Pending totals use current active membership and are evaluated on each response; a membership change alone does not immediately pass a pending request. A completed decision retains its passing totals and cannot be reversed by changing a response.

Passing marks the film removed and records exclusions for open elections containing it. Original candidate snapshot rows and ballot revisions are retained. Preferences transfer through the remaining eligible set. Closed elections and stored results stay unchanged. Even a watched film still present in an open election may be removed by vote.

A stale submission returns HTTP 409 / `candidates_changed`. The browser removes those films from the draft, retains the order of survivors and asks the member to review and submit again. It does not fetch newer ballots or save automatically. If all candidates are removed, there is no winner and submission is disabled.

## Watched films and movie nights

Organisers can record an existing catalogue film as watched or add a previously watched film directly, without inventing an election. A watched date and related election are optional; the watched film need not be that election's winner. One watched record per movie ID can be corrected. Marking watched excludes it from future election snapshots, not from an existing election.

The shared announcement banner shows a date and film, distinguishing an election winner from a direct organiser choice. Organisers can announce a winner while closing (optional date in election controls), announce a closed non-cancelled winner later, or choose a catalogue film directly. An election without a winner cannot supply an announcement.

A direct choice cancels any currently open election atomically. Ballots and the tally at cancellation remain as a clearly labelled archive; that cancelled election cannot later supply a democratic announcement. Direct scheduling can use watched or removed catalogue entries without reinstating their voting status.

Changing only the date preserves the selection source and does not cancel an open election. Clearing neither reopens elections nor marks anything watched. Announcement changes append records; the latest record is current, and clearing appends a null movie ID with source `cleared`. Old dates are labelled “Last announced movie night”. New dates must be today or later in Europe/London.

Announcement writes require organiser permission, CSRF and matching announcement/open-election versions. Stale forms cannot overwrite a newer plan or cancel an election opened since page load. Announcement, watched status, eligibility and mystery reveal are separate.

**Currently, being announced alone does not exclude an active film from a new election.** The proposed change to that rule is not implemented in the committed code; see the roadmap.

## Accounts and permissions

Single-use personal links expire after seven days. Redeeming one creates a separate 90-day device session. Secrets are random, with only SHA-256 hashes stored in SQLite. Creating a link replaces earlier unused links but preserves existing device sessions. Multiple devices are supported.

Authentication rechecks active status and role on each request. Sign-out removes only the current device session; organiser revocation removes all that member's devices and unused links. Deactivation also revokes access; reactivation requires a fresh link. Existing ballots and nominations remain. Names/roles can change without changing identity.

Organisers manage elections, announcements, watched records, members and invitations. They cannot edit/reveal another person's nomination or edit their comment. The final active organiser cannot be demoted or deactivated, though signing out or explicitly revoking devices can still require CLI recovery.

Member updates check permissions and stale versions inside a write transaction. Names must be nonempty, at most 100 characters and unique against active and inactive accounts (ASCII case variants compare equal).

The remembered-login cookie is distinct from PHP's temporary session used for CSRF. All state-changing requests require CSRF. See [deployment troubleshooting](../DEPLOYMENT.md#troubleshooting-repeated-reloadsession-errors) for support references and safe session diagnostics.

## Results, history and presentation

Closed results remain available by election ID after new elections open. With no open election, the main page shows the latest closed one; with no elections it offers the catalogue and relevant links.

Ballot history steps through revisions, starting with no ballots and ending at the frozen revisions for a closed election. Earlier calculations use the election's remaining candidates throughout: removals make them recalculations, not exact pre-removal outcomes. Existing timestamps do not establish precise ordering between removals and submissions in the same second. Stored closed results remain authoritative. Names and film labels reflect current public metadata.

The live chart has fixed candidate positions, vertical coloured bars, round stepping/replay and reduced-motion support. Counts and transfers come from engine rounds. A final survivor is labelled without inventing another tally. Detailed round tables are retained. The decorative trend/index never feeds into counting.

The open-ballot commentator chooses canned lines locally after completed changed drags and submission outcomes. It prioritises changed draft winners/elimination order, uses only public film labels, and has no API, persistence or influence on the vote. It does not monitor other members or announce events elsewhere in the club.
