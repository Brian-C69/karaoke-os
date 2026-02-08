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
            'stats' => get_home_stats($db),
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
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        $perPage = min(100, $perPage);
        $total = count_songs($db, $filters);
        $pager = new SimplePager($total, $page, $perPage);
        render('songs', [
            'filters' => $filters,
            'pager' => $pager,
            'songs' => find_songs($db, $filters, $pager->limit(), $pager->offset()),
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
        render('song', [
            'song' => $song,
            'playCount' => (int)get_song_play_count($db, $songId),
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
        $url = trim((string)($song['drive_url'] ?? ''));
        if ($url === '' && !empty($song['drive_file_id'])) {
            $url = drive_view_url((string)$song['drive_file_id']);
        }
        if ($url === '') {
            flash('warning', 'This song has no link yet.');
            redirect('/?r=/song&id=' . $songId);
        }

        log_play($db, $songId, (int)current_user()['id']);
        if (!is_safe_external_url($url)) {
            flash('danger', 'Invalid link configured for this song.');
            redirect('/?r=/song&id=' . $songId);
        }
        header('Location: ' . $url, true, 302);
        exit;

    case '/artists':
        $pageTitle = 'Artists';
        render('artists', [
            'artists' => list_artists($db),
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
        render('top', [
            'rows' => top_songs($db, 100),
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
        // Keep payload small for AJAX.
        $songs = array_map(static function (array $s): array {
            return [
                'id' => (int)($s['id'] ?? 0),
                'title' => (string)($s['title'] ?? ''),
                'artist' => (string)($s['artist'] ?? ''),
                'cover_url' => (string)($s['cover_url'] ?? ''),
                'play_count' => (int)($s['play_count'] ?? 0),
            ];
        }, array_slice($songs, 0, 200));
        echo json_encode(['ok' => true, 'songs' => $songs, 'pager' => $pager->toArray()], JSON_UNESCAPED_SLASHES);
        exit;

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
        $fileId = $drive !== '' ? (drive_extract_file_id($drive) ?? '') : '';
        if ($title === '' || $artist === '') {
            echo json_encode(['ok' => true, 'matches' => []], JSON_UNESCAPED_SLASHES);
            exit;
        }
        $driveUrl = is_safe_external_url($drive) ? $drive : null;
        $matches = find_song_duplicates($db, null, $title, $artist, $fileId !== '' ? $fileId : null, $driveUrl);
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
