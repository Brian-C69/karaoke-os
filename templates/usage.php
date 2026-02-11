<?php
/** @var array $userFull */
/** @var DateTimeImmutable $weekStart */
/** @var array $weekByDay */
/** @var int $weekTotal */
/** @var DateTimeImmutable $lastWeekStart */
/** @var array $lastWeekByDay */
/** @var int $lastWeekTotal */
/** @var DateTimeImmutable $thisMonthStart */
/** @var int $thisMonthTotal */
/** @var DateTimeImmutable $lastMonthStart */
/** @var int $lastMonthTotal */

$weekByDay = is_array($weekByDay ?? null) ? $weekByDay : [];
$weekMap = [];
foreach ($weekByDay as $r) {
  $day = (string)($r['day'] ?? '');
  if ($day !== '') $weekMap[$day] = (int)($r['play_count'] ?? 0);
}

// Ensure 7 rows (Mon..Sun).
$days = [];
for ($i = 0; $i < 7; $i++) {
  $d = $weekStart->modify('+' . $i . ' day');
  $key = $d->format('Y-m-d');
  $days[] = ['key' => $key, 'label' => $d->format('D'), 'count' => (int)($weekMap[$key] ?? 0)];
}

$lastWeekByDay = is_array($lastWeekByDay ?? null) ? $lastWeekByDay : [];
$lastWeekMap = [];
foreach ($lastWeekByDay as $r) {
  $day = (string)($r['day'] ?? '');
  if ($day !== '') $lastWeekMap[$day] = (int)($r['play_count'] ?? 0);
}
$lastWeekDays = [];
for ($i = 0; $i < 7; $i++) {
  $d = $lastWeekStart->modify('+' . $i . ' day');
  $key = $d->format('Y-m-d');
  $lastWeekDays[] = ['key' => $key, 'label' => $d->format('D'), 'count' => (int)($lastWeekMap[$key] ?? 0)];
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-activity me-2" aria-hidden="true"></i>Usage</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/account"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-calendar-week me-2" aria-hidden="true"></i>This week</div>
        <div class="text-muted small"><?= e($weekStart->format('Y-m-d')) ?></div>
      </div>
      <div class="card-body">
        <div class="list-group list-group-flush small">
          <?php foreach ($days as $d): ?>
            <div class="list-group-item d-flex align-items-center justify-content-between px-0">
              <span class="text-muted"><?= e($d['label']) ?> · <?= e($d['key']) ?></span>
              <span class="fw-semibold"><?= (int)$d['count'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-calendar-week me-2" aria-hidden="true"></i>Last week</div>
        <div class="text-muted small"><?= e($lastWeekStart->format('Y-m-d')) ?></div>
      </div>
      <div class="card-body">
        <div class="list-group list-group-flush small">
          <?php foreach ($lastWeekDays as $d): ?>
            <div class="list-group-item d-flex align-items-center justify-content-between px-0">
              <span class="text-muted"><?= e($d['label']) ?> · <?= e($d['key']) ?></span>
              <span class="fw-semibold"><?= (int)$d['count'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="row g-3">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">This week total</div>
              <div class="h4 m-0"><?= (int)$weekTotal ?></div>
            </div>
            <i class="bi bi-clock-history fs-2 text-muted" aria-hidden="true"></i>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">Last week total</div>
              <div class="h4 m-0"><?= (int)$lastWeekTotal ?></div>
            </div>
            <i class="bi bi-clock fs-2 text-muted" aria-hidden="true"></i>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">This month</div>
            <div class="h4 m-0"><?= (int)$thisMonthTotal ?></div>
            <div class="text-muted small mt-1"><?= e($thisMonthStart->format('Y-m')) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Last month</div>
            <div class="h4 m-0"><?= (int)$lastMonthTotal ?></div>
            <div class="text-muted small mt-1"><?= e($lastMonthStart->format('Y-m')) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
