# Karaoke OS (Local)

Simple local-first karaoke library browser + usage tracking.

## What it does
- Public can browse songs (artists, languages, Top 100 rankings).
- Logged-in users can access the Google Drive MP4 link (and every play is logged for analytics).
- Every play is logged for analytics (by song + artist).
- Admin can add/edit songs and view analytics.

## Adding songs (fast)
Admin only needs to enter:
- Title
- Artist
- Google Drive File URL/ID

The app auto-fetches album + cover via iTunes (fallback: MusicBrainz) and warns about duplicates before saving.

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
