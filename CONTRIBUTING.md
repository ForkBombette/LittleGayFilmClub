# Contributing

You can work on this project with an editor, PHP, Node/npm and Git. AI tools are optional; contributors remain responsible for understanding and testing what they submit.

Start with the [README setup](README.md#run-a-fresh-local-checkout), [behaviour reference](docs/BEHAVIOUR.md) and [roadmap](docs/ROADMAP.md). The codebase favours small, readable changes over frameworks or extra infrastructure.

## Where things live

| Path | Responsibility |
| --- | --- |
| `public/` | Web entry points, HTML forms, JSON endpoints, CSS and static assets. This is the web server's document root. |
| `src/` | PHP application rules, persistence, authentication, shared rendering and authoritative RCV. |
| `src/bootstrap.php` | Explicitly loads the PHP classes; there is no Composer autoloader. |
| `frontend/src/` | Browser TypeScript: dragging, preview RCV, charts, film details, comments and commentary. |
| `frontend/dist/`, `public/assets/js/` | Generated JavaScript; do not edit by hand. |
| `db/schema.sql`, `db/seed.sql` | Fresh development database structure and sample data. |
| `db/migrate.php` and related SQL/classes | Upgrades to existing databases. |
| `tests/` | PHP tests and shared RCV fixtures; the HTTP session test is PowerShell. |
| `frontend/test/` | Node tests of compiled browser modules. |
| `var/` | Private, local runtime data and configuration. Never commit databases, tokens or login links. |
| `release/` | Generated upload packages. |

Useful starting points: `src/Rcv.php` and `frontend/src/rcv.ts` for counting; `src/Elections.php` for election lifecycle; `frontend/src/app.ts` for the ballot; `src/Movies.php` for film visibility; `src/Navigation.php` and `public/styles.css` for shared presentation.

## Trace a request: opening an election

1. `public/elections.php` starts the PHP session and checks the signed-in member.
2. On a page load it queries `$pool` to render the proposed candidates.
3. On an open-election POST it checks organiser permission and CSRF, then calls `Elections::open()`.
4. `src/Elections.php` queries the eligible IDs again **inside the write transaction**, creates the election and saves those IDs into `election_movies`.
5. The page redirects to the ballot. Its PHP-rendered bootstrap data supplies the browser preview.

Both PHP files run on the server. The distinction is presentation/request handling versus application rules. Changing the displayed pool alone does not change the candidates saved when opening an election. Keep both in agreement, ideally through shared eligibility logic when changing that rule. Do not trust a previously rendered page as the authority for a later write.

The browser's preview is speculative. Ballot submission goes through `public/submit_ballot.php` and the PHP election service. Never make browser validation the only enforcement of a rule.

## Tests

Run from the project root. PHP test files use in-memory or temporary databases, not `var/lgfc.sqlite`; tests involving concurrent connections use temporary SQLite files. TMDB unit tests use fixtures and do not require a live token.

PowerShell:
```powershell
Get-ChildItem tests/*_test.php | ForEach-Object {
    php $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "Failed: $($_.Name)" }
}
```

POSIX shell:
```sh
for test in tests/*_test.php; do
    php "$test" || exit 1
done
```

Frontend (all platforms):
```sh
cd frontend
npm test
cd ..
```

HTTP session/CSRF diagnostics (PowerShell 7):
```powershell
./tests/web_test.ps1
# If PHP is not on PATH:
./tests/web_test.ps1 -Php C:/xampp/php/php.exe
```

That test starts and stops its own local PHP server, uses a temporary session directory, and never touches the application database. Temporary test files remain in the OS temporary directory.

For a changed PHP file, also run `php -l path/to/file.php`. Choose relevant tests during development; run both PHP and frontend suites before submitting changes that affect voting across the two implementations. The shared engine fixtures are in `tests/fixtures/rcv_cases.json`.

For UI changes, check the actual interaction, a narrow viewport and keyboard operation in your disposable local instance. For permissions or concurrency changes, use separate browser profiles/tabs to exercise both allowed and rejected requests. Automated tests do not replace those checks.

## Rules worth preserving

- Keep PHP RCV authoritative and the TypeScript engine in agreement; add edge-case fixtures before changing counting rules.
- Preserve candidate snapshots, historical ballot revisions and stored closed results. A revision replaces a member's effective vote, not their history.
- Retain the page-load baseline while editing a draft; do not silently refresh other members' ballots.
- Filter mystery data on the server with `Movies::publicView`; hiding text in the browser is insufficient.
- Derive the acting member from authentication, enforce permissions server-side, and check CSRF for writes.
- Use PDO parameters for input values and escape output. Comments and commentator titles are plain text.
- Keep lifecycle writes serialized. `Elections::closeInTransaction()` requires the caller to hold the existing `BEGIN IMMEDIATE` transaction.
- Schema changes need both fresh-database support and a non-destructive migration for existing data.
- Keep the fake AI and trend line independent of votes, thresholds and results.

The [behaviour reference](docs/BEHAVIOUR.md) explains the details. [BUGS.md](BUGS.md) records deliberately deferred duplicate-film behaviour; agree on matching/reinstatement policy before tackling it, and do not silently clean up existing records.

## A useful contribution

Keep one concern per commit. Describe the behaviour before and after, the tests you ran, and any migration or deployment steps. If a test or manual check was not run, say so. Include reproduction steps for bug reports, using sample data rather than the club's private database.

Discuss changes to voting rules, permissions or film identity before implementing them. There is no requirement to use an LLM, and using one does not waive review or testing. `AGENTS.md` is guidance for coding assistants; the human-facing setup, rules and contribution process live in these linked documents.

Keep documentation current: README for getting started, this file for development, the behaviour reference for current rules, the roadmap for future work, and DEPLOYMENT for hosting. Avoid appending another completed feature to a stale “next steps” list.
