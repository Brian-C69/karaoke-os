<?php
declare(strict_types=1);

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
    $where = ['is_active = 1'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(title LIKE :q OR artist LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['artist'])) {
        $where[] = 'artist = :artist';
        $params[':artist'] = $filters['artist'];
    }
    if (!empty($filters['language'])) {
        if ($filters['language'] === 'Unknown') {
            $where[] = '(language IS NULL OR TRIM(language) = \'\')';
        } else {
            $where[] = 'language = :language';
            $params[':language'] = $filters['language'];
        }
    }

    $sql = 'SELECT COUNT(*) FROM songs WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function find_songs(PDO $db, array $filters, int $limit = 500, int $offset = 0): array
{
    $where = ['is_active = 1'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(title LIKE :q OR artist LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['artist'])) {
        $where[] = 'artist = :artist';
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
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
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
    $stmt = $db->prepare('SELECT id, username, role, email, email_verified_at, is_paid, paid_until, created_at, last_login_at FROM users WHERE id = :id');
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
         LEFT JOIN songs s ON lower(s.artist) = lower(a.name) AND s.is_active = 1
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
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE a.name LIKE :q';
        $params[':q'] = '%' . $q . '%';
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
        LEFT JOIN songs s ON lower(s.artist) = lower(a.name) AND s.is_active = 1
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
    if ($album === '' || $coverUrl === '' || $language === '') {
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
            }
        } catch (Throwable $e) {
            // Ignore lookup failures; allow manual override later.
        }
    }

    $now = now_db();
    upsert_artist($db, $artist);
    if ($id === null) {
        $stmt = $db->prepare(
            'INSERT INTO songs (title, artist, language, album, cover_url, drive_url, drive_file_id, is_active, created_at, updated_at)
             VALUES (:t, :a, :l, :al, :c, :d, :fid, :ia, :ca, :ua)'
        );
        $stmt->execute([
            ':t' => $title,
            ':a' => $artist,
            ':l' => $language !== '' ? $language : null,
            ':al' => $album !== '' ? $album : null,
            ':c' => $coverUrl !== '' ? $coverUrl : null,
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
         SET title = :t, artist = :a, language = :l, album = :al, cover_url = :c, drive_url = :d, drive_file_id = :fid, is_active = :ia, updated_at = :ua
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $id,
        ':t' => $title,
        ':a' => $artist,
        ':l' => $language !== '' ? $language : null,
        ':al' => $album !== '' ? $album : null,
        ':c' => $coverUrl !== '' ? $coverUrl : null,
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

function admin_list_users(PDO $db): array
{
    return $db->query('SELECT id, username, email, email_verified_at, is_paid, paid_until, role, created_at, last_login_at FROM users ORDER BY id ASC')->fetchAll();
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
