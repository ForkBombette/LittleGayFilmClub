# Little Gay Film Club™

A tiny, deliberately over-engineered democratic film-night app.

I came up with the concept, thought about the concept, extended the concept, and realised that it
couldn't be Python if the plan was to just drop it on a server and run, but it was beyond my meagre
PHP skills.
So it's become an experiment in "vibe coding", while I make sure that I understand what's happening
every step of the way.
Endless thanks and blame to Nicola whose objection to selections from the **undisputable** pinnacle of
cinema (ie. 1980s sword-and-sorcery movies) formed the inspiration.

## What it does

Members nominate films, make their case, rank the candidates and revise their votes. Organisers manage membership, elections and movie nights. The application is plain PHP, SQLite and browser TypeScript: no application framework, Composer dependency or external identity provider.

- Ranked-choice voting with live draft previews, round charts and saved ballot history.
- Film search/import from TMDB, posters, mystery nominations and deliberate reveals.
- One editable comment per member per film, retained across elections.
- Majority removal votes, watched-film records and election controls.
- A dated next-movie-night banner, selected democratically or directly by an organiser.
- Single-use sign-in links supporting multiple remembered devices.
- A wholly unqualified, entirely canned ✨AI✨ commentator and a meaningless graph trend line.

The core is implemented and the app is hosted. Presentation improvements and known limitations remain; see the [roadmap](docs/ROADMAP.md) and [bug notes](BUGS.md).

## Start here

- [Contributing](CONTRIBUTING.md): code map, request flow, tests and working conventions.
- [Behaviour and voting rules](docs/BEHAVIOUR.md): what the application promises.
- [Deployment](DEPLOYMENT.md): cPanel, updates, backups and session troubleshooting.

No AI tool is required to understand, build, test or contribute. Contributions made with or without one follow the same review and testing expectations.

## Requirements

- PHP 8.1+ with PDO SQLite.
- Node.js 18+ and npm for local frontend builds/tests; neither is needed on the hosting server.
- PHP cURL with working HTTPS certificate verification for optional TMDB search.
- PowerShell 7 for the upload-package command and HTTP session test. Other development tasks also work in a POSIX shell.

The commands below assume PHP and npm are on PATH. On Windows/XAMPP, use `C:/xampp/php/php.exe` instead of `php` if needed (PowerShell: `& C:/xampp/php/php.exe ...`).

## Run a fresh local checkout

Use your own checkout and database. Run these commands from the project root unless stated otherwise.

1. **Create disposable development data.** This seeds sample members (including Sophie), films and an open election:

   ```sh
   php db/init.php
   ```

   **This deletes and recreates `var/lgfc.sqlite` if it already exists.** For an existing database, back it up and run `php db/migrate.php` instead. Do not initialise the live database.

2. Install the locked frontend dependencies and build:

   ```sh
   cd frontend
   npm ci
   npm run build
   cd ..
   ```

   The build creates `frontend/dist` for tests and publishes browser modules to `public/assets/js`. Both are generated; edit TypeScript under `frontend/src`.

3. Allow HTTP on this local loopback instance only. Create the empty, ignored marker:

   PowerShell:
   ```powershell
   New-Item -ItemType File -Force var/allow-local-http
   ```

   POSIX shell:
   ```sh
   touch var/allow-local-http
   ```

   Production requires HTTPS. Do not upload this marker; it permits HTTP only for direct loopback clients.

4. Generate a local organiser link for the seeded member:

   ```sh
   php db/create-login.php --user "Sophie" --base-url "http://127.0.0.1:8080" --output "var/organiser-login.html"
   ```

   This promotes that existing active member to organiser and creates a single-use link. It is also the organiser recovery command. With your own existing data, substitute the member's exact name.

5. Start the development server in a terminal:

   ```sh
   php -S 127.0.0.1:8080 -t public
   ```

   Open `var/organiser-login.html` as a local file, follow its link and press **Sign in**. Delete the private HTML file afterwards. The application is at `http://127.0.0.1:8080/`; PHP's built-in server is for local development only.

For Apache, point the virtual host's document root at this checkout's `public` directory. Use a separate local hostname such as `lgfc.test`, mapped to loopback, and use that origin in `--base-url`. Do not reuse the production hostname: cookies and open pages can cross between the two environments. The folder name `public` does not need to appear in the URL.

## Sign-in and permissions

An organiser creates invitations under **Club → Members**. A link can be redeemed once within seven days; the resulting device login lasts 90 days. Each additional device needs a fresh link. Issuing one replaces older unused links but **does not sign out existing devices**.

Members can vote, nominate, manage their own nominations and comments, and participate in removal votes. Organisers additionally manage members, invitations, elections, watched records and announcements. Organisers cannot edit or reveal someone else's nomination or edit their comments. The last active organiser cannot be demoted or deactivated.

Signing out affects that device. **Revoke devices and links** invalidates all of that member's sessions and unused links. Old consumed links cannot be reused; use a fresh link or the CLI recovery command.

## Optional TMDB search

Put your TMDB **API Read Access Token** in `var/tmdb-token.txt` as plain UTF-8 text, without quotes. It stays on the server and is ignored by Git. Manual nomination and watched-film entry work without it.

Search/import copies metadata into an editable form; only saving persists it. Metadata is not kept in sync with TMDB. Keep the existing Credits page and attribution when using the service. Never commit or share the token.

## Build, test and deploy

Run `npm test` in `frontend` to build and run frontend tests. PHP tests are standalone scripts, for example `php tests/elections_test.php`. [CONTRIBUTING.md](CONTRIBUTING.md#tests) gives commands for the full suite and explains the isolated HTTP test.

For hosting, `./package-hosting.ps1` tests/builds the frontend and creates a code-only ZIP in `release`. It does not run the PHP suite. Follow [DEPLOYMENT.md](DEPLOYMENT.md) for the initial data transfer and later updates; never replace live member data with a development database.
