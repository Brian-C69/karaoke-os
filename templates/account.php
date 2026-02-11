<?php /** @var array $userFull */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>Profile</h1>
</div>

<div class="card shadow-sm mb-3 d-lg-none">
  <div class="list-group list-group-flush">
    <div class="list-group-item text-muted small py-2">Browse</div>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/songs">
      <span><i class="bi bi-music-note-list me-2" aria-hidden="true"></i>Songs</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/artists">
      <span><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/languages">
      <span><i class="bi bi-translate me-2" aria-hidden="true"></i>Languages</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/top">
      <span><i class="bi bi-trophy me-2" aria-hidden="true"></i>Top 100</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/liked">
      <span><i class="bi bi-heart me-2" aria-hidden="true"></i>Most liked</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/recent">
      <span><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Recent</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
  </div>
</div>

<div class="card shadow-sm mb-3 d-lg-none">
  <div class="list-group list-group-flush">
    <div class="list-group-item text-muted small py-2">My stuff</div>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/favorites">
      <span><i class="bi bi-heart-fill me-2 text-danger" aria-hidden="true"></i>Favorites</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/playlists">
      <span><i class="bi bi-collection-play me-2" aria-hidden="true"></i>Playlists</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/usage">
      <span><i class="bi bi-activity me-2" aria-hidden="true"></i>Usage</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <?php if (((string)($userFull['role'] ?? '')) === 'admin'): ?>
      <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="<?= e(APP_BASE) ?>/?r=/admin">
        <span><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Admin</span>
        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
      </a>
    <?php endif; ?>
    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3" href="#settings">
      <span><i class="bi bi-gear me-2" aria-hidden="true"></i>Settings</span>
      <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/logout" class="m-0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 w-100 text-start">
        <span><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</span>
        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
      </button>
    </form>
  </div>
</div>

<div id="settings" class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <div>
        <div class="fw-semibold"><i class="bi bi-moon-stars me-2" aria-hidden="true"></i>Theme</div>
        <div class="text-muted small">Light / Dark mode</div>
      </div>
      <button type="button" class="btn btn-outline-secondary" data-theme-toggle>
        <i class="bi bi-moon-stars-fill me-1" aria-hidden="true"></i>Toggle
      </button>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <div class="text-muted small">Username</div>
        <div class="fw-semibold"><?= e((string)$userFull['username']) ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Email</div>
        <div class="fw-semibold"><?= e((string)($userFull['email'] ?? '—')) ?></div>
      </div>

      <div class="col-12">
        <div class="text-muted small mb-1">Status</div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php if (!empty($userFull['email_verified_at'])): ?>
            <span class="badge text-bg-success"><i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Verified</span>
          <?php else: ?>
            <span class="badge text-bg-warning"><i class="bi bi-patch-exclamation-fill me-1" aria-hidden="true"></i>Not verified</span>
          <?php endif; ?>

          <?php if ((int)($userFull['is_paid'] ?? 0) === 1): ?>
            <span class="badge text-bg-success"><i class="bi bi-credit-card-2-front-fill me-1" aria-hidden="true"></i>Paid</span>
          <?php else: ?>
            <span class="badge text-bg-secondary"><i class="bi bi-credit-card-2-front me-1" aria-hidden="true"></i>Not paid</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/usage"><i class="bi bi-activity me-1" aria-hidden="true"></i>Usage</a>
          <?php if (empty($userFull['email_verified_at'])): ?>
            <form method="post" action="<?= e(APP_BASE) ?>/?r=/account/send-verification" class="m-0">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <button class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>Send verification email</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-3 mb-3 d-lg-none">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div class="text-muted small">Version</div>
      <div class="fw-semibold">v<?= e(APP_VERSION) ?></div>
    </div>
  </div>
</div>
