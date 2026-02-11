<?php
/** @var array $userFull */
/** @var array $usageDay */
/** @var array $usageWeek */
/** @var array $usageMonth */
$usageDay = is_array($usageDay ?? null) ? $usageDay : [];
$usageWeek = is_array($usageWeek ?? null) ? $usageWeek : [];
$usageMonth = is_array($usageMonth ?? null) ? $usageMonth : [];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>Account</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <div class="text-muted small">Username</div>
        <div class="fw-semibold"><?= e((string)$userFull['username']) ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Role</div>
        <div class="fw-semibold"><?= e((string)$userFull['role']) ?></div>
      </div>
      <div class="col-md-8">
        <label class="form-label">Email</label>
        <form class="d-flex gap-2 flex-wrap" method="post" action="<?= e(APP_BASE) ?>/?r=/account/update-email">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="flex-grow-1" style="min-width: 220px; max-width: 460px;">
            <input class="form-control" name="email" value="<?= e((string)($userFull['email'] ?? '')) ?>" placeholder="you@example.com" required>
          </div>
          <button class="btn btn-outline-primary flex-shrink-0"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Update</button>
        </form>
        <div class="text-muted small mt-2">
          Status:
          <?php if (!empty($userFull['email_verified_at'])): ?>
            <span class="badge text-bg-success"><i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Verified</span>
          <?php else: ?>
            <span class="badge text-bg-warning"><i class="bi bi-patch-exclamation-fill me-1" aria-hidden="true"></i>Not verified</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-4">
        <form method="post" action="<?= e(APP_BASE) ?>/?r=/account/send-verification">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn btn-primary w-100" <?= !empty($userFull['email_verified_at']) ? 'disabled' : '' ?>><i class="bi bi-send me-1" aria-hidden="true"></i>Send verification email</button>
        </form>
        <div class="text-muted small mt-2">If mail isn’t configured, dev mode may show the link in a banner.</div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <div class="fw-semibold"><i class="bi bi-activity me-2" aria-hidden="true"></i>Your usage</div>
      <div class="text-muted small">Plays only (not song-specific).</div>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="text-muted small mb-1">Last 14 days</div>
        <?php if (!$usageDay): ?>
          <div class="text-muted small">No plays yet.</div>
        <?php else: ?>
          <div class="list-group list-group-flush small">
            <?php foreach ($usageDay as $r): ?>
              <div class="list-group-item d-flex align-items-center justify-content-between px-0">
                <span class="text-muted"><?= e((string)($r['day'] ?? '')) ?></span>
                <span class="fw-semibold"><?= (int)($r['play_count'] ?? 0) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="col-lg-4">
        <div class="text-muted small mb-1">Last 12 weeks</div>
        <?php if (!$usageWeek): ?>
          <div class="text-muted small">No plays yet.</div>
        <?php else: ?>
          <div class="list-group list-group-flush small">
            <?php foreach ($usageWeek as $r): ?>
              <div class="list-group-item d-flex align-items-center justify-content-between px-0">
                <span class="text-muted"><?= e((string)($r['week'] ?? '')) ?></span>
                <span class="fw-semibold"><?= (int)($r['play_count'] ?? 0) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="col-lg-4">
        <div class="text-muted small mb-1">Last 12 months</div>
        <?php if (!$usageMonth): ?>
          <div class="text-muted small">No plays yet.</div>
        <?php else: ?>
          <div class="list-group list-group-flush small">
            <?php foreach ($usageMonth as $r): ?>
              <div class="list-group-item d-flex align-items-center justify-content-between px-0">
                <span class="text-muted"><?= e((string)($r['month'] ?? '')) ?></span>
                <span class="fw-semibold"><?= (int)($r['play_count'] ?? 0) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="text-muted small">Paid access</div>
    <?php if ((int)($userFull['is_paid'] ?? 0) === 1): ?>
      <div class="fw-semibold">Active</div>
      <?php if (!empty($userFull['paid_until'])): ?>
        <div class="text-muted small">Until: <?= e((string)$userFull['paid_until']) ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="fw-semibold">Not active</div>
      <div class="text-muted small">Ask admin to activate your paid access.</div>
    <?php endif; ?>
  </div>
</div>
