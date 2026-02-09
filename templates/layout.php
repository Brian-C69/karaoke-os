<?php
/** @var string $pageTitle */
/** @var array $flash */
/** @var array|null $user */
/** @var string $templateFile */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · Karaoke OS</title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <link rel="icon" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.ico" sizes="any">
  <link rel="icon" type="image/png" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png">
  <link rel="apple-touch-icon" href="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(APP_BASE) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light" data-auth="<?= $user ? '1' : '0' ?>">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="<?= e(APP_BASE) ?>/?r=/">
        <img src="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png" alt="" width="24" height="24" class="me-2" style="object-fit:contain;">
        Karaoke OS
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Songs</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/artists"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Artists</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/languages"><i class="bi bi-translate me-1" aria-hidden="true"></i>Languages</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/top"><i class="bi bi-trophy me-1" aria-hidden="true"></i>Top 100</a></li>
        </ul>
        <ul class="navbar-nav">
          <?php if ($user): ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/favorites"><i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i>Favorites</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/playlists"><i class="bi bi-collection-play me-1" aria-hidden="true"></i>Playlists</a></li>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
              <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/admin"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Admin</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/account"><i class="bi bi-person-circle me-1" aria-hidden="true"></i>Account</a></li>
            <li class="nav-item"><span class="navbar-text me-2">Hi, <?= e((string)$user['username']) ?></span></li>
            <li class="nav-item">
              <form method="post" action="<?= e(APP_BASE) ?>/?r=/logout" class="d-inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Logout</button>
              </form>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(APP_BASE) ?>/?r=/login"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login</a></li>
          <?php endif; ?>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(APP_BASE) ?>/assets/js/song-actions.js"></script>
  <footer class="border-top py-3">
    <div class="container small text-muted d-flex align-items-center justify-content-between">
      <div>Karaoke OS</div>
      <div>v<?= e(APP_VERSION) ?></div>
    </div>
  </footer>
</body>
</html>
