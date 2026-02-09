<?php
declare(strict_types=1);

function lookup_artist_image(string $artistName): ?array
{
    $artistName = trim($artistName);
    if ($artistName === '') {
        return null;
    }

    $mb = musicbrainz_find_artist($artistName);
    if (!$mb) {
        return null;
    }

    $mbid = (string)($mb['id'] ?? '');
    if ($mbid === '') {
        return null;
    }

    $wikidata = musicbrainz_artist_wikidata($mbid);
    if ($wikidata === '') {
        return ['musicbrainz_id' => $mbid, 'image_url' => null];
    }

    $img = wikidata_artist_image($wikidata);
    return [
        'musicbrainz_id' => $mbid,
        'wikidata_id' => $wikidata,
        'image_url' => $img !== '' ? $img : null,
    ];
}

function musicbrainz_find_artist(string $artistName): ?array
{
    $query = sprintf('artist:"%s"', $artistName);
    $url = 'https://musicbrainz.org/ws/2/artist?fmt=json&limit=5&query=' . rawurlencode($query);
    $resp = http_raw('GET', $url, [
        'User-Agent: KaraokeOS/1.0 (artist lookup; contact=local)',
        'Accept: application/json',
    ], null);

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return null;
    }
    $data = json_decode((string)$resp['body'], true);
    if (!is_array($data) || empty($data['artists']) || !is_array($data['artists'])) {
        return null;
    }

    $needle = normalize_track_string($artistName);
    $best = null;
    $bestScore = -1;
    foreach ($data['artists'] as $a) {
        if (!is_array($a)) continue;
        $name = (string)($a['name'] ?? '');
        $id = (string)($a['id'] ?? '');
        if ($name === '' || $id === '') continue;
        $score = 0;
        $n = normalize_track_string($name);
        if ($n === $needle) $score += 5;
        elseif (str_contains($n, $needle) || str_contains($needle, $n)) $score += 2;
        $score += (int)($a['score'] ?? 0);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $a;
        }
    }
    return $best ?: null;
}

function musicbrainz_artist_wikidata(string $mbid): string
{
    $url = 'https://musicbrainz.org/ws/2/artist/' . rawurlencode($mbid) . '?fmt=json&inc=url-rels';
    $resp = http_raw('GET', $url, [
        'User-Agent: KaraokeOS/1.0 (artist lookup; contact=local)',
        'Accept: application/json',
    ], null);

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return '';
    }
    $data = json_decode((string)$resp['body'], true);
    if (!is_array($data) || empty($data['relations']) || !is_array($data['relations'])) {
        return '';
    }

    foreach ($data['relations'] as $rel) {
        if (!is_array($rel)) continue;
        if ((string)($rel['type'] ?? '') !== 'wikidata') continue;
        $urlObj = $rel['url'] ?? null;
        if (!is_array($urlObj)) continue;
        $res = (string)($urlObj['resource'] ?? '');
        if (preg_match('#wikidata\\.org/wiki/(Q\\d+)#', $res, $m)) {
            return (string)$m[1];
        }
    }
    return '';
}

function wikidata_artist_image(string $qid): string
{
    $qid = trim($qid);
    if ($qid === '') return '';
    $url = 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=claims&ids=' . rawurlencode($qid);
    $resp = http_raw('GET', $url, [
        'User-Agent: KaraokeOS/1.0 (artist lookup)',
        'Accept: application/json',
    ], null);
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return '';
    }
    $data = json_decode((string)$resp['body'], true);
    if (!is_array($data)) return '';
    $entities = $data['entities'] ?? null;
    if (!is_array($entities) || empty($entities[$qid]) || !is_array($entities[$qid])) return '';
    $claims = $entities[$qid]['claims'] ?? null;
    if (!is_array($claims) || empty($claims['P18']) || !is_array($claims['P18'])) return '';
    $p18 = $claims['P18'][0] ?? null;
    if (!is_array($p18)) return '';
    $mainsnak = $p18['mainsnak'] ?? null;
    if (!is_array($mainsnak)) return '';
    $datavalue = $mainsnak['datavalue'] ?? null;
    if (!is_array($datavalue)) return '';
    $value = (string)($datavalue['value'] ?? '');
    if ($value === '') return '';

    // Render via Commons Special:FilePath (easy + CDN).
    return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($value) . '?width=800';
}

