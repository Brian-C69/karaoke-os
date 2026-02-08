# Karaoke OS API Docs

## Authentication (LLM API key)

The LLM endpoints use a shared secret key.

1. Generate a key (example):
   - `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`
2. Set it in `config.local.php` (app root, same folder as `index.php`):
   - `define('LLM_API_KEY', 'paste-your-key-here');`
3. Send it on requests using either:
   - `Authorization: Bearer <key>` **(recommended)**
   - `X-Api-Key: <key>`

If `LLM_API_KEY` is empty, LLM endpoints return `501 llm_api_not_configured`.

## Endpoints

### Add song (LLM)

`POST /?r=/api/llm/songs`

Adds a new song with the same duplicate rules as the admin UI:
- Duplicate if same **Title + Artist** (case-insensitive), OR same Drive file/link.

**Request JSON**
- Required:
  - `title` (string)
  - `artist` (string)
  - `drive` (string) — Google Drive URL or file id
- Optional:
  - `language` (string)
  - `album` (string)
  - `cover_url` (string)
  - `is_active` (boolean) — default `true`
  - `dry_run` (boolean) — if `true`, only validates + checks duplicates (no insert)

**Success responses**
- `201`:
  - `{ ok: true, mode: "add", id: <int>, song: <song row> }`
- `200` when `dry_run=true`:
  - `{ ok: true, mode: "check", normalized: {...}, matches: [] }`

**Error responses**
- `400 missing_required` — missing `title`/`artist`/`drive`
- `400 invalid_drive` — drive value can’t be parsed or isn’t a safe URL/id
- `401 unauthorized` — API key missing/incorrect
- `405 method_not_allowed` — must be POST
- `409 duplicate` — returns `matches` array
- `501 llm_api_not_configured` — `LLM_API_KEY` not set

**Example (PowerShell)**

```powershell
$key = "YOUR_LLM_API_KEY"
$body = @{
  title  = "Honey Honey"
  artist = "ABBA"
  drive  = "https://drive.google.com/file/d/FILE_ID/view"
  dry_run = $true
} | ConvertTo-Json

Invoke-RestMethod `
  "http://localhost/karaoke-os/?r=/api/llm/songs" `
  -Method Post `
  -Headers @{ Authorization = "Bearer $key" } `
  -ContentType "application/json" `
  -Body $body
```

## Security notes
- Keep `LLM_API_KEY` in `config.local.php` only (don’t commit it).
- Treat this API as **admin-level** access (it can write to your database).
- If you expose the app beyond LAN, add additional protections (IP allowlist, VPN, reverse-proxy auth).

