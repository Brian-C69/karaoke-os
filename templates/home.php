<?php /** @var array $stats */ ?>
<div class="row g-3">
  <div class="col-12">
    <div class="p-4 bg-white rounded shadow-sm">
      <h1 class="h4 mb-2"><i class="bi bi-compass me-2" aria-hidden="true"></i>Browse & Rankings</h1>
      <div class="text-muted">Public can browse. Logged-in users can play, and every play is tracked.</div>
      <div class="mt-3 d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Browse Songs</a>
        <a class="btn btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/top"><i class="bi bi-trophy me-1" aria-hidden="true"></i>Top 100</a>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/artists"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Artists</a>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/languages"><i class="bi bi-translate me-1" aria-hidden="true"></i>Languages</a>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-6">
    <div class="stat card shadow-sm"><div class="card-body">
      <div class="text-muted small">Songs</div>
      <div class="fs-4 fw-semibold"><?= (int)$stats['songs'] ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat card shadow-sm"><div class="card-body">
      <div class="text-muted small">Artists</div>
      <div class="fs-4 fw-semibold"><?= (int)$stats['artists'] ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat card shadow-sm"><div class="card-body">
      <div class="text-muted small">Languages</div>
      <div class="fs-4 fw-semibold"><?= (int)$stats['languages'] ?></div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat card shadow-sm"><div class="card-body">
      <div class="text-muted small">Plays logged</div>
      <div class="fs-4 fw-semibold"><?= (int)$stats['plays'] ?></div>
    </div></div>
  </div>
</div>
