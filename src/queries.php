<?php
declare(strict_types=1);

function fts_build_query(string $q): string
{
    $q = trim($q);
    if ($q === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 120);
    } else {
        $q = substr($q, 0, 120);
    }

    // Turn punctuation into spaces so "AC/DC" becomes "AC DC".
    $q = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $q);
    $parts = preg_split('/\s+/u', (string)$q, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '';
    }

    $terms = [];
    foreach (array_slice($parts, 0, 8) as $p) {
        $p = trim((string)$p);
        if ($p === '') continue;
        $terms[] = $p . '*';
    }
    return implode(' AND ', $terms);
}

function fts_table_ready(PDO $db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return (bool)$cache[$table];
    }
    try {
        $cache[$table] = table_exists($db, $table);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return (bool)$cache[$table];
}

function get_home_stats(PDO $db): array
{
    $songs = (int)$db->query('SELECT COUNT(*) FROM songs WHERE is_active = 1')->fetchColumn();
    $plays = (int)$db->query('SELECT COUNT(*) FROM plays')->fetchColumn();
    $artists = (int)$db->query('SELECT COUNT(DISTINCT artist) FROM songs WHERE is_active = 1')->fetchColumn();
    $languages = (int)$db->query('SELECT COUNT(DISTINCT language) FROM songs WHERE is_active = 1 AND language IS NOT NULL AND TRIM(language) <> \'\'')->fetchColumn();

    return compact('songs', 'plays', 'artists', 'languages');
}

function count_songs(PDO $db, array $filters): int
{
    $where = ['s.is_active = 1'];
    $params = [];
    $joins = '';

    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins = ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }
    if (!empty($filters['artist_id']) && (int)$filters['artist_id'] > 0) {
        $where[] = 's.artist_id = :aid';
        $params[':aid'] = (int)$filters['artist_id'];
    } elseif (!empty($filters['artist'])) {
        $where[] = 's.artist = :artist';
        $params[':artist'] = $filters['artist'];
    }
    if (!empty($filters['language'])) {
        if ($filters['language'] === 'Unknown') {
            $where[] = '(s.language IS NULL OR TRIM(s.language) = \'\')';
        } else {
            $where[] = 's.language = :language';
            $params[':language'] = $filters['language'];
        }
    }

    $sql = 'SELECT COUNT(*) FROM songs s ' . $joins . 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':aid' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function find_songs(PDO $db, array $filters, int $limit = 500, int $offset = 0): array
{
    $where = ['s.is_active = 1'];
    $params = [];
    $joins = '';

    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }
    if (!empty($filters['artist_id']) && (int)$filters['artist_id'] > 0) {
        $where[] = 's.artist_id = :aid';
        $params[':aid'] = (int)$filters['artist_id'];
    } elseif (!empty($filters['artist'])) {
        $where[] = 's.artist = :artist';
        $params[':artist'] = $filters['artist'];
    }
    if (!empty($filters['language'])) {
        if ($filters['language'] === 'Unknown') {
            $where[] = '(s.language IS NULL OR TRIM(s.language) = \'\')';
        } else {
            $where[] = 's.language = :language';
            $params[':language'] = $filters['language'];
        }
    }

    $sort = $filters['sort'] ?? 'latest';
    $orderBy = 's.updated_at DESC';
    if ($sort === 'plays') {
        $orderBy = 'play_count DESC, s.title ASC';
    } elseif ($sort === 'title') {
        $orderBy = 's.title ASC';
    }

    $sql = '
        SELECT
            s.*,
            COALESCE(p.play_count, 0) AS play_count
        FROM songs s
        ' . $joins . '
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy . '
        LIMIT :lim OFFSET :off
    ';

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':aid' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_song(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT
            s.*,
            COALESCE(p.play_count, 0) AS play_count
        FROM songs s
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE s.id = :id
        LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_user(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT id, username, role, email, email_verified_at, is_paid, paid_until, is_revoked, created_at, last_login_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_song_play_count(PDO $db, int $songId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM plays WHERE song_id = :id');
    $stmt->execute([':id' => $songId]);
    return (int)$stmt->fetchColumn();
}

function log_play(PDO $db, int $songId, int $userId): void
{
    $stmt = $db->prepare('INSERT INTO plays (song_id, user_id, played_at, ip, user_agent) VALUES (:s, :u, :t, :ip, :ua)');
    $stmt->execute([
        ':s' => $songId,
        ':u' => $userId,
        ':t' => now_db(),
        ':ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);
}

function favorite_song_ids(PDO $db, int $userId, array $songIds): array
{
    $userId = (int)$userId;
    $songIds = array_values(array_filter(array_map('intval', $songIds), fn ($v) => $v > 0));
    if ($userId <= 0 || !$songIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($songIds), '?'));
    $sql = 'SELECT song_id FROM favorites WHERE user_id = ? AND song_id IN (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$userId], $songIds));
    $rows = $stmt->fetchAll();
    $set = [];
    foreach ($rows as $r) {
        $set[(int)($r['song_id'] ?? 0)] = true;
    }
    return $set;
}

function toggle_favorite(PDO $db, int $userId, int $songId): bool
{
    $userId = (int)$userId;
    $songId = (int)$songId;
    if ($userId <= 0 || $songId <= 0) {
        return false;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT id FROM favorites WHERE user_id = :u AND song_id = :s LIMIT 1');
        $stmt->execute([':u' => $userId, ':s' => $songId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $del = $db->prepare('DELETE FROM favorites WHERE user_id = :u AND song_id = :s');
            $del->execute([':u' => $userId, ':s' => $songId]);
            $db->commit();
            return false;
        }
        $ins = $db->prepare('INSERT INTO favorites (user_id, song_id, created_at) VALUES (:u, :s, :t)');
        $ins->execute([':u' => $userId, ':s' => $songId, ':t' => now_db()]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function count_favorite_songs(PDO $db, int $userId, array $filters): int
{
    $userId = (int)$userId;
    if ($userId <= 0) return 0;

    $where = ['s.is_active = 1', 'f.user_id = :u'];
    $params = [':u' => $userId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }
    if (!empty($filters['artist'])) {
        $where[] = 's.artist = :artist';
        $params[':artist'] = $filters['artist'];
    }
    if (!empty($filters['language'])) {
        if ($filters['language'] === 'Unknown') {
            $where[] = '(s.language IS NULL OR TRIM(s.language) = \'\')';
        } else {
            $where[] = 's.language = :language';
            $params[':language'] = $filters['language'];
        }
    }

    $sql = 'SELECT COUNT(*) FROM favorites f INNER JOIN songs s ON s.id = f.song_id ' . $joins . 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function find_favorite_songs(PDO $db, int $userId, array $filters, int $limit, int $offset): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];

    $where = ['s.is_active = 1', 'f.user_id = :u'];
    $params = [':u' => $userId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }
    if (!empty($filters['artist'])) {
        $where[] = 's.artist = :artist';
        $params[':artist'] = $filters['artist'];
    }
    if (!empty($filters['language'])) {
        if ($filters['language'] === 'Unknown') {
            $where[] = '(s.language IS NULL OR TRIM(s.language) = \'\')';
        } else {
            $where[] = 's.language = :language';
            $params[':language'] = $filters['language'];
        }
    }

    $sort = $filters['sort'] ?? 'latest';
    $orderBy = 's.updated_at DESC';
    if ($sort === 'plays') {
        $orderBy = 'play_count DESC, s.title ASC';
    } elseif ($sort === 'title') {
        $orderBy = 's.title ASC';
    }

    $sql = '
        SELECT
            s.*,
            COALESCE(p.play_count, 0) AS play_count
        FROM favorites f
        INNER JOIN songs s ON s.id = f.song_id
        ' . $joins . '
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy . '
        LIMIT :lim OFFSET :off
    ';

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function list_playlists(PDO $db, int $userId): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];

    $stmt = $db->prepare(
        'SELECT
            p.*,
            COUNT(ps.id) AS song_count
         FROM playlists p
         LEFT JOIN playlist_songs ps ON ps.playlist_id = p.id
         WHERE p.user_id = :u
         GROUP BY p.id
         ORDER BY p.updated_at DESC, p.name ASC'
    );
    $stmt->execute([':u' => $userId]);
    return $stmt->fetchAll();
}

function create_playlist(PDO $db, int $userId, string $name): int
{
    $userId = (int)$userId;
    $name = trim($name);
    if ($userId <= 0 || $name === '') {
        throw new InvalidArgumentException('Invalid playlist.');
    }

    $stmt = $db->prepare(
        'INSERT INTO playlists (user_id, name, created_at, updated_at)
         VALUES (:u, :n, :c, :u2)'
    );
    $now = now_db();
    $stmt->execute([':u' => $userId, ':n' => $name, ':c' => $now, ':u2' => $now]);
    return (int)$db->lastInsertId();
}

function get_playlist(PDO $db, int $userId, int $playlistId): ?array
{
    $stmt = $db->prepare(
        'SELECT
            p.*,
            COUNT(ps.id) AS song_count
         FROM playlists p
         LEFT JOIN playlist_songs ps ON ps.playlist_id = p.id
         WHERE p.id = :id AND p.user_id = :u
         GROUP BY p.id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int)$playlistId, ':u' => (int)$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function add_song_to_playlist(PDO $db, int $userId, int $playlistId, int $songId): bool
{
    $userId = (int)$userId;
    $playlistId = (int)$playlistId;
    $songId = (int)$songId;
    if ($userId <= 0 || $playlistId <= 0 || $songId <= 0) return false;

    $pl = get_playlist($db, $userId, $playlistId);
    if (!$pl) return false;

    $stmt = $db->prepare('INSERT OR IGNORE INTO playlist_songs (playlist_id, song_id, created_at) VALUES (:p, :s, :t)');
    $stmt->execute([':p' => $playlistId, ':s' => $songId, ':t' => now_db()]);

    $touch = $db->prepare('UPDATE playlists SET updated_at = :u WHERE id = :id');
    $touch->execute([':u' => now_db(), ':id' => $playlistId]);

    return $stmt->rowCount() > 0;
}

function remove_song_from_playlist(PDO $db, int $userId, int $playlistId, int $songId): bool
{
    $pl = get_playlist($db, $userId, $playlistId);
    if (!$pl) return false;
    $stmt = $db->prepare('DELETE FROM playlist_songs WHERE playlist_id = :p AND song_id = :s');
    $stmt->execute([':p' => (int)$playlistId, ':s' => (int)$songId]);
    if ($stmt->rowCount() > 0) {
        $touch = $db->prepare('UPDATE playlists SET updated_at = :u WHERE id = :id');
        $touch->execute([':u' => now_db(), ':id' => (int)$playlistId]);
        return true;
    }
    return false;
}

function count_playlist_songs(PDO $db, int $userId, int $playlistId, array $filters): int
{
    $pl = get_playlist($db, $userId, $playlistId);
    if (!$pl) return 0;

    $where = ['s.is_active = 1', 'ps.playlist_id = :p'];
    $params = [':p' => (int)$playlistId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }

    $sql = 'SELECT COUNT(*) FROM playlist_songs ps INNER JOIN songs s ON s.id = ps.song_id ' . $joins . 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':p' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function find_playlist_songs(PDO $db, int $userId, int $playlistId, array $filters, int $limit, int $offset): array
{
    $pl = get_playlist($db, $userId, $playlistId);
    if (!$pl) return [];

    $where = ['s.is_active = 1', 'ps.playlist_id = :p'];
    $params = [':p' => (int)$playlistId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }

    $sort = $filters['sort'] ?? 'latest';
    $orderBy = 's.updated_at DESC';
    if ($sort === 'plays') {
        $orderBy = 'play_count DESC, s.title ASC';
    } elseif ($sort === 'title') {
        $orderBy = 's.title ASC';
    }

    $sql = '
        SELECT
            s.*,
            COALESCE(p.play_count, 0) AS play_count
        FROM playlist_songs ps
        INNER JOIN songs s ON s.id = ps.song_id
        ' . $joins . '
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy . '
        LIMIT :lim OFFSET :off
    ';

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':p' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function upsert_artist(PDO $db, string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM artists WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => $name]);
    $row = $stmt->fetch();
    if ($row) {
        // Keep latest casing and touch updated_at.
        $stmt = $db->prepare('UPDATE artists SET name = :n, updated_at = :u WHERE id = :id');
        $stmt->execute([':n' => $name, ':u' => now_db(), ':id' => (int)$row['id']]);
        $stmt = $db->prepare('SELECT * FROM artists WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$row['id']]);
        $row = $stmt->fetch();
        if (is_array($row) && empty($row['image_url']) && empty($row['musicbrainz_id'])) {
            try {
                $meta = lookup_artist_image($name);
                if (is_array($meta)) {
                    $img = !empty($meta['image_url']) ? (string)$meta['image_url'] : null;
                    $mb = !empty($meta['musicbrainz_id']) ? (string)$meta['musicbrainz_id'] : null;
                    if ($img !== null || $mb !== null) {
                        if ($img !== null && is_safe_external_url($img)) {
                            $cached = cache_artist_image_url((int)$row['id'], $name, $img);
                            if ($cached) {
                                $img = $cached;
                            }
                        }
                        $stmt = $db->prepare(
                            'UPDATE artists
                             SET
                                image_url = CASE WHEN image_url IS NULL OR TRIM(image_url) = \'\' THEN :img ELSE image_url END,
                                musicbrainz_id = CASE WHEN musicbrainz_id IS NULL OR TRIM(musicbrainz_id) = \'\' THEN :mb ELSE musicbrainz_id END,
                                updated_at = :u
                             WHERE id = :id'
                        );
                        $stmt->execute([
                            ':img' => $img,
                            ':mb' => $mb,
                            ':u' => now_db(),
                            ':id' => (int)$row['id'],
                        ]);
                        $stmt = $db->prepare('SELECT * FROM artists WHERE id = :id LIMIT 1');
                        $stmt->execute([':id' => (int)$row['id']]);
                        $row = $stmt->fetch();
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
        return is_array($row) ? $row : null;
    }

    $now = now_db();
    $imageUrl = null;
    $mbid = null;
    try {
        $meta = lookup_artist_image($name);
        if (is_array($meta)) {
            $imageUrl = !empty($meta['image_url']) ? (string)$meta['image_url'] : null;
            $mbid = !empty($meta['musicbrainz_id']) ? (string)$meta['musicbrainz_id'] : null;
        }
    } catch (Throwable $e) {
        // ignore
    }

    $stmt = $db->prepare(
        'INSERT INTO artists (name, image_url, musicbrainz_id, created_at, updated_at)
         VALUES (:n, :img, :mb, :c, :u)'
    );
    $stmt->execute([
        ':n' => $name,
        ':img' => $imageUrl,
        ':mb' => $mbid,
        ':c' => $now,
        ':u' => $now,
    ]);
    $id = (int)$db->lastInsertId();

    if ($imageUrl !== null && $imageUrl !== '' && is_safe_external_url($imageUrl)) {
        try {
            $cached = cache_artist_image_url($id, $name, $imageUrl);
            if ($cached) {
                $stmt = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                $stmt->execute([':img' => $cached, ':u' => now_db(), ':id' => $id]);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $stmt = $db->prepare('SELECT * FROM artists WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cache_external_artist_images(PDO $db, int $limit = 25): array
{
    $limit = max(1, min(200, (int)$limit));
    $stmt = $db->prepare(
        'SELECT id, name, image_url
         FROM artists
         WHERE image_url IS NOT NULL AND TRIM(image_url) <> \'\' AND (image_url LIKE \'http://%\' OR image_url LIKE \'https://%\')
         ORDER BY updated_at DESC, id DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $ok = 0;
    $fail = 0;
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        $name = (string)($r['name'] ?? '');
        $url = (string)($r['image_url'] ?? '');
        if ($id <= 0 || $url === '' || !is_safe_external_url($url)) {
            continue;
        }
        try {
            $cached = cache_artist_image_url($id, $name, $url);
            if ($cached) {
                $u = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                $u->execute([':img' => $cached, ':u' => now_db(), ':id' => $id]);
                $ok++;
            } else {
                $fail++;
            }
        } catch (Throwable $e) {
            $fail++;
        }
    }

    return ['attempted' => count($rows), 'cached' => $ok, 'failed' => $fail];
}

function get_artist(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT
            a.*,
            COUNT(DISTINCT s.id) AS song_count,
            COALESCE(COUNT(p.id), 0) AS play_count
         FROM artists a
         LEFT JOIN songs s ON s.artist_id = a.id AND s.is_active = 1
         LEFT JOIN plays p ON p.song_id = s.id
         WHERE a.id = :id
         GROUP BY a.id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_artist_by_name(PDO $db, string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM artists WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => $name]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function count_artists(PDO $db, string $q = ''): int
{
    $q = trim($q);
    if ($q === '') {
        return (int)$db->query('SELECT COUNT(*) FROM artists')->fetchColumn();
    }

    $qFts = fts_build_query($q);
    if ($qFts !== '' && fts_table_ready($db, 'artists_fts')) {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM artists a
             INNER JOIN artists_fts ON artists_fts.rowid = a.id
             WHERE artists_fts MATCH :m'
        );
        $stmt->execute([':m' => $qFts]);
        return (int)$stmt->fetchColumn();
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM artists WHERE name LIKE :q');
    $stmt->execute([':q' => '%' . $q . '%']);
    return (int)$stmt->fetchColumn();
}

function find_artists(PDO $db, int $limit, int $offset, string $sort = 'plays', string $q = ''): array
{
    $orderBy = 'play_count DESC, song_count DESC, a.name ASC';
    if ($sort === 'songs') {
        $orderBy = 'song_count DESC, play_count DESC, a.name ASC';
    } elseif ($sort === 'name') {
        $orderBy = 'a.name ASC';
    } elseif ($sort === 'latest') {
        $orderBy = 'a.updated_at DESC, a.name ASC';
    }

    $q = trim($q);
    $joins = '';
    $where = '';
    $params = [];
    if ($q !== '') {
        $qFts = fts_build_query($q);
        if ($qFts !== '' && fts_table_ready($db, 'artists_fts')) {
            $joins .= ' INNER JOIN artists_fts ON artists_fts.rowid = a.id ';
            $where = 'WHERE artists_fts MATCH :m';
            $params[':m'] = $qFts;
        } else {
            $where = 'WHERE a.name LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
    }

    $sql = '
        SELECT
            a.id,
            a.name,
            a.image_url,
            a.updated_at,
            COUNT(DISTINCT s.id) AS song_count,
            COALESCE(COUNT(p.id), 0) AS play_count
        FROM artists a
        ' . $joins . '
        LEFT JOIN songs s ON s.artist_id = a.id AND s.is_active = 1
        LEFT JOIN plays p ON p.song_id = s.id
        ' . $where . '
        GROUP BY a.id
        ORDER BY ' . $orderBy . '
        LIMIT :lim OFFSET :off
    ';
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function list_artists(PDO $db): array
{
    $sql = '
        SELECT
            s.artist,
            COUNT(*) AS song_count,
            COALESCE(SUM(p.play_count), 0) AS play_count
        FROM songs s
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.artist
        ORDER BY play_count DESC, song_count DESC, s.artist ASC
        LIMIT 500
    ';
    return $db->query($sql)->fetchAll();
}

function suggest_artists(PDO $db, string $q, int $limit = 10): array
{
    $q = trim($q);
    if ($q === '') {
        $stmt = $db->prepare(
            'SELECT artist
             FROM songs
             WHERE is_active = 1 AND TRIM(artist) <> \'\'
             GROUP BY artist
             ORDER BY MAX(updated_at) DESC, artist ASC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn ($r) => (string)$r['artist'], $stmt->fetchAll());
    }

    $stmt = $db->prepare(
        'SELECT artist
         FROM songs
         WHERE is_active = 1 AND artist LIKE :q
         GROUP BY artist
         ORDER BY MAX(updated_at) DESC, artist ASC
         LIMIT :lim'
    );
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_map(fn ($r) => (string)$r['artist'], $stmt->fetchAll());
}

function suggest_songs(PDO $db, string $q, string $artist = '', int $limit = 10): array
{
    $q = trim($q);
    $artist = trim($artist);
    if ($q === '' && $artist === '') {
        return [];
    }

    $where = ['is_active = 1'];
    $params = [];
    if ($q !== '') {
        $where[] = 'title LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }
    if ($artist !== '') {
        $where[] = 'artist = :a';
        $params[':a'] = $artist;
    }

    $sql = 'SELECT id, title, artist FROM songs WHERE ' . implode(' AND ', $where) . ' ORDER BY artist ASC, title ASC LIMIT :lim';
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function list_languages(PDO $db): array
{
    $sql = '
        SELECT
            COALESCE(NULLIF(TRIM(language), \'\'), \'Unknown\') AS language,
            COUNT(*) AS song_count,
            COALESCE(SUM(p.play_count), 0) AS play_count
        FROM songs s
        LEFT JOIN (
            SELECT song_id, COUNT(*) AS play_count
            FROM plays
            GROUP BY song_id
        ) p ON p.song_id = s.id
        WHERE s.is_active = 1
        GROUP BY COALESCE(NULLIF(TRIM(language), \'\'), \'Unknown\')
        ORDER BY play_count DESC, song_count DESC, language ASC
        LIMIT 200
    ';
    return $db->query($sql)->fetchAll();
}

function top_songs(PDO $db, int $limit): array
{
    $stmt = $db->prepare(
        'SELECT
            s.*,
            COUNT(p.id) AS play_count
        FROM songs s
        LEFT JOIN plays p ON p.song_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.id
        ORDER BY play_count DESC, s.title ASC
        LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function top_liked_songs(PDO $db, int $limit): array
{
    $stmt = $db->prepare(
        'SELECT
            s.*,
            COUNT(f.id) AS like_count
        FROM songs s
        LEFT JOIN favorites f ON f.song_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.id
        ORDER BY like_count DESC, s.title ASC
        LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_recent_songs(PDO $db, int $userId, array $filters, string $mode = 'unique'): int
{
    $userId = (int)$userId;
    if ($userId <= 0) return 0;

    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['unique', 'history'], true)) {
        $mode = 'unique';
    }

    $where = ['p.user_id = :u', 's.is_active = 1'];
    $params = [':u' => $userId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }

    $countExpr = $mode === 'history' ? 'COUNT(*)' : 'COUNT(DISTINCT p.song_id)';
    $sql = '
        SELECT ' . $countExpr . '
        FROM plays p
        INNER JOIN songs s ON s.id = p.song_id
        ' . $joins . '
        WHERE ' . implode(' AND ', $where) . '
    ';
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':u' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function find_recent_songs(PDO $db, int $userId, array $filters, int $limit, int $offset, string $mode = 'unique'): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];

    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['unique', 'history'], true)) {
        $mode = 'unique';
    }

    $where = ['p.user_id = :u', 's.is_active = 1'];
    $params = [':u' => $userId];

    $joins = '';
    $qRaw = trim((string)($filters['q'] ?? ''));
    $qFts = $qRaw !== '' ? fts_build_query($qRaw) : '';
    if ($qFts !== '' && fts_table_ready($db, 'songs_fts')) {
        $joins .= ' INNER JOIN songs_fts ON songs_fts.rowid = s.id ';
        $where[] = 'songs_fts MATCH :m';
        $params[':m'] = $qFts;
    } elseif ($qRaw !== '') {
        $where[] = '(s.title LIKE :q OR s.artist LIKE :q)';
        $params[':q'] = '%' . $qRaw . '%';
    }

    $sort = strtolower(trim((string)($filters['sort'] ?? 'recent')));
    if (!in_array($sort, ['recent', 'plays', 'title'], true)) {
        $sort = 'recent';
    }

    if ($mode === 'history') {
        $sql = '
            SELECT
                s.*,
                p.played_at AS played_at
            FROM plays p
            INNER JOIN songs s ON s.id = p.song_id
            ' . $joins . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.played_at DESC
            LIMIT :lim OFFSET :off
        ';
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':u' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $orderBy = 'last_played_at DESC';
    if ($sort === 'plays') {
        $orderBy = 'user_play_count DESC, last_played_at DESC, s.title ASC';
    } elseif ($sort === 'title') {
        $orderBy = 's.title ASC';
    }

    $sql = '
        SELECT
            s.*,
            MAX(p.played_at) AS last_played_at,
            COUNT(p.id) AS user_play_count
        FROM plays p
        INNER JOIN songs s ON s.id = p.song_id
        ' . $joins . '
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY s.id
        ORDER BY ' . $orderBy . '
        LIMIT :lim OFFSET :off
    ';

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':u' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function top_artists(PDO $db, int $limit): array
{
    $stmt = $db->prepare(
        'SELECT
            s.artist,
            COUNT(p.id) AS play_count,
            COUNT(DISTINCT s.id) AS song_count
        FROM songs s
        LEFT JOIN plays p ON p.song_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.artist
        ORDER BY play_count DESC, song_count DESC, s.artist ASC
        LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function plays_by_day(PDO $db, int $days): array
{
    $stmt = $db->prepare(
        'SELECT
            substr(played_at, 1, 10) AS day,
            COUNT(*) AS play_count
        FROM plays
        WHERE played_at >= datetime(\'now\', \'localtime\', :since)
        GROUP BY substr(played_at, 1, 10)
        ORDER BY day DESC'
    );
    $stmt->execute([':since' => '-' . $days . ' day']);
    return $stmt->fetchAll();
}

function user_plays_by_day(PDO $db, int $userId, int $days): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];
    $days = max(1, min(365, (int)$days));
    $stmt = $db->prepare(
        'SELECT
            substr(played_at, 1, 10) AS day,
            COUNT(*) AS play_count
        FROM plays
        WHERE user_id = :u AND played_at >= datetime(\'now\', \'localtime\', :since)
        GROUP BY substr(played_at, 1, 10)
        ORDER BY day DESC'
    );
    $stmt->execute([
        ':u' => $userId,
        ':since' => '-' . $days . ' day',
    ]);
    return $stmt->fetchAll();
}

function user_plays_by_week(PDO $db, int $userId, int $weeks): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];
    $weeks = max(1, min(260, (int)$weeks));
    $stmt = $db->prepare(
        'SELECT
            strftime(\'%Y-W%W\', played_at) AS week,
            COUNT(*) AS play_count
        FROM plays
        WHERE user_id = :u AND played_at >= datetime(\'now\', \'localtime\', :since)
        GROUP BY strftime(\'%Y-W%W\', played_at)
        ORDER BY week DESC'
    );
    $stmt->execute([
        ':u' => $userId,
        ':since' => '-' . ($weeks * 7) . ' day',
    ]);
    return $stmt->fetchAll();
}

function user_plays_by_month(PDO $db, int $userId, int $months): array
{
    $userId = (int)$userId;
    if ($userId <= 0) return [];
    $months = max(1, min(120, (int)$months));
    // Approximate months range in days; grouping is by YYYY-MM so it's fine for limiting window.
    $stmt = $db->prepare(
        'SELECT
            substr(played_at, 1, 7) AS month,
            COUNT(*) AS play_count
        FROM plays
        WHERE user_id = :u AND played_at >= datetime(\'now\', \'localtime\', :since)
        GROUP BY substr(played_at, 1, 7)
        ORDER BY month DESC'
    );
    $stmt->execute([
        ':u' => $userId,
        ':since' => '-' . ($months * 31) . ' day',
    ]);
    return $stmt->fetchAll();
}

function user_plays_by_day_between(PDO $db, int $userId, string $start, string $end): array
{
    $userId = (int)$userId;
    $start = trim($start);
    $end = trim($end);
    if ($userId <= 0 || $start === '' || $end === '') return [];

    $stmt = $db->prepare(
        'SELECT
            substr(played_at, 1, 10) AS day,
            COUNT(*) AS play_count
        FROM plays
        WHERE user_id = :u AND played_at >= :s AND played_at < :e
        GROUP BY substr(played_at, 1, 10)
        ORDER BY day ASC'
    );
    $stmt->execute([':u' => $userId, ':s' => $start, ':e' => $end]);
    return $stmt->fetchAll();
}

function user_play_count_between(PDO $db, int $userId, string $start, string $end): int
{
    $userId = (int)$userId;
    $start = trim($start);
    $end = trim($end);
    if ($userId <= 0 || $start === '' || $end === '') return 0;

    $stmt = $db->prepare('SELECT COUNT(*) FROM plays WHERE user_id = :u AND played_at >= :s AND played_at < :e');
    $stmt->execute([':u' => $userId, ':s' => $start, ':e' => $end]);
    return (int)$stmt->fetchColumn();
}

function admin_list_songs(PDO $db, string $view = 'active'): array
{
    $view = strtolower(trim($view));
    $where = '1=1';
    if ($view === 'active') {
        $where = 'is_active = 1';
    } elseif ($view === 'disabled') {
        $where = 'is_active = 0';
    }

    $sql = 'SELECT * FROM songs WHERE ' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT 1000';
    return $db->query($sql)->fetchAll();
}

function admin_list_songs_with_drive(PDO $db): array
{
    $stmt = $db->query('SELECT id, title, artist, drive_file_id FROM songs WHERE is_active = 1 AND drive_file_id IS NOT NULL AND TRIM(drive_file_id) <> \'\' ORDER BY id ASC');
    return $stmt->fetchAll();
}

function admin_upsert_song(PDO $db, ?int $id, array $input): int
{
    $title = trim((string)($input['title'] ?? ''));
    $artist = trim((string)($input['artist'] ?? ''));
    $language = trim((string)($input['language'] ?? ''));
    $album = trim((string)($input['album'] ?? ''));
    $coverUrl = trim((string)($input['cover_url'] ?? ''));
    $genre = trim((string)($input['genre'] ?? ''));
    $yearRaw = trim((string)($input['year'] ?? ''));
    $year = null;
    if ($yearRaw !== '' && preg_match('/^[0-9]{4}$/', $yearRaw)) {
        $year = (int)$yearRaw;
        if ($year < 1900 || $year > ((int)(new DateTimeImmutable('now'))->format('Y') + 1)) {
            $year = null;
        }
    }
    $driveInput = trim((string)($input['drive'] ?? ($input['drive_url'] ?? '')));
    $isActive = isset($input['is_active']) ? 1 : 0;

    if ($title === '' || $artist === '') {
        flash('danger', 'Title and artist are required.');
        redirect('/?r=' . ($id ? '/admin/song-edit&id=' . $id : '/admin/song-new'));
    }

    if ($driveInput === '') {
        flash('danger', 'Google Drive URL/ID is required.');
        redirect('/?r=' . ($id ? '/admin/song-edit&id=' . $id : '/admin/song-new'));
    }
    $fileId = drive_extract_file_id($driveInput);
    $driveUrl = is_safe_external_url($driveInput) ? $driveInput : ($fileId ? drive_view_url($fileId) : null);
    if (!$driveUrl) {
        flash('danger', 'Google Drive URL/ID is invalid.');
        redirect('/?r=' . ($id ? '/admin/song-edit&id=' . $id : '/admin/song-new'));
    }

    // Duplicate guard (server-side).
    $dupes = find_song_duplicates($db, $id, $title, $artist, $fileId, $driveUrl);
    if ($dupes) {
        flash('danger', 'Duplicate detected (same Title + Artist, or same Drive link/file).');
        redirect('/?r=' . ($id ? '/admin/song-edit&id=' . $id : '/admin/song-new'));
    }

    // Auto-fill metadata if missing.
    if ($album === '' || $coverUrl === '' || $language === '' || $genre === '' || $year === null) {
        try {
            $meta = lookup_song_metadata($title, $artist);
            if (is_array($meta)) {
                if ($album === '' && !empty($meta['album'])) {
                    $album = (string)$meta['album'];
                }
                if ($coverUrl === '' && !empty($meta['cover_url'])) {
                    $coverUrl = (string)$meta['cover_url'];
                }
                if ($language === '' && !empty($meta['language'])) {
                    $language = (string)$meta['language'];
                }
                if ($genre === '' && !empty($meta['genre'])) {
                    $genre = (string)$meta['genre'];
                }
                if ($year === null && !empty($meta['year']) && is_numeric($meta['year'])) {
                    $y = (int)$meta['year'];
                    if ($y >= 1900 && $y <= ((int)(new DateTimeImmutable('now'))->format('Y') + 1)) {
                        $year = $y;
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore lookup failures; allow manual override later.
        }
    }

    $now = now_db();
    $artistRow = upsert_artist($db, $artist);
    $artistId = is_array($artistRow) ? (int)($artistRow['id'] ?? 0) : 0;
    if ($artistId <= 0) {
        $artistId = 0;
    }
    if ($id === null) {
        $stmt = $db->prepare(
            'INSERT INTO songs (title, artist, artist_id, language, album, cover_url, genre, year, drive_url, drive_file_id, is_active, created_at, updated_at)
             VALUES (:t, :a, :aid, :l, :al, :c, :g, :y, :d, :fid, :ia, :ca, :ua)'
        );
        $stmt->execute([
            ':t' => $title,
            ':a' => $artist,
            ':aid' => $artistId > 0 ? $artistId : null,
            ':l' => $language !== '' ? $language : null,
            ':al' => $album !== '' ? $album : null,
            ':c' => $coverUrl !== '' ? $coverUrl : null,
            ':g' => $genre !== '' ? $genre : null,
            ':y' => $year,
            ':d' => $driveUrl,
            ':fid' => $fileId,
            ':ia' => $isActive,
            ':ca' => $now,
            ':ua' => $now,
        ]);
        return (int)$db->lastInsertId();
    }

    $stmt = $db->prepare(
        'UPDATE songs
         SET title = :t, artist = :a, artist_id = :aid, language = :l, album = :al, cover_url = :c, genre = :g, year = :y, drive_url = :d, drive_file_id = :fid, is_active = :ia, updated_at = :ua
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $id,
        ':t' => $title,
        ':a' => $artist,
        ':aid' => $artistId > 0 ? $artistId : null,
        ':l' => $language !== '' ? $language : null,
        ':al' => $album !== '' ? $album : null,
        ':c' => $coverUrl !== '' ? $coverUrl : null,
        ':g' => $genre !== '' ? $genre : null,
        ':y' => $year,
        ':d' => $driveUrl,
        ':fid' => $fileId,
        ':ia' => $isActive,
        ':ua' => $now,
    ]);

    return $id;
}

function find_song_duplicates(PDO $db, ?int $excludeId, string $title, string $artist, ?string $driveFileId, ?string $driveUrl): array
{
    $sql = 'SELECT id, title, artist, drive_file_id FROM songs
            WHERE (
                (lower(title) = lower(:t) AND lower(artist) = lower(:a))
                OR (:fid <> \'\' AND drive_file_id = :fid)
                OR (:durl <> \'\' AND drive_url = :durl)
            )
            AND id <> :exid
            ORDER BY id DESC
            LIMIT 10';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':t' => $title,
        ':a' => $artist,
        ':fid' => (string)($driveFileId ?? ''),
        ':durl' => (string)($driveUrl ?? ''),
        ':exid' => (int)($excludeId ?? 0),
    ]);
    $rows = $stmt->fetchAll();
    return $rows ?: [];
}

function admin_import_songs_csv(PDO $db, string $csvTmpPath, bool $lookupMeta = false): array
{
    $csvTmpPath = trim($csvTmpPath);
    if ($csvTmpPath === '' || !is_file($csvTmpPath)) {
        return ['ok' => false];
    }

    $fp = fopen($csvTmpPath, 'rb');
    if ($fp === false) {
        return ['ok' => false];
    }

    $inserted = 0;
    $skipped = 0;
    $errors = 0;
    $errorMessages = [];
    $lookupMeta = (bool)$lookupMeta;

    $headerMap = null;
    $rowNum = 0;

    $db->beginTransaction();
    try {
        while (($row = fgetcsv($fp)) !== false) {
            $rowNum++;
            if (!is_array($row)) continue;

            $row = array_map(static function ($v): string {
                $v = is_string($v) ? $v : '';
                $v = trim($v);
                $v = preg_replace('/^\xEF\xBB\xBF/', '', $v); // strip UTF-8 BOM
                return $v;
            }, $row);

            // Skip blank lines.
            $nonEmpty = array_filter($row, static fn ($v) => $v !== '');
            if (!$nonEmpty) {
                continue;
            }

            // Detect header row.
            if ($headerMap === null) {
                $lower = array_map(static fn ($v) => strtolower(trim((string)$v)), $row);
                if (in_array('title', $lower, true) && in_array('artist', $lower, true)) {
                    $headerMap = [];
                    foreach ($lower as $i => $name) {
                        if ($name === '') continue;
                        $headerMap[$name] = (int)$i;
                    }
                    continue;
                }
                $headerMap = [];
            }

            $get = static function (array $r, array $map, array $keys, int $fallbackIndex = -1): string {
                foreach ($keys as $k) {
                    $k = strtolower((string)$k);
                    if (isset($map[$k]) && isset($r[(int)$map[$k]])) {
                        return (string)$r[(int)$map[$k]];
                    }
                }
                return ($fallbackIndex >= 0 && isset($r[$fallbackIndex])) ? (string)$r[$fallbackIndex] : '';
            };

            $title = $get($row, $headerMap, ['title'], 0);
            $artist = $get($row, $headerMap, ['artist'], 1);
            $drive = $get($row, $headerMap, ['drive', 'drive_url', 'url', 'link'], 2);
            $fileId = $get($row, $headerMap, ['drive_file_id', 'file_id'], -1);
            $language = $get($row, $headerMap, ['language'], 3);
            $album = $get($row, $headerMap, ['album'], 4);
            $coverUrl = $get($row, $headerMap, ['cover_url', 'cover'], 5);
            $genre = $get($row, $headerMap, ['genre'], 6);
            $yearRaw = $get($row, $headerMap, ['year'], 7);
            $isActiveRaw = $get($row, $headerMap, ['is_active', 'active'], 8);

            $title = trim($title);
            $artist = trim($artist);
            $drive = trim($drive);
            $fileId = trim($fileId);

            if ($title === '' || $artist === '') {
                $errors++;
                $errorMessages[] = "Row {$rowNum}: missing title/artist.";
                continue;
            }

            $driveUrl = null;
            $driveFileId = null;

            if ($fileId !== '') {
                $driveFileId = drive_extract_file_id($fileId) ?? $fileId;
            } elseif ($drive !== '') {
                $driveFileId = drive_extract_file_id($drive);
            }

            if ($drive !== '' && is_safe_external_url($drive)) {
                $driveUrl = $drive;
            } elseif ($driveFileId !== null && $driveFileId !== '') {
                $driveUrl = drive_view_url($driveFileId);
            }

            if (!$driveUrl || !is_safe_external_url($driveUrl)) {
                $errors++;
                $errorMessages[] = "Row {$rowNum}: invalid drive/url.";
                continue;
            }

            $isActive = 1;
            $flag = strtolower(trim((string)$isActiveRaw));
            if ($flag !== '') {
                if (in_array($flag, ['0', 'false', 'no', 'n'], true)) $isActive = 0;
            }

            $year = null;
            $yearRaw = trim((string)$yearRaw);
            if ($yearRaw !== '' && preg_match('/^[0-9]{4}$/', $yearRaw)) {
                $year = (int)$yearRaw;
            }

            $coverUrl = trim((string)$coverUrl);
            if ($coverUrl !== '' && !is_safe_external_url($coverUrl)) {
                $coverUrl = '';
            }

            $dupes = find_song_duplicates($db, null, $title, $artist, $driveFileId, $driveUrl);
            if ($dupes) {
                $skipped++;
                continue;
            }

            if ($lookupMeta && ($album === '' || $coverUrl === '' || $language === '' || $genre === '' || $year === null)) {
                try {
                    $meta = lookup_song_metadata($title, $artist);
                    if (is_array($meta)) {
                        if ($album === '' && !empty($meta['album'])) $album = (string)$meta['album'];
                        if ($coverUrl === '' && !empty($meta['cover_url'])) $coverUrl = (string)$meta['cover_url'];
                        if ($language === '' && !empty($meta['language'])) $language = (string)$meta['language'];
                        if ($genre === '' && !empty($meta['genre'])) $genre = (string)$meta['genre'];
                        if ($year === null && !empty($meta['year']) && is_numeric($meta['year'])) $year = (int)$meta['year'];
                    }
                } catch (Throwable $e) {
                    // ignore
                }
            }

            $now = now_db();
            $artistRow = upsert_artist($db, $artist);
            $artistId = is_array($artistRow) ? (int)($artistRow['id'] ?? 0) : 0;
            if ($artistId <= 0) $artistId = 0;

            try {
                $stmt = $db->prepare(
                    'INSERT INTO songs (title, artist, artist_id, language, album, cover_url, genre, year, drive_url, drive_file_id, is_active, created_at, updated_at)
                     VALUES (:t, :a, :aid, :l, :al, :c, :g, :y, :d, :fid, :ia, :ca, :ua)'
                );
                $stmt->execute([
                    ':t' => $title,
                    ':a' => $artist,
                    ':aid' => $artistId > 0 ? $artistId : null,
                    ':l' => trim((string)$language) !== '' ? trim((string)$language) : null,
                    ':al' => trim((string)$album) !== '' ? trim((string)$album) : null,
                    ':c' => $coverUrl !== '' ? $coverUrl : null,
                    ':g' => trim((string)$genre) !== '' ? trim((string)$genre) : null,
                    ':y' => $year,
                    ':d' => $driveUrl,
                    ':fid' => $driveFileId !== null && $driveFileId !== '' ? $driveFileId : null,
                    ':ia' => $isActive,
                    ':ca' => $now,
                    ':ua' => $now,
                ]);
                $inserted++;
            } catch (Throwable $e) {
                $errors++;
                $errorMessages[] = "Row {$rowNum}: insert failed.";
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        fclose($fp);
        return ['ok' => false];
    }

    fclose($fp);
    return [
        'ok' => true,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_messages' => $errorMessages,
    ];
}

function admin_delete_song(PDO $db, int $id): void
{
    $stmt = $db->prepare('UPDATE songs SET is_active = 0, updated_at = :ua WHERE id = :id');
    $stmt->execute([':id' => $id, ':ua' => now_db()]);
}

function admin_set_song_active(PDO $db, int $id, bool $active): void
{
    $stmt = $db->prepare('UPDATE songs SET is_active = :a, updated_at = :ua WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':a' => $active ? 1 : 0,
        ':ua' => now_db(),
    ]);
}

function admin_set_songs_active(PDO $db, array $ids, bool $active): int
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare('UPDATE songs SET is_active = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
    $params = array_merge([$active ? 1 : 0, now_db()], $ids);
    $stmt->execute($params);
    return (int)$stmt->rowCount();
}

function admin_list_users(PDO $db): array
{
    return $db->query('SELECT id, username, email, email_verified_at, is_paid, paid_until, is_revoked, role, created_at, last_login_at FROM users ORDER BY id ASC')->fetchAll();
}

function admin_set_user_revoked(PDO $db, int $id, bool $revoked): void
{
    $stmt = $db->prepare('UPDATE users SET is_revoked = :r WHERE id = :id');
    $stmt->execute([':id' => (int)$id, ':r' => $revoked ? 1 : 0]);
}

function admin_set_users_revoked(PDO $db, array $ids, bool $revoked, int $skipUserId = 0): int
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    if ($skipUserId > 0) {
        $ids = array_values(array_filter($ids, static fn (int $v): bool => $v !== $skipUserId));
    }
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare('UPDATE users SET is_revoked = ? WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_merge([$revoked ? 1 : 0], $ids));
    return (int)$stmt->rowCount();
}

function admin_set_users_paid(PDO $db, array $ids, bool $paid): int
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare('UPDATE users SET is_paid = ? WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_merge([$paid ? 1 : 0], $ids));
    return (int)$stmt->rowCount();
}

function admin_set_users_email_verified(PDO $db, array $ids, bool $verified): int
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if ($verified) {
        $stmt = $db->prepare(
            "UPDATE users
             SET email_verified_at = ?
             WHERE id IN (" . $placeholders . ")
               AND email IS NOT NULL
               AND TRIM(email) <> ''"
        );
        $stmt->execute(array_merge([now_db()], $ids));
        return (int)$stmt->rowCount();
    }

    $stmt = $db->prepare("UPDATE users SET email_verified_at = NULL WHERE id IN (" . $placeholders . ")");
    $stmt->execute($ids);
    return (int)$stmt->rowCount();
}

function admin_set_users_paid_until(PDO $db, array $ids, ?string $paidUntil): int
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare('UPDATE users SET paid_until = ? WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_merge([$paidUntil !== null && trim($paidUntil) !== '' ? trim($paidUntil) : null], $ids));
    return (int)$stmt->rowCount();
}

function admin_create_user(PDO $db, string $username, string $password, string $role, ?string $email, int $isPaid, ?string $paidUntil): void
{
    $username = trim($username);
    if ($username === '') {
        flash('danger', 'Username is required.');
        redirect('/?r=/admin/user-new');
    }
    if (strlen($password) < 6) {
        flash('danger', 'Password must be at least 6 characters.');
        redirect('/?r=/admin/user-new');
    }
    $role = $role === 'admin' ? 'admin' : 'user';

    $email = trim((string)($email ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Email is invalid.');
        redirect('/?r=/admin/user-new');
    }

    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, is_paid, paid_until, created_at) VALUES (:u, :e, :p, :r, :ip, :pu, :t)');
    try {
        $stmt->execute([
            ':u' => $username,
            ':e' => $email !== '' ? $email : null,
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':r' => $role,
            ':ip' => $isPaid ? 1 : 0,
            ':pu' => $paidUntil,
            ':t' => now_db(),
        ]);
    } catch (Throwable $e) {
        flash('danger', 'Could not create user (username may already exist).');
        redirect('/?r=/admin/user-new');
    }
}

function admin_update_user(PDO $db, int $id, array $input): void
{
    $email = trim((string)($input['email'] ?? ''));
    $role = (string)($input['role'] ?? 'user');
    $role = $role === 'admin' ? 'admin' : 'user';
    $isPaid = isset($input['is_paid']) ? 1 : 0;
    $paidUntil = trim((string)($input['paid_until'] ?? '')) ?: null;
    $markVerified = isset($input['mark_verified']);
    $clearVerified = isset($input['clear_verified']);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Email is invalid.');
        redirect('/?r=/admin/user-edit&id=' . $id);
    }

    $fields = [
        'email = :e',
        'role = :r',
        'is_paid = :ip',
        'paid_until = :pu',
    ];
    $params = [
        ':id' => $id,
        ':e' => $email !== '' ? $email : null,
        ':r' => $role,
        ':ip' => $isPaid,
        ':pu' => $paidUntil,
    ];

    if ($markVerified) {
        $fields[] = 'email_verified_at = :ev';
        $params[':ev'] = now_db();
    } elseif ($clearVerified) {
        $fields[] = 'email_verified_at = NULL';
    }

    if (!empty($input['password'] ?? '')) {
        $pw = (string)$input['password'];
        if (strlen($pw) < 6) {
            flash('danger', 'Password must be at least 6 characters.');
            redirect('/?r=/admin/user-edit&id=' . $id);
        }
        $fields[] = 'password_hash = :ph';
        $params[':ph'] = password_hash($pw, PASSWORD_DEFAULT);
    }

    $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
    try {
        $stmt->execute($params);
    } catch (Throwable $e) {
        flash('danger', 'Could not update user (email/username may already exist).');
        redirect('/?r=/admin/user-edit&id=' . $id);
    }
}

function create_email_verification(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(20));
    $hash = hash('sha256', $token);
    $now = now_db();
    $expires = (new DateTimeImmutable('now +24 hours'))->format('Y-m-d H:i:s');

    $stmt = $db->prepare('INSERT INTO email_verifications (user_id, token_hash, created_at, expires_at) VALUES (:u, :h, :c, :e)');
    $stmt->execute([
        ':u' => $userId,
        ':h' => $hash,
        ':c' => $now,
        ':e' => $expires,
    ]);

    return $token;
}

function confirm_email_verification(PDO $db, string $token): ?int
{
    $hash = hash('sha256', $token);
    $stmt = $db->prepare('SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token_hash = :h LIMIT 1');
    $stmt->execute([':h' => $hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (!empty($row['used_at'])) {
        return null;
    }
    if ((string)$row['expires_at'] < now_db()) {
        return null;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE email_verifications SET used_at = :u WHERE id = :id');
        $stmt->execute([':u' => now_db(), ':id' => (int)$row['id']]);
        $stmt = $db->prepare('UPDATE users SET email_verified_at = :v WHERE id = :uid');
        $stmt->execute([':v' => now_db(), ':uid' => (int)$row['user_id']]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return (int)$row['user_id'];
}

function ensure_drive_access_for_user(PDO $db, array $song, array $user): void
{
    $fileId = trim((string)($song['drive_file_id'] ?? ''));
    if ($fileId === '') {
        throw new RuntimeException('Drive file ID not set for this song.');
    }
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('User email invalid.');
    }

    if (drive_grant_cached_ok($db, (int)$song['id'], (int)$user['id'], $fileId)) {
        return;
    }

    if (!drive_is_configured()) {
        if (DRIVE_ENFORCE_PERMISSION_ON_PLAY) {
            throw new RuntimeException('Drive integration is not configured (service account JSON missing).');
        }
        return;
    }

    try {
        drive_grant_viewer($fileId, $email);
        drive_try_harden_view_only($fileId);
        drive_grant_record($db, (int)$song['id'], (int)$user['id'], $fileId, 'ok', null);
    } catch (Throwable $e) {
        drive_grant_record($db, (int)$song['id'], (int)$user['id'], $fileId, 'error', $e->getMessage());
        throw $e;
    }
}

function drive_grant_cached_ok(PDO $db, int $songId, int $userId, string $fileId): bool
{
    $stmt = $db->prepare('SELECT status FROM drive_grants WHERE song_id = :s AND user_id = :u AND file_id = :f ORDER BY id DESC LIMIT 1');
    $stmt->execute([':s' => $songId, ':u' => $userId, ':f' => $fileId]);
    $row = $stmt->fetch();
    return $row && ($row['status'] ?? '') === 'ok';
}

function drive_grant_record(PDO $db, int $songId, int $userId, string $fileId, string $status, ?string $message): void
{
    $stmt = $db->prepare('INSERT OR REPLACE INTO drive_grants (song_id, user_id, file_id, status, message, granted_at) VALUES (:s, :u, :f, :st, :m, :t)');
    $stmt->execute([
        ':s' => $songId,
        ':u' => $userId,
        ':f' => $fileId,
        ':st' => $status,
        ':m' => $message ? substr($message, 0, 500) : null,
        ':t' => now_db(),
    ]);
}
