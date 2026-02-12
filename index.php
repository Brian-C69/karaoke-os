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

    case '/usage':
        require_login();
        $pageTitle = 'Usage';
        $uid = (int)(current_user()['id'] ?? 0);

        $now = new DateTimeImmutable('now');
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+7 days');
        $lastWeekStart = $weekStart->modify('-7 days');
        $lastWeekEnd = $weekStart;

        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $nextMonthStart = $monthStart->modify('+1 month');
        $lastMonthStart = $monthStart->modify('-1 month');

        $weekStartStr = $weekStart->format('Y-m-d H:i:s');
        $weekEndStr = $weekEnd->format('Y-m-d H:i:s');
        $lastWeekStartStr = $lastWeekStart->format('Y-m-d H:i:s');
        $lastWeekEndStr = $lastWeekEnd->format('Y-m-d H:i:s');
        $monthStartStr = $monthStart->format('Y-m-d H:i:s');
        $nextMonthStartStr = $nextMonthStart->format('Y-m-d H:i:s');
        $lastMonthStartStr = $lastMonthStart->format('Y-m-d H:i:s');

        $weekByDay = user_plays_by_day_between($db, $uid, $weekStartStr, $weekEndStr);
        $weekTotal = user_play_count_between($db, $uid, $weekStartStr, $weekEndStr);
        $lastWeekByDay = user_plays_by_day_between($db, $uid, $lastWeekStartStr, $lastWeekEndStr);
        $lastWeekTotal = user_play_count_between($db, $uid, $lastWeekStartStr, $lastWeekEndStr);
        $thisMonthTotal = user_play_count_between($db, $uid, $monthStartStr, $nextMonthStartStr);
        $lastMonthTotal = user_play_count_between($db, $uid, $lastMonthStartStr, $monthStartStr);

        render('usage', [
            'userFull' => current_user_full($db),
            'weekStart' => $weekStart,
            'weekByDay' => $weekByDay,
            'weekTotal' => $weekTotal,
            'lastWeekStart' => $lastWeekStart,
            'lastWeekByDay' => $lastWeekByDay,
            'lastWeekTotal' => $lastWeekTotal,
            'thisMonthStart' => $monthStart,
            'thisMonthTotal' => $thisMonthTotal,
            'lastMonthStart' => $lastMonthStart,
            'lastMonthTotal' => $lastMonthTotal,
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

    case '/contact':
        $user = current_user();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $hp = trim((string)($_POST['website'] ?? ''));
            if ($hp !== '') {
                flash('success', 'Message sent.');
                redirect('/?r=/contact');
            }

            if (!isset($_SESSION['contact_last_at'])) {
                $_SESSION['contact_last_at'] = 0;
            }
            $last = (int)$_SESSION['contact_last_at'];
            if ($last > 0 && (time() - $last) < 20) {
                flash('warning', 'Please wait a moment before sending again.');
                redirect('/?r=/contact');
            }
            $_SESSION['contact_last_at'] = time();

            $type = trim((string)($_POST['type'] ?? ''));
            $allowed = ['Issue' => true, 'Song request' => true, 'Feedback' => true];
            if (!isset($allowed[$type])) {
                flash('danger', 'Invalid type.');
                redirect('/?r=/contact');
            }

            $fromName = trim((string)($_POST['from_name'] ?? ''));
            $fromEmail = trim((string)($_POST['from_email'] ?? ''));
            if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Please enter a valid email (or leave it blank).');
                redirect('/?r=/contact');
            }

            $songTitle = trim((string)($_POST['song_title'] ?? ''));
            $songArtist = trim((string)($_POST['song_artist'] ?? ''));
            $songLink = trim((string)($_POST['song_link'] ?? ''));
            if ($songLink !== '' && !is_safe_external_url($songLink)) {
                $songLink = '';
            }

            $message = trim((string)($_POST['message'] ?? ''));
            if (function_exists('mb_substr')) {
                $message = mb_substr($message, 0, 4000);
            } else {
                $message = substr($message, 0, 4000);
            }
            if (strlen($message) < 5) {
                flash('danger', 'Message is too short.');
                redirect('/?r=/contact');
            }

            if ($user) {
                if ($fromName === '') $fromName = (string)($user['username'] ?? '');
                if ($fromEmail === '') $fromEmail = (string)($user['email'] ?? '');
            }

            try {
                send_contact_form_email($db, [
                    'type' => $type,
                    'message' => $message,
                    'song_title' => $songTitle,
                    'song_artist' => $songArtist,
                    'song_link' => $songLink,
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                    'username' => $user ? (string)($user['username'] ?? '') : '',
                    'user_id' => $user ? (int)($user['id'] ?? 0) : 0,
                    'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]);
                flash('success', 'Message sent.');
            } catch (Throwable $e) {
                flash('danger', 'Could not send message. Ask admin to configure SMTP + contact email.');
            }
            redirect('/?r=/contact');
        }

        $pageTitle = 'Contact';
        render('contact');
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
            'artist_id' => (int)$artist['id'],
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

    case '/recent':
        require_login();
        $pageTitle = 'Recently played';
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'recent')),
            'mode' => trim((string)($_GET['mode'] ?? 'unique')),
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
        $mode = strtolower(trim((string)$filters['mode']));
        $total = count_recent_songs($db, $uid, $filters, $mode);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_recent_songs($db, $uid, $filters, $pager->limit(), $pager->offset(), $mode);
        $favoriteIds = favorite_song_ids($db, $uid, array_map(fn ($s) => (int)$s['id'], $songs));
        render('recent', [
            'filters' => $filters,
            'mode' => $mode,
            'view' => $view,
            'pager' => $pager,
            'songs' => $songs,
            'favoriteIds' => $favoriteIds,
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
            'artist_id' => (int)($_GET['artist_id'] ?? 0),
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

    case '/api/recent':
        header('Content-Type: application/json; charset=utf-8');
        if (!current_user()) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'sort' => trim((string)($_GET['sort'] ?? 'recent')),
            'mode' => trim((string)($_GET['mode'] ?? 'unique')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) $perPage = 20;
        $perPage = min(100, $perPage);
        $uid = (int)current_user()['id'];
        $mode = strtolower(trim((string)$filters['mode']));
        $total = count_recent_songs($db, $uid, $filters, $mode);
        $pager = new SimplePager($total, $page, $perPage);
        $songs = find_recent_songs($db, $uid, $filters, $pager->limit(), $pager->offset(), $mode);
        $favSet = favorite_song_ids($db, $uid, array_map(fn ($s) => (int)$s['id'], $songs));
        $songs = array_map(static function (array $s) use ($mode, $favSet): array {
            $lang = (string)($s['language'] ?? '');
            $id = (int)($s['id'] ?? 0);
            $playedAt = $mode === 'history' ? (string)($s['played_at'] ?? '') : (string)($s['last_played_at'] ?? '');
            return [
                'id' => $id,
                'title' => (string)($s['title'] ?? ''),
                'artist' => (string)($s['artist'] ?? ''),
                'cover_url' => (string)($s['cover_url'] ?? ''),
                'language' => $lang,
                'language_flag' => language_flag_url($lang) ?: null,
                'favorited' => !empty($favSet[$id]),
                'played_at' => $playedAt,
                'user_play_count' => $mode === 'history' ? 1 : (int)($s['user_play_count'] ?? 0),
            ];
        }, array_slice($songs, 0, 200));
        echo json_encode(['ok' => true, 'songs' => $songs, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

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
        $artistRow = upsert_artist($db, $artist);
        $artistId = is_array($artistRow) ? (int)($artistRow['id'] ?? 0) : 0;
        if ($artistId <= 0) {
            $artistId = 0;
        }
        $stmt = $db->prepare(
            'INSERT INTO songs (title, artist, artist_id, language, album, cover_url, drive_url, drive_file_id, is_active, created_at, updated_at)
             VALUES (:t, :a, :aid, :l, :al, :c, :d, :fid, :ia, :ca, :ua)'
        );
        $stmt->execute([
            ':t' => $title,
            ':a' => $artist,
            ':aid' => $artistId > 0 ? $artistId : null,
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

    case '/admin/tools':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'import_songs_csv') {
                $file = $_FILES['csv'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    flash('danger', 'Upload a CSV file.');
                    redirect('/?r=/admin/tools');
                }
                $tmp = (string)($file['tmp_name'] ?? '');
                $size = (int)($file['size'] ?? 0);
                if ($tmp === '' || $size <= 0) {
                    flash('danger', 'CSV upload is empty.');
                    redirect('/?r=/admin/tools');
                }
                if ($size > 10_000_000) {
                    flash('danger', 'CSV is too large (max 10MB).');
                    redirect('/?r=/admin/tools');
                }

                $lookupMeta = !empty($_POST['lookup_meta']);
                $res = admin_import_songs_csv($db, $tmp, $lookupMeta);
                if (!empty($res['ok'])) {
                    $msg = sprintf(
                        'Import complete: %d added, %d duplicates skipped, %d errors.',
                        (int)($res['inserted'] ?? 0),
                        (int)($res['skipped'] ?? 0),
                        (int)($res['errors'] ?? 0)
                    );
                    flash('success', $msg);
                    if (!empty($res['error_messages']) && is_array($res['error_messages'])) {
                        flash('danger', 'Import errors: ' . implode(' ; ', array_slice($res['error_messages'], 0, 6)));
                    }
                } else {
                    flash('danger', 'Import failed.');
                }
                redirect('/?r=/admin/tools');
            }

            flash('danger', 'Unknown action.');
            redirect('/?r=/admin/tools');
        }
        $pageTitle = 'Admin · Tools';
        render('admin_tools');
        break;

    case '/admin/export-songs':
        require_admin();
        header('Content-Type: text/csv; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="karaoke_songs_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['id', 'title', 'artist', 'artist_id', 'language', 'album', 'cover_url', 'genre', 'year', 'drive_url', 'drive_file_id', 'is_active', 'created_at', 'updated_at']);
        $stmt = $db->query('SELECT id, title, artist, artist_id, language, album, cover_url, genre, year, drive_url, drive_file_id, is_active, created_at, updated_at FROM songs ORDER BY id ASC');
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                (int)($r['id'] ?? 0),
                (string)($r['title'] ?? ''),
                (string)($r['artist'] ?? ''),
                $r['artist_id'] === null ? '' : (int)$r['artist_id'],
                (string)($r['language'] ?? ''),
                (string)($r['album'] ?? ''),
                (string)($r['cover_url'] ?? ''),
                (string)($r['genre'] ?? ''),
                $r['year'] === null ? '' : (int)$r['year'],
                (string)($r['drive_url'] ?? ''),
                (string)($r['drive_file_id'] ?? ''),
                (int)($r['is_active'] ?? 0),
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]);
        }
        fclose($out);
        exit;

    case '/admin/export-artists':
        require_admin();
        header('Content-Type: text/csv; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="karaoke_artists_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['id', 'name', 'image_url', 'musicbrainz_id', 'created_at', 'updated_at']);
        $stmt = $db->query('SELECT id, name, image_url, musicbrainz_id, created_at, updated_at FROM artists ORDER BY id ASC');
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                (int)($r['id'] ?? 0),
                (string)($r['name'] ?? ''),
                (string)($r['image_url'] ?? ''),
                (string)($r['musicbrainz_id'] ?? ''),
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]);
        }
        fclose($out);
        exit;

    case '/admin/export-plays':
        require_admin();
        header('Content-Type: text/csv; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="karaoke_plays_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['id', 'played_at', 'ip', 'user_agent', 'user_id', 'username', 'song_id', 'title', 'artist']);
        $stmt = $db->query(
            'SELECT
                p.id,
                p.played_at,
                p.ip,
                p.user_agent,
                u.id AS user_id,
                u.username,
                s.id AS song_id,
                s.title,
                s.artist
             FROM plays p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN songs s ON s.id = p.song_id
             ORDER BY p.id ASC'
        );
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                (int)($r['id'] ?? 0),
                (string)($r['played_at'] ?? ''),
                (string)($r['ip'] ?? ''),
                (string)($r['user_agent'] ?? ''),
                (int)($r['user_id'] ?? 0),
                (string)($r['username'] ?? ''),
                (int)($r['song_id'] ?? 0),
                (string)($r['title'] ?? ''),
                (string)($r['artist'] ?? ''),
            ]);
        }
        fclose($out);
        exit;

    case '/admin/backup-db':
        require_admin();
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'karaoke_os_backup_' . date('Ymd_His') . '.sqlite';
        $tmp = str_replace('\\', '/', $tmp);
        $tmpSql = str_replace("'", "''", $tmp);
        try {
            $bdb = new PDO('sqlite:' . DB_PATH);
            $bdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $bdb->exec("VACUUM INTO '{$tmpSql}';");
        } catch (Throwable $e) {
            flash('danger', 'Could not create DB backup.');
            redirect('/?r=/admin/tools');
        }
        if (!is_file($tmp) || filesize($tmp) <= 0) {
            flash('danger', 'DB backup file missing.');
            redirect('/?r=/admin/tools');
        }
        header('Content-Type: application/x-sqlite3');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="karaoke_backup_' . date('Ymd_His') . '.sqlite"');
        header('Content-Length: ' . filesize($tmp));
        register_shutdown_function(static function () use ($tmp) {
            @unlink($tmp);
        });
        readfile($tmp);
        exit;

    case '/admin/songs':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'bulk_update') {
                $bulk = strtolower(trim((string)($_POST['bulk_action'] ?? '')));
                $ids = $_POST['song_ids'] ?? [];
                if (!is_array($ids)) $ids = [];
                $songIds = [];
                foreach ($ids as $v) {
                    $id = (int)$v;
                    if ($id > 0) $songIds[$id] = true;
                }
                $songIds = array_keys($songIds);

                if (!$songIds) {
                    flash('info', 'No songs selected.');
                    redirect('/?r=/admin/songs&view=' . urlencode((string)($_GET['view'] ?? 'active')));
                }

                if (!in_array($bulk, ['enable', 'disable'], true)) {
                    flash('danger', 'Invalid bulk action.');
                    redirect('/?r=/admin/songs&view=' . urlencode((string)($_GET['view'] ?? 'active')));
                }

                $count = 0;
                try {
                    $count = admin_set_songs_active($db, $songIds, $bulk === 'enable');
                } catch (Throwable $e) {
                    $count = 0;
                }

                flash('success', sprintf('Bulk update complete: %d song(s) updated.', $count));
                redirect('/?r=/admin/songs&view=' . urlencode((string)($_GET['view'] ?? 'active')));
            }

            if ($action === 'bulk_insert') {
                $raw = (string)($_POST['bulk_lines'] ?? '');
                $lines = preg_split("/\\r\\n|\\n|\\r/", $raw);
                $inserted = 0;
                $skipped = 0;
                $errors = [];

                foreach ($lines as $idx => $line) {
                    $lineNum = $idx + 1;
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    $parts = preg_split('/\\s*\\|\\s*|\\t+/', $line);
                    $parts = array_map('trim', is_array($parts) ? $parts : []);
                    $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));

                    $title = $parts[0] ?? '';
                    $artist = $parts[1] ?? '';
                    $driveInput = $parts[2] ?? '';
                    $language = $parts[3] ?? '';

                    if ($title === '' || $artist === '' || $driveInput === '') {
                        $errors[] = "Line {$lineNum}: missing fields (Title | Artist | Drive).";
                        continue;
                    }

                    $fileId = drive_extract_file_id($driveInput);
                    $driveUrl = is_safe_external_url($driveInput) ? $driveInput : ($fileId ? drive_view_url($fileId) : null);
                    if (!$driveUrl || !is_safe_external_url($driveUrl)) {
                        $errors[] = "Line {$lineNum}: invalid Drive URL/ID.";
                        continue;
                    }

                    $dupes = find_song_duplicates($db, null, $title, $artist, $fileId, $driveUrl);
                    if ($dupes) {
                        $skipped++;
                        continue;
                    }

                    $now = now_db();
                    $artistRow = upsert_artist($db, $artist);
                    $artistId = is_array($artistRow) ? (int)($artistRow['id'] ?? 0) : 0;
                    if ($artistId <= 0) $artistId = 0;

                    try {
                        $stmt = $db->prepare(
                            'INSERT INTO songs (title, artist, artist_id, language, drive_url, drive_file_id, is_active, created_at, updated_at)
                             VALUES (:t, :a, :aid, :l, :d, :fid, 1, :c, :u)'
                        );
                        $stmt->execute([
                            ':t' => $title,
                            ':a' => $artist,
                            ':aid' => $artistId > 0 ? $artistId : null,
                            ':l' => $language !== '' ? $language : null,
                            ':d' => $driveUrl,
                            ':fid' => $fileId,
                            ':c' => $now,
                            ':u' => $now,
                        ]);
                        $inserted++;
                    } catch (Throwable $e) {
                        $errors[] = "Line {$lineNum}: insert failed.";
                        continue;
                    }
                }

                if ($inserted > 0) {
                    flash('success', sprintf('Bulk insert: %d added, %d duplicate(s) skipped.', $inserted, $skipped));
                } else {
                    flash('info', sprintf('Bulk insert: %d added, %d duplicate(s) skipped.', $inserted, $skipped));
                }
                if ($errors) {
                    $msg = 'Bulk insert errors: ' . implode(' ; ', array_slice($errors, 0, 6));
                    if (count($errors) > 6) $msg .= ' ; (+' . (count($errors) - 6) . ' more)';
                    flash('danger', $msg);
                }
                redirect('/?r=/admin/songs&view=' . urlencode((string)($_GET['view'] ?? 'active')));
            }

            flash('danger', 'Unknown action.');
            redirect('/?r=/admin/songs&view=' . urlencode((string)($_GET['view'] ?? 'active')));
        }
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
            } elseif ($action === 'cleanup_unused_artist_images') {
                $dryRun = !empty($_POST['dry_run']);
                $res = cleanup_unused_artist_uploads($db, $dryRun);
                if (!empty($res['ok'])) {
                    if (!empty($res['dry_run'])) {
                        flash('info', sprintf('Preview cleanup: would delete %d file(s) (scanned %d).', (int)($res['deleted'] ?? 0), (int)($res['scanned'] ?? 0)));
                    } else {
                        flash('success', sprintf('Cleanup complete: deleted %d file(s) (scanned %d).', (int)($res['deleted'] ?? 0), (int)($res['scanned'] ?? 0)));
                    }
                } else {
                    flash('danger', 'Cleanup failed.');
                }
            } elseif ($action === 'bulk_artist_images') {
                $bulk = strtolower(trim((string)($_POST['bulk_action'] ?? '')));
                $ids = $_POST['artist_ids'] ?? [];
                if (!is_array($ids)) $ids = [];
                $artistIds = [];
                foreach ($ids as $v) {
                    $id = (int)$v;
                    if ($id > 0) $artistIds[$id] = true;
                }
                $artistIds = array_keys($artistIds);

                if (!$artistIds) {
                    flash('info', 'No artists selected.');
                    redirect('/?r=/admin/artists');
                }
                if (!in_array($bulk, ['fetch', 'refresh'], true)) {
                    flash('danger', 'Invalid bulk action.');
                    redirect('/?r=/admin/artists');
                }

                $ok = 0;
                $fail = 0;
                $skip = 0;

                $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
                $stmt = $db->prepare('SELECT id, name, image_url FROM artists WHERE id IN (' . $placeholders . ')');
                $stmt->execute($artistIds);
                $rows = $stmt->fetchAll();

                foreach ($rows as $r) {
                    $id = (int)($r['id'] ?? 0);
                    $name = trim((string)($r['name'] ?? ''));
                    $current = trim((string)($r['image_url'] ?? ''));
                    if ($id <= 0 || $name === '') {
                        $fail++;
                        continue;
                    }

                    if ($bulk === 'fetch' && $current !== '') {
                        $skip++;
                        continue;
                    }
                    if ($bulk === 'refresh') {
                        try {
                            purge_cached_artist_image_files($id);
                        } catch (Throwable $e) {
                            // ignore
                        }
                    }

                    $remote = null;
                    $mbid = null;
                    try {
                        $meta = lookup_artist_image($name);
                        if (is_array($meta)) {
                            $remote = !empty($meta['image_url']) ? (string)$meta['image_url'] : null;
                            $mbid = !empty($meta['musicbrainz_id']) ? (string)$meta['musicbrainz_id'] : null;
                        }
                    } catch (Throwable $e) {
                        $remote = null;
                    }

                    $cached = null;
                    if (is_string($remote) && $remote !== '' && is_safe_external_url($remote)) {
                        try {
                            $cached = cache_artist_image_url($id, $name, $remote);
                        } catch (Throwable $e) {
                            $cached = null;
                        }
                    }

                    $didUpdate = false;
                    try {
                        if ($cached) {
                            $u = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                            $u->execute([':img' => $cached, ':u' => now_db(), ':id' => $id]);
                            $didUpdate = true;
                        }
                        if (is_string($mbid) && trim($mbid) !== '') {
                            $u = $db->prepare(
                                'UPDATE artists
                                 SET musicbrainz_id = CASE WHEN musicbrainz_id IS NULL OR TRIM(musicbrainz_id) = \'\' THEN :mb ELSE musicbrainz_id END,
                                     updated_at = :u
                                 WHERE id = :id'
                            );
                            $u->execute([':mb' => $mbid, ':u' => now_db(), ':id' => $id]);
                            $didUpdate = true;
                        }
                    } catch (Throwable $e) {
                        $didUpdate = false;
                    }

                    if ($cached || $didUpdate) $ok++;
                    else $fail++;
                }

                flash('success', sprintf('Bulk artist images: %d updated, %d skipped, %d failed.', $ok, $skip, $fail));
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
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'fetch_artist_image') {
                $name = trim((string)($artist['name'] ?? ''));
                $current = trim((string)($artist['image_url'] ?? ''));
                $currentIsExternal = $current !== '' && is_safe_external_url($current);

                $remote = $currentIsExternal ? $current : null;
                $mbid = null;

                if ($remote === null) {
                    try {
                        $meta = lookup_artist_image($name);
                        if (is_array($meta)) {
                            $remote = !empty($meta['image_url']) ? (string)$meta['image_url'] : null;
                            $mbid = !empty($meta['musicbrainz_id']) ? (string)$meta['musicbrainz_id'] : null;
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                }

                $cached = null;
                if (is_string($remote) && $remote !== '' && is_safe_external_url($remote)) {
                    try {
                        $cached = cache_artist_image_url((int)$artist['id'], $name, $remote);
                    } catch (Throwable $e) {
                        $cached = null;
                    }
                }

                $didUpdate = false;
                if ($cached) {
                    $stmt = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                    $stmt->execute([':img' => $cached, ':u' => now_db(), ':id' => (int)$artist['id']]);
                    $didUpdate = true;
                }
                if (is_string($mbid) && trim($mbid) !== '') {
                    $stmt = $db->prepare(
                        'UPDATE artists
                         SET musicbrainz_id = CASE WHEN musicbrainz_id IS NULL OR TRIM(musicbrainz_id) = \'\' THEN :mb ELSE musicbrainz_id END,
                             updated_at = :u
                         WHERE id = :id'
                    );
                    $stmt->execute([':mb' => $mbid, ':u' => now_db(), ':id' => (int)$artist['id']]);
                    $didUpdate = true;
                }

                if ($cached) {
                    flash('success', 'Artist image fetched and cached locally.');
                } elseif ($didUpdate) {
                    flash('info', 'No image found, but metadata was updated.');
                } else {
                    flash('danger', 'Could not fetch artist image.');
                }

                redirect('/?r=/admin/artist-edit&id=' . (int)$artist['id']);
            }
            if ($action === 'force_refresh_artist_image') {
                $name = trim((string)($artist['name'] ?? ''));
                $deleted = 0;
                try {
                    $deleted = purge_cached_artist_image_files((int)$artist['id']);
                } catch (Throwable $e) {
                    $deleted = 0;
                }

                $remote = null;
                $mbid = null;
                try {
                    $meta = lookup_artist_image($name);
                    if (is_array($meta)) {
                        $remote = !empty($meta['image_url']) ? (string)$meta['image_url'] : null;
                        $mbid = !empty($meta['musicbrainz_id']) ? (string)$meta['musicbrainz_id'] : null;
                    }
                } catch (Throwable $e) {
                    // ignore
                }

                $cached = null;
                if (is_string($remote) && $remote !== '' && is_safe_external_url($remote)) {
                    try {
                        $cached = cache_artist_image_url((int)$artist['id'], $name, $remote);
                    } catch (Throwable $e) {
                        $cached = null;
                    }
                }

                $didUpdate = false;
                if ($cached) {
                    $stmt = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                    $stmt->execute([':img' => $cached, ':u' => now_db(), ':id' => (int)$artist['id']]);
                    $didUpdate = true;
                }
                if (is_string($mbid) && trim($mbid) !== '') {
                    $stmt = $db->prepare(
                        'UPDATE artists
                         SET musicbrainz_id = CASE WHEN musicbrainz_id IS NULL OR TRIM(musicbrainz_id) = \'\' THEN :mb ELSE musicbrainz_id END,
                             updated_at = :u
                         WHERE id = :id'
                    );
                    $stmt->execute([':mb' => $mbid, ':u' => now_db(), ':id' => (int)$artist['id']]);
                    $didUpdate = true;
                }

                if ($cached) {
                    flash('success', sprintf('Artist image refreshed (purged %d cached file(s)).', $deleted));
                } elseif ($didUpdate) {
                    flash('info', sprintf('No image found, but metadata was updated (purged %d cached file(s)).', $deleted));
                } else {
                    flash('danger', sprintf('Could not refresh artist image (purged %d cached file(s)).', $deleted));
                }

                redirect('/?r=/admin/artist-edit&id=' . (int)$artist['id']);
            }
            if ($action === 'rename_merge_artist') {
                $newName = trim((string)($_POST['artist_new_name'] ?? ''));
                if ($newName === '') {
                    flash('danger', 'New artist name is required.');
                    redirect('/?r=/admin/artist-edit&id=' . (int)$artist['id']);
                }

                $oldId = (int)$artist['id'];
                $oldName = (string)($artist['name'] ?? '');
                $now = now_db();

                // If the name already exists, merge into it (unless it's the same artist).
                $target = null;
                $stmt = $db->prepare('SELECT id, name, image_url FROM artists WHERE name = :n COLLATE NOCASE LIMIT 1');
                $stmt->execute([':n' => $newName]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $target = $row;
                }

                $db->beginTransaction();
                try {
                    if (is_array($target) && (int)($target['id'] ?? 0) > 0 && (int)$target['id'] !== $oldId) {
                        $targetId = (int)$target['id'];
                        $targetName = (string)($target['name'] ?? $newName);

                        // Move songs to the target artist.
                        $u = $db->prepare(
                            'UPDATE songs
                             SET artist_id = :tid, artist = :tn, updated_at = :u
                             WHERE artist_id = :oid
                                OR ((artist_id IS NULL OR artist_id = 0) AND lower(artist) = lower(:on))'
                        );
                        $u->execute([':tid' => $targetId, ':tn' => $targetName, ':u' => $now, ':oid' => $oldId, ':on' => $oldName]);

                        // Prefer keeping an existing target image; only copy if missing.
                        $srcImg = trim((string)($artist['image_url'] ?? ''));
                        $tgtImg = trim((string)($target['image_url'] ?? ''));
                        if ($tgtImg === '' && $srcImg !== '') {
                            $u2 = $db->prepare('UPDATE artists SET image_url = :img, updated_at = :u WHERE id = :id');
                            $u2->execute([':img' => $srcImg, ':u' => $now, ':id' => $targetId]);
                        } else {
                            $touch = $db->prepare('UPDATE artists SET updated_at = :u WHERE id = :id');
                            $touch->execute([':u' => $now, ':id' => $targetId]);
                        }

                        // Remove the source artist row.
                        $d = $db->prepare('DELETE FROM artists WHERE id = :id');
                        $d->execute([':id' => $oldId]);

                        $db->commit();
                        flash('success', sprintf('Merged "%s" into "%s".', $oldName, $targetName));
                        redirect('/?r=/admin/artist-edit&id=' . $targetId);
                    }

                    // Rename (or casing change).
                    $u = $db->prepare('UPDATE artists SET name = :n, updated_at = :u WHERE id = :id');
                    $u->execute([':n' => $newName, ':u' => $now, ':id' => $oldId]);

                    $u2 = $db->prepare(
                        'UPDATE songs
                         SET artist = :n, updated_at = :u
                         WHERE artist_id = :id
                            OR ((artist_id IS NULL OR artist_id = 0) AND lower(artist) = lower(:on))'
                    );
                    $u2->execute([':n' => $newName, ':u' => $now, ':id' => $oldId, ':on' => $oldName]);

                    $db->commit();
                    flash('success', 'Artist renamed.');
                    redirect('/?r=/admin/artist-edit&id=' . $oldId);
                } catch (Throwable $e) {
                    $db->rollBack();
                    flash('danger', 'Could not rename/merge artist (name may already exist).');
                    redirect('/?r=/admin/artist-edit&id=' . (int)$artist['id']);
                }
            }

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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'bulk_update') {
                $bulk = strtolower(trim((string)($_POST['bulk_action'] ?? '')));
                $ids = $_POST['user_ids'] ?? [];
                if (!is_array($ids)) $ids = [];
                $userIds = [];
                foreach ($ids as $v) {
                    $id = (int)$v;
                    if ($id > 0) $userIds[$id] = true;
                }
                $userIds = array_keys($userIds);
                if (!$userIds) {
                    flash('info', 'No users selected.');
                    redirect('/?r=/admin/users');
                }

                $updated = 0;
                $skipped = 0;
                $selfId = (int)(current_user()['id'] ?? 0);

                try {
                    if ($bulk === 'revoke') {
                        if ($selfId > 0 && in_array($selfId, $userIds, true)) {
                            $skipped++;
                        }
                        $updated = admin_set_users_revoked($db, $userIds, true, $selfId);
                    } elseif ($bulk === 'restore') {
                        $updated = admin_set_users_revoked($db, $userIds, false);
                    } elseif ($bulk === 'set_paid') {
                        $updated = admin_set_users_paid($db, $userIds, true);
                    } elseif ($bulk === 'unset_paid') {
                        $updated = admin_set_users_paid($db, $userIds, false);
                    } elseif ($bulk === 'mark_verified') {
                        $updated = admin_set_users_email_verified($db, $userIds, true);
                    } elseif ($bulk === 'clear_verified') {
                        $updated = admin_set_users_email_verified($db, $userIds, false);
                    } elseif ($bulk === 'set_paid_until') {
                        $paidUntil = trim((string)($_POST['paid_until'] ?? ''));
                        if ($paidUntil !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $paidUntil)) {
                            flash('danger', 'Paid until must be YYYY-MM-DD.');
                            redirect('/?r=/admin/users');
                        }
                        $updated = admin_set_users_paid_until($db, $userIds, $paidUntil !== '' ? $paidUntil : null);
                    } elseif ($bulk === 'clear_paid_until') {
                        $updated = admin_set_users_paid_until($db, $userIds, null);
                    } else {
                        flash('danger', 'Invalid bulk action.');
                        redirect('/?r=/admin/users');
                    }
                } catch (Throwable $e) {
                    flash('danger', 'Bulk update failed.');
                    redirect('/?r=/admin/users');
                }

                $msg = sprintf('Bulk update complete: %d user(s) updated.', $updated);
                if ($skipped > 0) {
                    $msg .= sprintf(' %d skipped.', $skipped);
                }
                flash('success', $msg);
                redirect('/?r=/admin/users');
            }

            if ($action === 'bulk_insert') {
                $raw = (string)($_POST['bulk_lines'] ?? '');
                $lines = preg_split("/\\r\\n|\\n|\\r/", $raw);
                $inserted = 0;
                $skipped = 0;
                $errors = [];

                foreach ($lines as $idx => $line) {
                    $lineNum = $idx + 1;
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    $parts = preg_split('/\\s*\\|\\s*|\\t+/', $line);
                    $parts = array_map('trim', is_array($parts) ? $parts : []);
                    $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));

                    $username = $parts[0] ?? '';
                    $password = $parts[1] ?? '';
                    $role = strtolower($parts[2] ?? 'user');
                    $email = $parts[3] ?? '';
                    $paid = $parts[4] ?? '';
                    $paidUntil = $parts[5] ?? '';

                    if ($username === '' || $password === '') {
                        $errors[] = "Line {$lineNum}: missing fields (Username | Password).";
                        continue;
                    }
                    if (strlen($password) < 6) {
                        $errors[] = "Line {$lineNum}: password must be at least 6 characters.";
                        continue;
                    }
                    $role = $role === 'admin' ? 'admin' : 'user';

                    $email = trim((string)$email);
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Line {$lineNum}: invalid email.";
                        continue;
                    }

                    $isPaid = in_array(strtolower(trim((string)$paid)), ['1', 'y', 'yes', 'true'], true) ? 1 : 0;
                    $paidUntil = trim((string)$paidUntil);
                    if ($paidUntil !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $paidUntil)) {
                        $errors[] = "Line {$lineNum}: paid_until must be YYYY-MM-DD.";
                        continue;
                    }

                    try {
                        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, is_paid, paid_until, created_at) VALUES (:u, :e, :p, :r, :ip, :pu, :t)');
                        $stmt->execute([
                            ':u' => $username,
                            ':e' => $email !== '' ? $email : null,
                            ':p' => password_hash($password, PASSWORD_DEFAULT),
                            ':r' => $role,
                            ':ip' => $isPaid,
                            ':pu' => $paidUntil !== '' ? $paidUntil : null,
                            ':t' => now_db(),
                        ]);
                        $inserted++;
                    } catch (Throwable $e) {
                        $skipped++;
                    }
                }

                flash('success', sprintf('Bulk insert: %d added, %d skipped.', $inserted, $skipped));
                if ($errors) {
                    $msg = 'Bulk insert errors: ' . implode(' ; ', array_slice($errors, 0, 6));
                    if (count($errors) > 6) $msg .= ' ; (+' . (count($errors) - 6) . ' more)';
                    flash('danger', $msg);
                }
                redirect('/?r=/admin/users');
            }

            flash('danger', 'Unknown action.');
            redirect('/?r=/admin/users');
        }
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

    case '/admin/user-usage':
        require_admin();
        $userId = (int)($_GET['id'] ?? 0);
        $target = get_user($db, $userId);
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }

        $now = new DateTimeImmutable('now');
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+7 days');
        $lastWeekStart = $weekStart->modify('-7 days');
        $lastWeekEnd = $weekStart;

        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $nextMonthStart = $monthStart->modify('+1 month');
        $lastMonthStart = $monthStart->modify('-1 month');

        $weekStartStr = $weekStart->format('Y-m-d H:i:s');
        $weekEndStr = $weekEnd->format('Y-m-d H:i:s');
        $lastWeekStartStr = $lastWeekStart->format('Y-m-d H:i:s');
        $lastWeekEndStr = $lastWeekEnd->format('Y-m-d H:i:s');
        $monthStartStr = $monthStart->format('Y-m-d H:i:s');
        $nextMonthStartStr = $nextMonthStart->format('Y-m-d H:i:s');
        $lastMonthStartStr = $lastMonthStart->format('Y-m-d H:i:s');

        $weekByDay = user_plays_by_day_between($db, $userId, $weekStartStr, $weekEndStr);
        $weekTotal = user_play_count_between($db, $userId, $weekStartStr, $weekEndStr);
        $lastWeekByDay = user_plays_by_day_between($db, $userId, $lastWeekStartStr, $lastWeekEndStr);
        $lastWeekTotal = user_play_count_between($db, $userId, $lastWeekStartStr, $lastWeekEndStr);
        $thisMonthTotal = user_play_count_between($db, $userId, $monthStartStr, $nextMonthStartStr);
        $lastMonthTotal = user_play_count_between($db, $userId, $lastMonthStartStr, $monthStartStr);

        $pageTitle = 'Admin · Usage · ' . (string)($target['username'] ?? '');
        render('admin_user_usage', [
            'target' => $target,
            'weekStart' => $weekStart,
            'weekByDay' => $weekByDay,
            'weekTotal' => $weekTotal,
            'lastWeekStart' => $lastWeekStart,
            'lastWeekByDay' => $lastWeekByDay,
            'lastWeekTotal' => $lastWeekTotal,
            'thisMonthStart' => $monthStart,
            'thisMonthTotal' => $thisMonthTotal,
            'lastMonthStart' => $lastMonthStart,
            'lastMonthTotal' => $lastMonthTotal,
        ]);
        break;

    case '/admin/user-revoke':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId <= 0) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        if ($targetId === (int)current_user()['id']) {
            flash('danger', 'You cannot revoke your own account.');
            redirect('/?r=/admin/users');
        }
        $target = get_user($db, $targetId);
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        admin_set_user_revoked($db, $targetId, true);
        flash('success', 'User access revoked.');
        redirect('/?r=/admin/users');
        break;

    case '/admin/user-restore':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
        csrf_verify();
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId <= 0) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        $target = get_user($db, $targetId);
        if (!$target) {
            flash('danger', 'User not found.');
            redirect('/?r=/admin/users');
        }
        admin_set_user_revoked($db, $targetId, false);
        flash('success', 'User access restored.');
        redirect('/?r=/admin/users');
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
            set_setting($db, 'contact_to_email', trim((string)($_POST['contact_to_email'] ?? '')));
            set_setting($db, 'contact_to_name', trim((string)($_POST['contact_to_name'] ?? '')));

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
        $smtp = get_smtp_settings($db);
        $contact = get_contact_settings($db);
        $smtp['contact_to_email'] = (string)($contact['to_email'] ?? '');
        $smtp['contact_to_name'] = (string)($contact['to_name'] ?? '');
        render('admin_email', [
            'smtp' => $smtp,
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
