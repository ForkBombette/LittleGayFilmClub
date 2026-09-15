# Uploading to cPanel

## Layout

Upload the application into `/home/geexnet/littlegayfilmclub`. Set the domain document root to `/home/geexnet/littlegayfilmclub/public` in cPanel Domains. The address is then `https://littlegayfilmclub.geekplex.net/`, with no `/public` in the URL. Keep `src`, `db` and `var` outside the document root. Enable HTTPS for the domain using cPanel's certificate controls and Force HTTPS Redirect.

PHP 8.1+ and PDO SQLite are required. PHP cURL and working HTTPS certificates are needed for TMDB search. Node/npm are only needed locally to build assets. PHP must be able to write both `var` and its database file (including creating SQLite journal files); use account ownership/host-recommended permissions, not world-writable permissions.

## Build the upload

From the local project root in PowerShell:

```powershell
./package-hosting.ps1
```

This runs the frontend tests/build and creates a timestamped ZIP in `release`. Extract its contents directly into `/home/geexnet/littlegayfilmclub` (no extra enclosing folder). Include hidden `.htaccess` files. The package contains PHP, SQL migrations, static assets and compiled JavaScript. It excludes the database, backups, secrets, generated login links, local HTTP marker, Git, node_modules and development sources/tests.

## First upload: bring the real data separately

Pause local writes and create a consistent database snapshot with SQLite's `VACUUM INTO` or backup facility. Alternatively stop local Apache before copying `var/lgfc.sqlite` so no writes are in progress. Upload that snapshot as `/home/geexnet/littlegayfilmclub/var/lgfc.sqlite`. Never copy a changing database or run `db/init.php` over real data.

Transfer `var/tmdb-token.txt` privately if using TMDB. Do not upload `var/allow-local-http`, private login HTML files or database backups. A migrated copy of the current dev database already has the current schema.

For upgrades, back up the live database and run this from cPanel Terminal/SSH using the site's PHP version:

```sh
cd /home/geexnet/littlegayfilmclub
php db/migrate.php
```

Do not expose migration or setup scripts through a browser if Terminal is unavailable; use the host's supported CLI mechanism. For this first upload, no server migration is needed if transferring the current migrated dev database.

## Sign in and verify

If needed, create a fresh organiser link from Terminal:

```sh
php db/create-login.php --user "Sophie" --base-url "https://littlegayfilmclub.geekplex.net" --output "var/organiser-login.html"
```

Download that private HTML file using cPanel File Manager, open it locally and follow the link, then delete the file. If there is no server terminal, generate the link locally before taking the database snapshot, using the production base URL; the snapshot must contain that newly issued link.

The local Windows hosts entry still points this domain at the dev machine. Remove/comment it for the production test (or use a device without that override). Visit the HTTPS domain, sign in, check the movie-night banner, film details/comments, ballot drag preview and TMDB search. Check `/assets/js/app.js` loads. Requests to `/var/lgfc.sqlite` and `/src/Database.php` must not return private files; with the correct public document root they do not exist at those URLs.

## Later updates

Upload a new code ZIP, preserving the server's `var` contents. Back up the live database before migrations. Never replace it with the development database after members start using the hosted app. The packaging command deliberately includes only `var/.htaccess`. No server Node build is required.

Local Apache may also point at `D:/Dev/LittleGayFilmClub/public` for matching URLs. Existing dev URLs ending in `/public/` still work because browser asset references are relative to the page.
