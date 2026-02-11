<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}

function ensure_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN (\'admin\', \'user\')),
            created_at TEXT NOT NULL,
            last_login_at TEXT
        );'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            artist TEXT NOT NULL,
            artist_id INTEGER,
            language TEXT,
            album TEXT,
            cover_url TEXT,
            drive_url TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS plays (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            song_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            played_at TEXT NOT NULL,
            ip TEXT,
            user_agent TEXT,
            FOREIGN KEY(song_id) REFERENCES songs(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );'
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_artist ON songs(artist);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_language ON songs(language);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_plays_song_id ON plays(song_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_plays_played_at ON plays(played_at);');

    migrate_schema($db);
}

function migrate_schema(PDO $db): void
{
    // artists (entity for name + image)
    $db->exec(
        'CREATE TABLE IF NOT EXISTS artists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE,
            image_url TEXT,
            musicbrainz_id TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_artists_updated_at ON artists(updated_at);');

    // settings
    $db->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at TEXT NOT NULL
        );'
    );

    // users additions
    if (!table_has_column($db, 'users', 'email')) {
        $db->exec('ALTER TABLE users ADD COLUMN email TEXT;');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(email);');
    }
    if (!table_has_column($db, 'users', 'email_verified_at')) {
        $db->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT;');
    }
    if (!table_has_column($db, 'users', 'is_paid')) {
        $db->exec('ALTER TABLE users ADD COLUMN is_paid INTEGER NOT NULL DEFAULT 0;');
    }
    if (!table_has_column($db, 'users', 'paid_until')) {
        $db->exec('ALTER TABLE users ADD COLUMN paid_until TEXT;');
    }
    if (!table_has_column($db, 'users', 'is_revoked')) {
        $db->exec('ALTER TABLE users ADD COLUMN is_revoked INTEGER NOT NULL DEFAULT 0;');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_users_is_revoked ON users(is_revoked);');
    }

    // songs additions
    if (!table_has_column($db, 'songs', 'drive_file_id')) {
        $db->exec('ALTER TABLE songs ADD COLUMN drive_file_id TEXT;');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_drive_file_id ON songs(drive_file_id);');
    }
    if (!table_has_column($db, 'songs', 'artist_id')) {
        $db->exec('ALTER TABLE songs ADD COLUMN artist_id INTEGER;');
    }
    if (table_has_column($db, 'songs', 'artist_id')) {
        $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_artist_id ON songs(artist_id);');
    }
    if (!table_has_column($db, 'songs', 'genre')) {
        $db->exec('ALTER TABLE songs ADD COLUMN genre TEXT;');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_genre ON songs(genre);');
    }
    if (!table_has_column($db, 'songs', 'year')) {
        $db->exec('ALTER TABLE songs ADD COLUMN year INTEGER;');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_songs_year ON songs(year);');
    }

    // email verification tokens
    $db->exec(
        'CREATE TABLE IF NOT EXISTS email_verifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used_at TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_email_verifications_user_id ON email_verifications(user_id);');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_email_verifications_token_hash ON email_verifications(token_hash);');

    // drive grants cache
    $db->exec(
        'CREATE TABLE IF NOT EXISTS drive_grants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            song_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            file_id TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN (\'ok\', \'error\')),
            message TEXT,
            granted_at TEXT NOT NULL,
            FOREIGN KEY(song_id) REFERENCES songs(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_drive_grants_user_id ON drive_grants(user_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_drive_grants_song_id ON drive_grants(song_id);');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_drive_grants_unique ON drive_grants(song_id, user_id, file_id);');

    // Seed artists from existing songs (best-effort).
    $db->exec(
        'INSERT OR IGNORE INTO artists (name, created_at, updated_at)
         SELECT DISTINCT TRIM(artist) AS name, datetime(\'now\', \'localtime\'), datetime(\'now\', \'localtime\')
         FROM songs
         WHERE artist IS NOT NULL AND TRIM(artist) <> \'\';'
    );

    // Backfill artist_id on songs for faster joins (best-effort, safe to re-run).
    if (table_has_column($db, 'songs', 'artist_id')) {
        $db->exec(
            'UPDATE songs
             SET artist_id = (
                 SELECT a.id
                 FROM artists a
                 WHERE a.name = TRIM(songs.artist) COLLATE NOCASE
                 LIMIT 1
             )
             WHERE (artist_id IS NULL OR artist_id = 0)
               AND artist IS NOT NULL
               AND TRIM(artist) <> \'\';'
        );
    }

    // favorites + playlists
    $db->exec(
        'CREATE TABLE IF NOT EXISTS favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            song_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(song_id) REFERENCES songs(id) ON DELETE CASCADE,
            UNIQUE(user_id, song_id)
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_favorites_user_id ON favorites(user_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_favorites_song_id ON favorites(song_id);');

    $db->exec(
        'CREATE TABLE IF NOT EXISTS playlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL COLLATE NOCASE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, name)
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_playlists_user_id ON playlists(user_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_playlists_updated_at ON playlists(updated_at);');

    $db->exec(
        'CREATE TABLE IF NOT EXISTS playlist_songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL,
            song_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            FOREIGN KEY(song_id) REFERENCES songs(id) ON DELETE CASCADE,
            UNIQUE(playlist_id, song_id)
        );'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_playlist_songs_playlist_id ON playlist_songs(playlist_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_playlist_songs_song_id ON playlist_songs(song_id);');

    // recent plays (per-user history)
    $db->exec('CREATE INDEX IF NOT EXISTS idx_plays_user_played_at ON plays(user_id, played_at DESC);');
}

function table_has_column(PDO $db, string $table, string $column): bool
{
    $stmt = $db->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (isset($row['name']) && $row['name'] === $column) {
            return true;
        }
    }
    return false;
}
