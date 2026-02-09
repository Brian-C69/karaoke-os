<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$route = $_GET['r'] ?? '/';
if (!is_string($route)) {
    $route = '/';
}
$route = '/' . ltrim($route, '/');

$db = db();
ensure_schema($db);

switch ($route) {
    case '/':
        $pageTitle = 'Home';
        render('home', [
            'latestSongs' => find_songs($db, ['sort' => 'latest'], 10, 0),
            'topSongs' => top_songs($db, 10),
            'topLikedSongs' => top_liked_songs($db, 10),
            'topArtists' => find_artists($db, 10, 0, 'plays'),
            'topLanguages' => array_slice(list_languages($db), 0, 10),
        ]);
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if (login_attempt($db, $username, $password)) {
                flash('success', 'Welcome back.');
                redirect('/?r=/');
            }
            flash('danger', 'Invalid username or password.');
            redirect('/?r=/login');
        }
        $pageTitle = 'Login';
        render('login');
        break;

    case '/logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        logout_now();
        flash('success', 'Logged out.');
        redirect('/?r=/');
        break;

    case '/account':
        require_login();
        $pageTitle = 'Account';
        render('account', [
            'userFull' => current_user_full($db),
        ]);
        break;

    case '/account/update-email':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        require_login();
        $user = current_user_full($db);
        if (!$user) {
            logout_now();
            redirect('/?r=/login');
        }
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Please enter a valid email.');
            redirect('/?r=/account');
        }
        // Changing email invalidates prior verification.
        $stmt = $db->prepare('UPDATE users SET email = :e, email_verified_at = NULL WHERE id = :id');
        $stmt->execute([':e' => $email, ':id' => (int)$user['id']]);
        flash('success', 'Email updated. Please verify it.');
        redirect('/?r=/account');
        break;

    case '/account/send-verification':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        require_login();
        $user = current_user_full($db);
        if (!$user) {
            logout_now();
            redirect('/?r=/login');
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Add a valid email first.');
            redirect('/?r=/account');
        }
        if (!empty($user['email_verified_at'])) {
            flash('info', 'Email is already verified.');
            redirect('/?r=/account');
        }

        $token = create_email_verification($db, (int)$user['id']);
        $verifyUrl = rtrim(APP_BASE_URL, '/') . '/?r=/verify-email&token=' . urlencode($token);

        try {
            send_verification_email($db, $email, $verifyUrl);
            flash('success', 'Verification email sent.');
        } catch (Throwable $e) {
            if (DEV_SHOW_VERIFICATION_LINK) {
                flash('warning', 'SMTP not configured or send failed. Verification link (dev): ' . $verifyUrl);
            } else {
                flash('danger', 'Could not send verification email. Ask admin to configure SMTP.');
            }
        }
        redirect('/?r=/account');
        break;

    case '/verify-email':
        $token = (string)($_GET['token'] ?? '');
        if ($token === '') {
            flash('danger', 'Invalid verification link.');
            redirect('/?r=/');
        }
        $userId = confirm_email_verification($db, $token);
        if (!$userId) {
            flash('danger', 'Verification link is invalid or expired.');
            redirect('/?r=/account');
        }
        flash('success', 'Email verified.');
        redirect('/?r=/account');
        break;

    case '/songs':
        $pageTitle = 'Songs';
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'artist' => trim((string)($_GET['artist'] ?? '')),
            'language' => trim((string)($_GET['language'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_songs($db, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_songs($db, $filters, $pager->limit(), $pager->offset());
        $user = current_user();
        $favoriteIds = $user ? favorite_song_ids($db, (int)$user['id'], array_map(fn ($s) => (int)$s['id'], $songs)) : [];
        render('songs', [
            'filters' => $filters,
            'view' => $view,
            'pager' => $pager,
            'songs' => $songs,
            'favoriteIds' => $favoriteIds,
        ]);
        break;

    case '/song':
        $songId = (int)($_GET['id'] ?? 0);
        $song = get_song($db, $songId);
        if (!$song) {
            http_response_code(404);
            $pageTitle = 'Not Found';
            render('error', ['message' => 'Song not found.']);
            break;
        }
        $pageTitle = $song['title'];
        $u = current_user();
        $fav = false;
        if ($u) {
            $set = favorite_song_ids($db, (int)$u['id'], [$songId]);
            $fav = !empty($set[$songId]);
        }
        render('song', [
            'song' => $song,
            'artistRow' => get_artist_by_name($db, (string)($song['artist'] ?? '')),
            'playCount' => (int)get_song_play_count($db, $songId),
            'favorited' => $fav,
        ]);
        break;

    case '/play':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        require_login();

        $songId = (int)($_POST['id'] ?? 0);
        $song = get_song($db, $songId);
        if (!$song) {
            flash('danger', 'Song not found.');
            redirect('/?r=/songs');
        }
        $driveUrl = trim((string)($song['drive_url'] ?? ''));
        $fileId = (string)($song['drive_file_id'] ?? '');
        if ($fileId === '' && $driveUrl !== '') {
            $fileId = drive_extract_file_id($driveUrl) ?? '';
        }

        $url = '';
        if ($fileId !== '') {
            $url = drive_preview_url($fileId);
        } elseif ($driveUrl !== '' && is_safe_external_url($driveUrl)) {
            $url = $driveUrl;
        }

        if ($url === '' || !is_safe_external_url($url)) {
            flash('warning', 'This song has no playable link yet.');
            redirect('/?r=/song&id=' . $songId);
        }

        log_play($db, $songId, (int)current_user()['id']);
        header('Location: ' . $url, true, 302);
        exit;

    case '/artists':
        $pageTitle = 'Artists';
        $q = trim((string)($_GET['q'] ?? ''));
        $sort = strtolower(trim((string)($_GET['sort'] ?? 'latest')));
        if (!in_array($sort, ['latest', 'plays', 'songs', 'name'], true)) {
            $sort = 'latest';
        }
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_artists($db, $q);
        $pager = new SimplePager($total, $page, $perPage);
        render('artists', [
            'q' => $q,
            'sort' => $sort,
            'view' => $view,
            'pager' => $pager,
            'artists' => find_artists($db, $pager->limit(), $pager->offset(), $sort, $q),
        ]);
        break;

    case '/artist':
        $artistId = (int)($_GET['id'] ?? 0);
        $artist = $artistId > 0 ? get_artist($db, $artistId) : null;
        if (!$artist) {
            http_response_code(404);
            $pageTitle = 'Not Found';
            render('error', ['message' => 'Artist not found.']);
            break;
        }
        $pageTitle = (string)$artist['name'];

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'artist' => (string)$artist['name'],
            'language' => trim((string)($_GET['language'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_songs($db, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_songs($db, $filters, $pager->limit(), $pager->offset());
        $user = current_user();
        $favoriteIds = $user ? favorite_song_ids($db, (int)$user['id'], array_map(fn ($s) => (int)$s['id'], $songs)) : [];

        render('artist', [
            'artist' => $artist,
            'filters' => $filters,
            'view' => $view,
            'pager' => $pager,
            'songs' => $songs,
            'favoriteIds' => $favoriteIds,
        ]);
        break;

    case '/languages':
        $pageTitle = 'Languages';
        render('languages', [
            'languages' => list_languages($db),
        ]);
        break;

    case '/top':
        $pageTitle = 'Top 100';
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        render('top', [
            'view' => $view,
            'rows' => top_songs($db, 100),
        ]);
        break;

    case '/liked':
        $pageTitle = 'Most liked';
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        render('liked', [
            'view' => $view,
            'rows' => top_liked_songs($db, 100),
        ]);
        break;

    case '/favorites':
        require_login();
        $pageTitle = 'Favorites';
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'artist' => trim((string)($_GET['artist'] ?? '')),
            'language' => trim((string)($_GET['language'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $uid = (int)current_user()['id'];
        $total = count_favorite_songs($db, $uid, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_favorite_songs($db, $uid, $filters, $pager->limit(), $pager->offset());
        $favoriteIds = [];
        foreach ($songs as $s) $favoriteIds[(int)($s['id'] ?? 0)] = true;
        render('favorites', [
            'filters' => $filters,
            'view' => $view,
            'pager' => $pager,
            'songs' => $songs,
            'favoriteIds' => $favoriteIds,
        ]);
        break;

    case '/playlists':
        require_login();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                flash('danger', 'Playlist name is required.');
                redirect('/?r=/playlists');
            }
            try {
                create_playlist($db, (int)current_user()['id'], $name);
                flash('success', 'Playlist created.');
            } catch (Throwable $e) {
                flash('danger', 'Could not create playlist (maybe duplicate name).');
            }
            redirect('/?r=/playlists');
        }
        $pageTitle = 'My Playlists';
        render('playlists', [
            'playlists' => list_playlists($db, (int)current_user()['id']),
        ]);
        break;

    case '/playlist':
        require_login();
        $playlistId = (int)($_GET['id'] ?? 0);
        $playlist = $playlistId > 0 ? get_playlist($db, (int)current_user()['id'], $playlistId) : null;
        if (!$playlist) {
            http_response_code(404);
            $pageTitle = 'Not Found';
            render('error', ['message' => 'Playlist not found.']);
            break;
        }
        $pageTitle = (string)$playlist['name'];
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $view = strtolower(trim((string)($_GET['view'] ?? 'tile')));
        if (!in_array($view, ['tile', 'list'], true)) {
            $view = 'tile';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $uid = (int)current_user()['id'];
        $total = count_playlist_songs($db, $uid, $playlistId, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_playlist_songs($db, $uid, $playlistId, $filters, $pager->limit(), $pager->offset());
        $favoriteIds = favorite_song_ids($db, $uid, array_map(fn ($s) => (int)$s['id'], $songs));
        render('playlist', [
            'playlist' => $playlist,
            'filters' => $filters,
            'view' => $view,
            'pager' => $pager,
            'songs' => $songs,
            'favoriteIds' => $favoriteIds,
        ]);
        break;

    case '/api/songs':
        header('Content-Type: application/json; charset=utf-8');
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'artist' => trim((string)($_GET['artist'] ?? '')),
            'language' => trim((string)($_GET['language'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_songs($db, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_songs($db, $filters, $pager->limit(), $pager->offset());
        $u = current_user();
        $favSet = $u ? favorite_song_ids($db, (int)$u['id'], array_map(fn ($s) => (int)$s['id'], $songs)) : [];
        // Keep payload small for AJAX.
        $songs = array_map(static function (array $s) use ($favSet): array {
            $lang = (string)($s['language'] ?? '');
            $id = (int)($s['id'] ?? 0);
            return [
                'id' => $id,
                'title' => (string)($s['title'] ?? ''),
                'artist' => (string)($s['artist'] ?? ''),
                'cover_url' => (string)($s['cover_url'] ?? ''),
                'language' => $lang,
                'language_flag' => language_flag_url($lang) ?: null,
                'favorited' => !empty($favSet[$id]),
                'play_count' => (int)($s['play_count'] ?? 0),
            ];
        }, array_slice($songs, 0, 200));
        echo json_encode(['ok' => true, 'songs' => $songs, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

    case '/api/favorites':
        header('Content-Type: application/json; charset=utf-8');
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'artist' => trim((string)($_GET['artist'] ?? '')),
            'language' => trim((string)($_GET['language'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) $perPage = 20;
        $perPage = min(100, $perPage);
        $uid = (int)current_user()['id'];
        $total = count_favorite_songs($db, $uid, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_favorite_songs($db, $uid, $filters, $pager->limit(), $pager->offset());
        $songs = array_map(static function (array $s): array {
            $lang = (string)($s['language'] ?? '');
            return [
                'id' => (int)($s['id'] ?? 0),
                'title' => (string)($s['title'] ?? ''),
                'artist' => (string)($s['artist'] ?? ''),
                'cover_url' => (string)($s['cover_url'] ?? ''),
                'language' => $lang,
                'language_flag' => language_flag_url($lang) ?: null,
                'favorited' => true,
                'play_count' => (int)($s['play_count'] ?? 0),
            ];
        }, array_slice($songs, 0, 200));
        echo json_encode(['ok' => true, 'songs' => $songs, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

    case '/api/playlist-songs':
        header('Content-Type: application/json; charset=utf-8');
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $playlistId = (int)($_GET['id'] ?? 0);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'latest')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) $perPage = 20;
        $perPage = min(100, $perPage);
        $uid = (int)current_user()['id'];
        $total = count_playlist_songs($db, $uid, $playlistId, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_playlist_songs($db, $uid, $playlistId, $filters, $pager->limit(), $pager->offset());
        $favSet = favorite_song_ids($db, $uid, array_map(fn ($s) => (int)$s['id'], $songs));
        $songs = array_map(static function (array $s) use ($favSet): array {
            $lang = (string)($s['language'] ?? '');
            $id = (int)($s['id'] ?? 0);
            return [
                'id' => $id,
                'title' => (string)($s['title'] ?? ''),
                'artist' => (string)($s['artist'] ?? ''),
                'cover_url' => (string)($s['cover_url'] ?? ''),
                'language' => $lang,
                'language_flag' => language_flag_url($lang) ?: null,
                'favorited' => !empty($favSet[$id]),
                'play_count' => (int)($s['play_count'] ?? 0),
            ];
        }, array_slice($songs, 0, 200));
        echo json_encode(['ok' => true, 'songs' => $songs, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

    case '/api/favorite/toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        csrf_verify();
        $songId = (int)($_POST['song_id'] ?? 0);
        if ($songId <= 0 || !get_song($db, $songId)) {
            json_response(['ok' => false, 'error' => 'song_not_found'], 404);
        }
        $favorited = toggle_favorite($db, (int)current_user()['id'], $songId);
        json_response(['ok' => true, 'song_id' => $songId, 'favorited' => $favorited]);

    case '/api/playlists':
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $items = list_playlists($db, (int)current_user()['id']);
        $items = array_map(static function (array $p): array {
            return [
                'id' => (int)($p['id'] ?? 0),
                'name' => (string)($p['name'] ?? ''),
                'song_count' => (int)($p['song_count'] ?? 0),
            ];
        }, $items);
        json_response(['ok' => true, 'items' => $items]);

    case '/api/playlists/create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        csrf_verify();
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            json_response(['ok' => false, 'error' => 'missing_name'], 400);
        }
        try {
            $id = create_playlist($db, (int)current_user()['id'], $name);
            json_response(['ok' => true, 'id' => $id, 'name' => $name]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'create_failed'], 409);
        }

    case '/api/playlists/add-song':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        csrf_verify();
        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        $songId = (int)($_POST['song_id'] ?? 0);
        if ($playlistId <= 0 || $songId <= 0) {
            json_response(['ok' => false, 'error' => 'missing_fields'], 400);
        }
        if (!get_song($db, $songId)) {
            json_response(['ok' => false, 'error' => 'song_not_found'], 404);
        }
        $added = add_song_to_playlist($db, (int)current_user()['id'], $playlistId, $songId);
        json_response(['ok' => true, 'added' => $added]);

    case '/api/playlists/remove-song':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        csrf_verify();
        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        $songId = (int)($_POST['song_id'] ?? 0);
        if ($playlistId <= 0 || $songId <= 0) {
            json_response(['ok' => false, 'error' => 'missing_fields'], 400);
        }
        $removed = remove_song_from_playlist($db, (int)current_user()['id'], $playlistId, $songId);
        json_response(['ok' => true, 'removed' => $removed]);

    case '/api/artists':
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        $sort = strtolower(trim((string)($_GET['sort'] ?? 'latest')));
        if (!in_array($sort, ['latest', 'plays', 'songs', 'name'], true)) {
            $sort = 'latest';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_artists($db, $q);
        $pager = new SimplePager($total, $page, $perPage);
        $rows = find_artists($db, $pager->limit(), $pager->offset(), $sort, $q);
        $artists = array_map(static function (array $a): array {
            return [
                'id' => (int)($a['id'] ?? 0),
                'name' => (string)($a['name'] ?? ''),
                'image_url' => (string)($a['image_url'] ?? ''),
                'song_count' => (int)($a['song_count'] ?? 0),
                'play_count' => (int)($a['play_count'] ?? 0),
            ];
        }, array_slice($rows, 0, 500));
        echo json_encode(['ok' => true, 'artists' => $artists, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

    case '/api/llm/songs':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        if (!defined('LLM_API_KEY') || trim((string)LLM_API_KEY) === '') {
            json_response(['ok' => false, 'error' => 'llm_api_not_configured'], 501);
        }
        $auth = trim(request_header('Authorization'));
        $key = trim(request_header('X-Api-Key'));
        if ($key === '' && preg_match('/^Bearer\\s+(.+)$/i', $auth, $m)) {
            $key = trim((string)$m[1]);
        }
        if ($key === '' || !hash_equals((string)LLM_API_KEY, $key)) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $input = read_json_body();
        $dryRun = !empty($input['dry_run']);
        $title = trim((string)($input['title'] ?? ''));
        $artist = trim((string)($input['artist'] ?? ''));
        $driveInput = trim((string)($input['drive'] ?? ''));
        $language = trim((string)($input['language'] ?? ''));
        $album = trim((string)($input['album'] ?? ''));
        $coverUrl = trim((string)($input['cover_url'] ?? ''));
        $isActive = array_key_exists('is_active', $input) ? (!empty($input['is_active']) ? 1 : 0) : 1;

        if ($title === '' || $artist === '' || $driveInput === '') {
            json_response(['ok' => false, 'error' => 'missing_required', 'fields' => ['title', 'artist', 'drive']], 400);
        }

        $fileId = drive_extract_file_id($driveInput);
        $driveUrl = is_safe_external_url($driveInput) ? $driveInput : ($fileId ? drive_view_url($fileId) : null);
        if (!$driveUrl || !is_safe_external_url($driveUrl)) {
            json_response(['ok' => false, 'error' => 'invalid_drive'], 400);
        }

        $matches = find_song_duplicates($db, null, $title, $artist, $fileId, $driveUrl);
        if ($matches) {
            json_response(['ok' => false, 'error' => 'duplicate', 'matches' => $matches], 409);
        }
        if ($dryRun) {
            json_response([
                'ok' => true,
                'mode' => 'check',
                'normalized' => [
                    'title' => $title,
                    'artist' => $artist,
                    'drive_url' => $driveUrl,
                    'drive_file_id' => $fileId,
                ],
                'matches' => [],
            ]);
        }

        if ($album === '' || $coverUrl === '' || $language === '') {
            try {
                $meta = lookup_song_metadata($title, $artist);
                if (is_array($meta)) {
                    if ($album === '' && !empty($meta['album'])) $album = (string)$meta['album'];
                    if ($coverUrl === '' && !empty($meta['cover_url'])) $coverUrl = (string)$meta['cover_url'];
                    if ($language === '' && !empty($meta['language'])) $language = (string)$meta['language'];
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        $now = now_db();
        upsert_artist($db, $artist);
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
        $id = (int)$db->lastInsertId();
        $song = get_song($db, $id);
        json_response(['ok' => true, 'mode' => 'add', 'id' => $id, 'song' => $song], 201);
        break;

    case '/admin':
        require_admin();
        $pageTitle = 'Admin';
        render('admin_home');
        break;

    case '/admin/songs':
        require_admin();
        $pageTitle = 'Admin · Songs';
        render('admin_songs', [
            'view' => (string)($_GET['view'] ?? 'active'),
            'songs' => admin_list_songs($db, (string)($_GET['view'] ?? 'active')),
        ]);
        break;

    case '/admin/artists':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'cache_external_images') {
                $res = cache_external_artist_images($db, 25);
                flash('success', sprintf('Cached %d/%d artist images.', (int)($res['cached'] ?? 0), (int)($res['attempted'] ?? 0)));
            }
            redirect('/?r=/admin/artists');
        }
        $pageTitle = 'Admin · Artists';
        $sort = strtolower(trim((string)($_GET['sort'] ?? 'latest')));
        if (!in_array($sort, ['plays', 'songs', 'name', 'latest'], true)) {
            $sort = 'latest';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $total = count_artists($db);
        $pager = new SimplePager($total, $page, $perPage);
        render('admin_artists', [
            'sort' => $sort,
            'pager' => $pager,
            'artists' => find_artists($db, $pager->limit(), $pager->offset(), $sort),
        ]);
        break;

    case '/admin/artist-edit':
        require_admin();
        $artistId = (int)($_GET['id'] ?? 0);
        $artist = $artistId > 0 ? get_artist($db, $artistId) : null;
        if (!$artist) {
            flash('danger', 'Artist not found.');
            redirect('/?r=/admin/artists');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $remove = !empty($_POST['remove_image']);
            $imageUrl = trim((string)($_POST['image_url'] ?? ''));

            $upload = $_FILES['image_file'] ?? null;
            $uploadedPath = null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $tmp = (string)($upload['tmp_name'] ?? '');
                $size = (int)($upload['size'] ?? 0);
                if ($tmp !== '' && $size > 0 && $size <= 3_000_000) {
                    $info = @getimagesize($tmp);
                    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
                    $ext = '';
                    if ($mime === 'image/jpeg') $ext = 'jpg';
                    elseif ($mime === 'image/png') $ext = 'png';
                    elseif ($mime === 'image/webp') $ext = 'webp';
                    elseif ($mime === 'image/gif') $ext = 'gif';

                    if ($ext !== '') {
                        $dir = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'artists';
                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        }
                        $name = 'artist_' . (int)$artist['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        $dest = $dir . DIRECTORY_SEPARATOR . $name;
                        if (@move_uploaded_file($tmp, $dest)) {
                            $uploadedPath = 'assets/uploads/artists/' . $name;
                        }
                    }
                }
            }

            if ($remove) {
                $stmt = $db->prepare('UPDATE artists SET image_url = NULL, updated_at = :u WHERE id = :id');
                $stmt->execute([':u' => now_db(), ':id' => (int)$artist['id']]);
                flash('success', 'Artist image removed.');
            } elseif ($uploadedPath !== null) {
                $stmt = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                $stmt->execute([':img' => $uploadedPath, ':u' => now_db(), ':id' => (int)$artist['id']]);
                flash('success', 'Artist image uploaded.');
            } elseif ($imageUrl !== '') {
                $stmt = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                $stmt->execute([':img' => $imageUrl, ':u' => now_db(), ':id' => (int)$artist['id']]);
                flash('success', 'Artist image updated.');
            } else {
                flash('info', 'No changes.');
            }

            redirect('/?r=/admin/artist-edit&id=' . (int)$artist['id']);
        }

        render('admin_artist_form', [
            'artist' => $artist,
        ]);
        break;

    case '/admin/song-new':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $id = admin_upsert_song($db, null, $_POST);
            flash('success', 'Song saved.');
            redirect('/?r=/admin/song-new&saved_id=' . $id);
        }
        $pageTitle = 'Admin · Add Song';
        render('admin_song_form', ['song' => null]);
        break;

    case '/admin/song-edit':
        require_admin();
        $songId = (int)($_GET['id'] ?? 0);
        $returnView = strtolower(trim((string)($_GET['return_view'] ?? '')));
        if (!in_array($returnView, ['active', 'disabled', 'all'], true)) {
            $returnView = '';
        }
        $song = get_song($db, $songId);
        if (!$song) {
            flash('danger', 'Song not found.');
            redirect('/?r=/admin/songs');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            admin_upsert_song($db, $songId, $_POST);
            flash('success', 'Song saved.');
            redirect('/?r=/admin/songs' . ($returnView !== '' ? '&view=' . $returnView : ''));
        }
        $pageTitle = 'Admin · Edit Song';
        render('admin_song_form', ['song' => $song, 'return_view' => $returnView]);
        break;

    case '/admin/song-delete':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        $songId = (int)($_POST['id'] ?? 0);
        admin_delete_song($db, $songId);
        flash('success', 'Song removed from active library.');
        redirect('/?r=/admin/songs&view=active');
        break;

    case '/admin/song-enable':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        $songId = (int)($_POST['id'] ?? 0);
        admin_set_song_active($db, $songId, true);
        flash('success', 'Song enabled.');
        redirect('/?r=/admin/songs&view=disabled');
        break;

    case '/admin/users':
        require_admin();
        $pageTitle = 'Admin · Users';
        render('admin_users', [
            'users' => admin_list_users($db),
        ]);
        break;

    case '/admin/user-new':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $email = trim((string)($_POST['email'] ?? ''));
            $role = (string)($_POST['role'] ?? 'user');
            $isPaid = isset($_POST['is_paid']) ? 1 : 0;
            $paidUntil = trim((string)($_POST['paid_until'] ?? '')) ?: null;
            admin_create_user($db, $username, $password, $role, $email !== '' ? $email : null, $isPaid, $paidUntil);
            flash('success', 'User created.');
            redirect('/?r=/admin/users');
        }
        $pageTitle = 'Admin · Add User';
        render('admin_user_form');
        break;

    case '/admin/user-edit':
        require_admin();
        $userId = (int)($_GET['id'] ?? 0);
        $target = get_user($db, $userId);
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            admin_update_user($db, $userId, $_POST);
            flash('success', 'User updated.');
            redirect('/?r=/admin/users');
        }
        $pageTitle = 'Admin · Edit User';
        render('admin_user_edit_form', ['target' => $target]);
        break;

    case '/admin/user-sync-drive':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        $userId = (int)($_POST['id'] ?? 0);
        $target = get_user($db, $userId);
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        if (empty($target['email']) || empty($target['email_verified_at']) || !user_is_paid($target)) {
            flash('warning', 'User must be paid + email-verified before syncing Drive access.');
            redirect('/?r=/admin/user-edit&id=' . $userId);
        }

        $songs = admin_list_songs_with_drive($db);
        $ok = 0;
        $err = 0;
        foreach ($songs as $s) {
            try {
                ensure_drive_access_for_user($db, $s, $target);
                $ok++;
            } catch (Throwable $e) {
                $err++;
            }
        }
        flash('success', "Drive sync done. ok={$ok}, errors={$err}.");
        redirect('/?r=/admin/user-edit&id=' . $userId);
        break;

    case '/admin/analytics':
        require_admin();
        $pageTitle = 'Admin · Analytics';
        render('admin_analytics', [
            'topSongs' => top_songs($db, 25),
            'topArtists' => top_artists($db, 25),
            'playsByDay' => plays_by_day($db, 14),
        ]);
        break;

    case '/admin/email':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            set_setting($db, 'smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
            set_setting($db, 'smtp_host', trim((string)($_POST['smtp_host'] ?? '')));
            set_setting($db, 'smtp_port', trim((string)($_POST['smtp_port'] ?? '587')));
            set_setting($db, 'smtp_encryption', trim((string)($_POST['smtp_encryption'] ?? 'tls')));
            set_setting($db, 'smtp_username', trim((string)($_POST['smtp_username'] ?? '')));
            set_setting($db, 'smtp_password', (string)($_POST['smtp_password'] ?? ''));
            set_setting($db, 'smtp_from_email', trim((string)($_POST['smtp_from_email'] ?? EMAIL_FROM)));
            set_setting($db, 'smtp_from_name', trim((string)($_POST['smtp_from_name'] ?? 'Karaoke OS')));

            if (isset($_POST['send_test'])) {
                $to = trim((string)($_POST['test_to'] ?? ''));
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    flash('danger', 'Test email address invalid.');
                    redirect('/?r=/admin/email');
                }
                try {
                    send_email_smtp($db, $to, $to, 'Karaoke OS SMTP Test', 'SMTP test OK.', '<p><strong>SMTP test OK.</strong></p>');
                    flash('success', 'Test email sent.');
                } catch (Throwable $e) {
                    flash('danger', 'Test email failed: ' . $e->getMessage());
                }
            } else {
                flash('success', 'Email settings saved.');
            }
            redirect('/?r=/admin/email');
        }

        $pageTitle = 'Admin · Email (SMTP)';
        render('admin_email', [
            'smtp' => get_smtp_settings($db),
        ]);
        break;

    case '/admin/api/song-check':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        $title = trim((string)($_GET['title'] ?? ''));
        $artist = trim((string)($_GET['artist'] ?? ''));
        $drive = trim((string)($_GET['drive'] ?? ''));
        $excludeId = (int)($_GET['exclude_id'] ?? 0);
        if ($excludeId <= 0) {
            $excludeId = 0;
        }
        $fileId = $drive !== '' ? (drive_extract_file_id($drive) ?? '') : '';
        if ($title === '' || $artist === '') {
            echo json_encode(['ok' => true, 'matches' => []], JSON_UNESCAPED_SLASHES);
            exit;
        }
        $driveUrl = is_safe_external_url($drive) ? $drive : null;
        $matches = find_song_duplicates($db, $excludeId > 0 ? $excludeId : null, $title, $artist, $fileId !== '' ? $fileId : null, $driveUrl);
        echo json_encode(['ok' => true, 'matches' => $matches], JSON_UNESCAPED_SLASHES);
        exit;

    case '/admin/api/song-lookup':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        $title = trim((string)($_GET['title'] ?? ''));
        $artist = trim((string)($_GET['artist'] ?? ''));
        if ($title === '' || $artist === '') {
            echo json_encode(['ok' => false, 'error' => 'missing'], JSON_UNESCAPED_SLASHES);
            exit;
        }
        try {
            $meta = lookup_song_metadata($title, $artist);
            echo json_encode(['ok' => true, 'meta' => $meta], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'lookup_failed'], JSON_UNESCAPED_SLASHES);
        }
        exit;

    case '/admin/api/song-candidates':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        $title = trim((string)($_GET['title'] ?? ''));
        $artist = trim((string)($_GET['artist'] ?? ''));
        if ($title === '' || $artist === '') {
            echo json_encode(['ok' => false, 'error' => 'missing'], JSON_UNESCAPED_SLASHES);
            exit;
        }
        try {
            $candidates = lookup_cover_candidates($title, $artist);
            echo json_encode(['ok' => true, 'candidates' => $candidates], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'lookup_failed'], JSON_UNESCAPED_SLASHES);
        }
        exit;

    case '/admin/api/artist-suggest':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        $q = (string)($_GET['q'] ?? '');
        $items = suggest_artists($db, $q, 12);
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
        exit;

    case '/admin/api/song-suggest':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        $q = (string)($_GET['q'] ?? '');
        $artist = (string)($_GET['artist'] ?? '');
        $items = suggest_songs($db, $q, $artist, 12);
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
        exit;

    default:
        http_response_code(404);
        $pageTitle = 'Not Found';
        render('error', ['message' => 'Page not found.']);
        break;
}
