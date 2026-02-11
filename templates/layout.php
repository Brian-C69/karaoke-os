<?php
/** @var string $pageTitle */
/** @var array $flash */
/** @var array|null $user */
/** @var string $templateFile */
?>
<?php
  $currentRoute = $_GET['r'] ?? '/';
  if (!is_string($currentRoute)) $currentRoute = '/';
  $currentRoute = '/' . ltrim($currentRoute, '/');
  $isAdmin = substr($currentRoute, 0, 6) === '/admin';

  $activeHome = $currentRoute === '/';
  $activeSongs = $currentRoute === '/songs' || $currentRoute === '/song';
  $activeArtists = $currentRoute === '/artists' || $currentRoute === '/artist';
  $activeLanguages = $currentRoute === '/languages';
  $activeTop = $currentRoute === '/top';
  $activeLiked = $currentRoute === '/liked';
  $activeRecent = $currentRoute === '/recent';
  $activeFavorites = $currentRoute === '/favorites';
  $activePlaylists = $currentRoute === '/playlists' || $currentRoute === '/playlist';
  $activeUsage = $currentRoute === '/usage';
  $activeAccount = $currentRoute === '/account';
  $activeAdmin = $isAdmin;
  $activeLogin = $currentRoute === '/login';

  $mobileHome = $activeHome || $activeTop || $activeLanguages || $activeLiked;
  $mobileSongs = $activeSongs;
  $mobileArtists = $activeArtists;
  $mobileProfile = $activeAccount || $activeLogin || $activeUsage || $activeFavorites || $activePlaylists || $activeRecent || $activeAdmin;
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?> · Karaoke OS</title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="theme-color" content="#212529">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Karaoke OS">
  <meta name="pwa-sw" content="<?= e(APP_BASE) ?>/sw.js">
  <link rel="manifest" href="<?= e(APP_BASE) ?>/manifest.webmanifest">
  <link rel="icon" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.ico" sizes="any">
  <link rel="icon" type="image/png" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png">
  <link rel="apple-touch-icon" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="<?= e(APP_BASE) ?>/assets/js/theme.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(APP_BASE) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary" data-auth="<?= $user ? '1' : '0' ?>">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark d-none d-lg-flex">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="<?= e(APP_BASE) ?>/?r=/">
        <img src="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png" alt="" width="32" height="32" class="me-2" style="object-fit:contain;">
        Karaoke <span style="color:#db4143;">OS</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link<?= $activeSongs ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/songs"<?= $activeSongs ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Songs
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeArtists ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/artists"<?= $activeArtists ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Artists
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeLanguages ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/languages"<?= $activeLanguages ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-translate me-1" aria-hidden="true"></i>Languages
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeTop ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/top"<?= $activeTop ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-trophy me-1" aria-hidden="true"></i>Top 100
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= $activeLiked ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/liked"<?= $activeLiked ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-heart me-1" aria-hidden="true"></i>Most liked
            </a>
          </li>
          <?php if ($user): ?>
            <li class="nav-item">
              <a class="nav-link<?= $activeRecent ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/recent"<?= $activeRecent ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Recent
              </a>
            </li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav">
          <?php if ($user): ?>
            <li class="nav-item">
              <a class="nav-link<?= $activeFavorites ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/favorites"<?= $activeFavorites ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i>Favorites
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link<?= $activePlaylists ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/playlists"<?= $activePlaylists ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-collection-play me-1" aria-hidden="true"></i>Playlists
              </a>
            </li>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
              <li class="nav-item">
                <a class="nav-link<?= $activeAdmin ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/admin"<?= $activeAdmin ? ' aria-current="page"' : '' ?>>
                  <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Admin
                </a>
              </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link<?= ($activeAccount || $activeUsage) ? ' active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/account"<?= ($activeAccount || $activeUsage) ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>Account
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/login"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login</a></li>
          <?php endif; ?>
          <li class="nav-item d-flex align-items-center ms-lg-2">
            <button type="button" class="btn btn-sm btn-outline-secondary theme-toggle-btn" data-theme-toggle aria-label="Toggle theme" title="Toggle theme">
              <i class="bi bi-moon-stars-fill theme-icon-light" aria-hidden="true"></i>
              <i class="bi bi-sun-fill theme-icon-dark" aria-hidden="true"></i>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <?php foreach ($flash as $f): ?>
      <div class="alert alert-<?= e((string)$f['level']) ?>"><?= e((string)$f['message']) ?></div>
    <?php endforeach; ?>

    <?php require $templateFile; ?>
  </main>

  <nav class="mobile-bottom-nav d-lg-none fixed-bottom border-top bg-body">
    <div class="container">
      <div class="nav nav-pills nav-fill py-2">
        <a class="nav-link <?= $mobileHome ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/">
          <div><i class="bi bi-house" aria-hidden="true"></i></div>
          <div class="small">Home</div>
        </a>
        <a class="nav-link <?= $mobileSongs ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/songs">
          <div><i class="bi bi-music-note-list" aria-hidden="true"></i></div>
          <div class="small">Songs</div>
        </a>
        <a class="nav-link <?= $mobileArtists ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/artists">
          <div><i class="bi bi-person-lines-fill" aria-hidden="true"></i></div>
          <div class="small">Artists</div>
        </a>
        <a class="nav-link <?= $mobileProfile ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=<?= $user ? '/account' : '/login' ?>">
          <div><i class="bi bi-person-circle" aria-hidden="true"></i></div>
          <div class="small">Profile</div>
        </a>
      </div>
    </div>
  </nav>

  <footer class="border-top py-3 d-none d-lg-block">
    <div class="container small text-muted d-flex align-items-center justify-content-between">
      <div>Karaoke OS</div>
      <div>v<?= e(APP_VERSION) ?></div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(APP_BASE) ?>/assets/js/song-actions.js"></script>
  <script src="<?= e(APP_BASE) ?>/assets/js/no-right-click.js"></script>
  <script src="<?= e(APP_BASE) ?>/assets/js/pwa.js"></script>
  <script src="<?= e(APP_BASE) ?>/assets/js/flash.js"></script>
</body>
</html>
