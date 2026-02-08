<?php
declare(strict_types=1);

function lookup_song_metadata(string $title, string $artist): ?array
{
    $title = trim($title);
    $artist = trim($artist);
    if ($title === '' || $artist === '') {
        return null;
    }

    $candidates = lookup_cover_candidates($title, $artist);
    if (!$candidates) {
        return null;
    }

    // Pick best candidate (prefer iTunes exact-ish track match).
    return $candidates[0];
}

function lookup_cover_candidates(string $title, string $artist): array
{
    $out = [];
    foreach (itunes_candidates($title, $artist, 10) as $c) {
        $out[] = $c;
    }
    if ($out) {
        return $out;
    }
    foreach (musicbrainz_candidates($title, $artist, 5) as $c) {
        $out[] = $c;
    }
    return $out;
}

function itunes_candidates(string $title, string $artist, int $limit): array
{
    $term = trim($title . ' ' . $artist);
    $url = 'https://itunes.apple.com/search?entity=song&limit=' . max(1, min(25, $limit)) . '&term=' . rawurlencode($term);
    $resp = http_raw('GET', $url, [
        'User-Agent: KaraokeOS/1.0 (metadata lookup)',
        'Accept: application/json',
    ], null);

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return [];
    }

    $data = json_decode((string)$resp['body'], true);
    if (!is_array($data) || empty($data['results']) || !is_array($data['results'])) {
        return [];
    }

    $needleTitle = normalize_track_string($title);
    $needleArtist = normalize_track_string($artist);
    $langGuess = guess_language_from_title($title);

    $items = [];
    foreach ($data['results'] as $r) {
        if (!is_array($r)) {
            continue;
        }
        $track = (string)($r['trackName'] ?? '');
        $art = (string)($r['artistName'] ?? '');
        $album = (string)($r['collectionName'] ?? '');
        $cover = (string)($r['artworkUrl100'] ?? ($r['artworkUrl60'] ?? ''));
        if ($cover !== '') {
            $cover = preg_replace('#/([0-9]+x[0-9]+)bb\\.(jpg|png)$#i', '/600x600bb.$2', $cover) ?: $cover;
        }
        if ($track === '' || $art === '' || $cover === '') {
            continue;
        }

        $score = 0;
        $nTrack = normalize_track_string($track);
        $nArt = normalize_track_string($art);
        if ($nTrack === $needleTitle) {
            $score += 4;
        } elseif (str_contains($nTrack, $needleTitle) || str_contains($needleTitle, $nTrack)) {
            $score += 2;
        }
        if ($nArt === $needleArtist) {
            $score += 4;
        } elseif (str_contains($nArt, $needleArtist) || str_contains($needleArtist, $nArt)) {
            $score += 2;
        }

        $items[] = [
            'score' => $score,
            'source' => 'itunes',
            'title' => $track,
            'artist' => $art,
            'album' => $album,
            'language' => $langGuess,
            'cover_url' => $cover,
        ];
    }

    usort($items, fn ($a, $b) => ($b['score'] <=> $a['score']));
    return array_map(function ($i) {
        unset($i['score']);
        return $i;
    }, array_slice($items, 0, $limit));
}

function musicbrainz_candidates(string $title, string $artist, int $limit): array
{
    $query = sprintf('recording:"%s" AND artist:"%s"', $title, $artist);
    $url = 'https://musicbrainz.org/ws/2/recording?fmt=json&limit=3&query=' . rawurlencode($query);
    $resp = http_raw('GET', $url, [
        'User-Agent: KaraokeOS/1.0 (metadata lookup; contact=local)',
        'Accept: application/json',
    ], null);

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return [];
    }

    $data = json_decode((string)$resp['body'], true);
    if (!is_array($data) || empty($data['recordings']) || !is_array($data['recordings'])) {
        return [];
    }

    $langGuess = guess_language_from_title($title);
    $items = [];
    foreach ($data['recordings'] as $rec) {
        if (!is_array($rec)) {
            continue;
        }

        $recTitle = (string)($rec['title'] ?? '');
        $rel = null;
        if (!empty($rec['releases']) && is_array($rec['releases'])) {
            $rel = $rec['releases'][0] ?? null;
        }
        if (!is_array($rel)) {
            continue;
        }

        $album = (string)($rel['title'] ?? '');
        $releaseId = (string)($rel['id'] ?? '');
        if ($releaseId === '') {
            continue;
        }

        $cover = '';
        $coverResp = http_raw('GET', 'https://coverartarchive.org/release/' . rawurlencode($releaseId), [
            'User-Agent: KaraokeOS/1.0 (metadata lookup)',
            'Accept: application/json',
        ], null);
        if ($coverResp['status'] >= 200 && $coverResp['status'] < 300) {
            $coverData = json_decode((string)$coverResp['body'], true);
            if (is_array($coverData) && !empty($coverData['images']) && is_array($coverData['images'])) {
                $img = $coverData['images'][0] ?? null;
                if (is_array($img)) {
                    $cover = (string)($img['image'] ?? '');
                }
            }
        }
        if ($cover === '') {
            continue;
        }

        $items[] = [
            'source' => 'musicbrainz',
            'title' => $recTitle ?: $title,
            'artist' => $artist,
            'album' => $album,
            'language' => $langGuess,
            'cover_url' => $cover,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }

    return $items;
}

function normalize_track_string(string $s): string
{
    $s = trim($s);
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = preg_replace('/\\s+/', ' ', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9\\s]/i', '', $s) ?: $s;
    return trim($s);
}

function guess_language_from_title(string $title): string
{
    $t = trim($title);
    if ($t === '') {
        return 'EN';
    }

    // CJK
    if (preg_match('/\\p{Han}/u', $t)) {
        return 'ZH';
    }
    if (preg_match('/[\\p{Hiragana}\\p{Katakana}]/u', $t)) {
        return 'JA';
    }
    if (preg_match('/\\p{Hangul}/u', $t)) {
        return 'KO';
    }

    // Others
    if (preg_match('/\\p{Thai}/u', $t)) {
        return 'TH';
    }
    if (preg_match('/\\p{Arabic}/u', $t)) {
        return 'AR';
    }
    if (preg_match('/\\p{Cyrillic}/u', $t)) {
        return 'RU';
    }

    return 'EN';
}
