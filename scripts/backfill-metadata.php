<?php
declare(strict_types=1);

/**
 * Backfill metadata (genre/year) + artist images.
 *
 * Usage:
 *   php scripts/backfill-metadata.php [--songs] [--artists] [--cache-artists] [--limit=200] [--sleep-ms=250] [--force] [--dry-run]
 *
 * Notes:
 * - Calls external services (iTunes, MusicBrainz/Wikidata/Wikimedia) and may take time.
 * - Re-run safely; by default it only fills missing fields.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run via CLI.\n");
    exit(2);
}

// Bootstrap expects a SCRIPT_NAME (for APP_BASE); provide a stable default for CLI runs.
if (empty($_SERVER['SCRIPT_NAME']) || !is_string($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require dirname(__DIR__) . '/src/bootstrap.php';

function arg_flag(string $name): bool
{
    global $argv;
    return in_array('--' . $name, $argv, true);
}

function arg_value(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $a) {
        if (!is_string($a)) continue;
        if (str_starts_with($a, '--' . $name . '=')) {
            return substr($a, strlen('--' . $name . '='));
        }
    }
    return $default;
}

function msleep(int $ms): void
{
    if ($ms <= 0) return;
    usleep($ms * 1000);
}

function usage(): void
{
    $msg = <<<TXT
Karaoke OS metadata backfill

Usage:
  php scripts/backfill-metadata.php [options]

Options:
  --songs            Backfill songs genre/year from iTunes (fills missing only)
  --artists          Backfill artists image_url/musicbrainz_id (fills missing only)
  --cache-artists    Cache external artist image_url locally (no lookups)
  --limit=N          Max items per section (default: 200)
  --sleep-ms=N       Delay between external calls in ms (default: 250)
  --force            Overwrite existing genre/year (songs only)
  --dry-run          Print actions without writing to DB
  --help             Show this help

Examples:
  php scripts/backfill-metadata.php --songs --artists --limit=300 --sleep-ms=250
  php scripts/backfill-metadata.php --songs --force --limit=100
  php scripts/backfill-metadata.php --cache-artists --limit=50

TXT;
    fwrite(STDOUT, $msg);
}

if (arg_flag('help')) {
    usage();
    exit(0);
}

$doSongs = arg_flag('songs');
$doArtists = arg_flag('artists');
$doCacheArtists = arg_flag('cache-artists');

if (!$doSongs && !$doArtists && !$doCacheArtists) {
    // Default: do everything.
    $doSongs = true;
    $doArtists = true;
    $doCacheArtists = true;
}

$limit = (int)(arg_value('limit', '200') ?? '200');
$limit = max(1, min(5000, $limit));
$sleepMs = (int)(arg_value('sleep-ms', '250') ?? '250');
$sleepMs = max(0, min(5000, $sleepMs));
$force = arg_flag('force');
$dryRun = arg_flag('dry-run');

$db = db();
ensure_schema($db);

fwrite(STDOUT, "Backfill starting (dry-run=" . ($dryRun ? 'yes' : 'no') . ", limit={$limit}, sleep-ms={$sleepMs})\n");

if ($doSongs) {
    fwrite(STDOUT, "\n[Songs] Updating genre/year\n");
    $afterId = 0;
    $seen = 0;
    $updated = 0;
    $noMeta = 0;
    $errors = 0;

    $sel = $db->prepare(
        'SELECT id, title, artist, genre, year
         FROM songs
         WHERE id > :after
           AND (genre IS NULL OR TRIM(genre) = \'\' OR year IS NULL)
         ORDER BY id ASC
         LIMIT :lim'
    );
    $upd = $db->prepare(
        'UPDATE songs
         SET genre = :g, year = :y, updated_at = :u
         WHERE id = :id'
    );

    while ($seen < $limit) {
        $sel->bindValue(':after', $afterId, PDO::PARAM_INT);
        $sel->bindValue(':lim', min(200, $limit - $seen), PDO::PARAM_INT);
        $sel->execute();
        $rows = $sel->fetchAll();
        if (!$rows) break;

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $afterId = $id;
            $seen++;

            $title = trim((string)($r['title'] ?? ''));
            $artist = trim((string)($r['artist'] ?? ''));
            if ($id <= 0 || $title === '' || $artist === '') {
                continue;
            }

            $currentGenre = trim((string)($r['genre'] ?? ''));
            $currentYear = $r['year'] === null ? null : (int)$r['year'];

            try {
                $meta = lookup_song_metadata($title, $artist);
                $newGenre = is_array($meta) ? trim((string)($meta['genre'] ?? '')) : '';
                $newYear = null;
                if (is_array($meta) && isset($meta['year']) && is_numeric($meta['year'])) {
                    $newYear = (int)$meta['year'];
                }
                if ($newGenre === '' && $newYear === null) {
                    $noMeta++;
                    msleep($sleepMs);
                    continue;
                }

                if (!$force) {
                    if ($currentGenre !== '' && $currentYear !== null) {
                        msleep($sleepMs);
                        continue;
                    }
                    if ($currentGenre !== '') $newGenre = $currentGenre;
                    if ($currentYear !== null) $newYear = $currentYear;
                }

                if ($dryRun) {
                    fwrite(STDOUT, "  #{$id} {$artist} - {$title} => genre=" . ($newGenre !== '' ? $newGenre : 'NULL') . ", year=" . ($newYear !== null ? (string)$newYear : 'NULL') . "\n");
                } else {
                    $upd->execute([
                        ':id' => $id,
                        ':g' => $newGenre !== '' ? $newGenre : null,
                        ':y' => $newYear,
                        ':u' => now_db(),
                    ]);
                }
                $updated++;
            } catch (Throwable $e) {
                $errors++;
            }

            msleep($sleepMs);
        }
    }

    fwrite(STDOUT, "Songs scanned: {$seen}, updated: {$updated}, no-meta: {$noMeta}, errors: {$errors}\n");
}

if ($doArtists) {
    fwrite(STDOUT, "\n[Artists] Filling missing images\n");
    $done = 0;
    $changed = 0;
    $errors = 0;

    $stmt = $db->prepare(
        'SELECT name
         FROM artists
         WHERE (image_url IS NULL OR TRIM(image_url) = \'\')
         ORDER BY updated_at DESC, id DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        if ($done >= $limit) break;
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') continue;
        $done++;
        try {
            if ($dryRun) {
                fwrite(STDOUT, "  lookup: {$name}\n");
            } else {
                $before = get_artist_by_name($db, $name);
                upsert_artist($db, $name);
                $after = get_artist_by_name($db, $name);
                if ($after && (($before['image_url'] ?? '') !== ($after['image_url'] ?? ''))) {
                    $changed++;
                }
            }
        } catch (Throwable $e) {
            $errors++;
        }
        msleep($sleepMs);
    }

    fwrite(STDOUT, "Artists processed: {$done}, updated: {$changed}, errors: {$errors}\n");
}

if ($doCacheArtists) {
    fwrite(STDOUT, "\n[Artists] Caching external images locally\n");
    if ($dryRun) {
        fwrite(STDOUT, "  dry-run: would cache up to {$limit} artists with external image_url\n");
    } else {
        $res = cache_external_artist_images($db, $limit);
        fwrite(
            STDOUT,
            sprintf(
                "  attempted: %d, cached: %d, failed: %d\n",
                (int)($res['attempted'] ?? 0),
                (int)($res['cached'] ?? 0),
                (int)($res['failed'] ?? 0)
            )
        );
    }
}

fwrite(STDOUT, "\nDone.\n");

