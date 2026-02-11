# Karaoke OS (Local)

Simple local-first karaoke library browser + usage tracking.

**Version:** 0.1.80

## What it does
- Public can browse songs (artists, languages, Top 100 rankings).
- Logged-in users can access the Google Drive MP4 link (and every play is logged for analytics).
- Every play is logged for analytics (by song + artist).
- Admin can add/edit songs and view analytics.
- Language display uses Square Flags icons (vendored under `assets/vendor/square-flags/`).

## Adding songs (fast)
Admin only needs to enter:
- Title
- Artist
- Google Drive File URL/ID

The app auto-fetches album + cover via iTunes (fallback: MusicBrainz) and warns about duplicates before saving.

## Maintenance (backfill metadata)
Some metadata is fetched lazily when songs/artists are added. To retroactively fill missing `genre`/`year` and artist images, run:

- Fill missing song `genre/year` + fetch missing artist images + cache external images locally:
  - `php scripts/backfill-metadata.php --songs --artists --cache-artists --limit=300 --sleep-ms=250`
- Preview what would change (no DB writes):
  - `php scripts/backfill-metadata.php --songs --artists --cache-artists --dry-run`
- Force overwrite `genre/year` (songs only):
  - `php scripts/backfill-metadata.php --songs --force --limit=200`

## Setup (XAMPP)
1. Ensure Apache is running in XAMPP.
2. Open `http://localhost/karaoke-os/setup.php` (localhost only).
3. Login at `http://localhost/karaoke-os/?r=/login`.

## Default accounts (created by setup)
- Admin: `admin` / `admin12345`
- User: `user` / `user12345` (not paid/verified by default)

Change these passwords immediately.

## Google Drive integration (required for play)
This build redirects users to the saved Drive URL on play. Make sure your Drive files are shared appropriately (e.g., “Anyone with the link can view”) for your intended access model.

## Email verification (SMTP via PHPMailer)
Open Admin → Email (SMTP) and configure your SMTP host/port/credentials/from address, then send a test email.

## LLM Song Add API (optional)
This is a simple JSON API so an LLM/tool can add songs without using the admin UI.

1. Set an API key in `config.local.php`:
   - `define('LLM_API_KEY', 'change-me');`
2. Call the endpoint (example):
   - `POST http://localhost/karaoke-os/?r=/api/llm/songs`
   - Header: `Authorization: Bearer change-me`
   - JSON body:
     - `{"title":"Honey Honey","artist":"ABBA","drive":"https://drive.google.com/file/d/FILE_ID/view"}`

See `API_DOC.md` for full details (auth, errors, examples).

## Versioning (bump on every update)
- Bump patch version: `php scripts/bump-version.php patch`
- Optional auto-bump on `git commit`:
  - `git config core.hooksPath scripts/githooks`
