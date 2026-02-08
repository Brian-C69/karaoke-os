<?php
/** @var array $latestSongs */
/** @var array $topSongs */
/** @var array $topArtists */
/** @var array $topLanguages */

function href_with(array $params): string {
  return e(APP_BASE) . '/?' . http_build_query($params);
}
?>

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

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Latest songs</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/songs">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$latestSongs): ?>
          <div class="p-3 text-muted small">No songs yet.</div>
        <?php else: ?>
          <?php foreach ($latestSongs as $s): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
              <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
                <?php if (!empty($s['cover_url'])): ?>
                  <img src="<?= e((string)$s['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
                <?php else: ?>
                  <i class="bi bi-image" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 text-truncate">
                <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($s['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-trophy me-2" aria-hidden="true"></i>Top 100 (preview)</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/top">View top 100</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topSongs): ?>
          <div class="p-3 text-muted small">No plays yet.</div>
        <?php else: ?>
          <?php foreach ($topSongs as $i => $s): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
              <div class="d-flex align-items-center gap-3 text-truncate">
                <div class="badge text-bg-dark"><?= (int)($i + 1) ?></div>
                <div class="text-truncate">
                  <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                  <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
                </div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($s['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/artists">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topArtists): ?>
          <div class="p-3 text-muted small">No artists yet.</div>
        <?php else: ?>
          <?php foreach ($topArtists as $a): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3"
               href="<?= href_with(['r' => '/songs', 'artist' => (string)$a['artist']]) ?>">
              <div class="text-truncate">
                <div class="fw-semibold text-truncate"><?= e((string)$a['artist']) ?></div>
                <div class="text-muted small"><?= (int)($a['song_count'] ?? 0) ?> songs</div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($a['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-translate me-2" aria-hidden="true"></i>Languages</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/languages">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topLanguages): ?>
          <div class="p-3 text-muted small">No languages yet.</div>
        <?php else: ?>
          <?php foreach ($topLanguages as $l): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3"
               href="<?= href_with(['r' => '/songs', 'language' => (string)$l['language']]) ?>">
              <div class="text-truncate">
                <div class="fw-semibold text-truncate"><?= e((string)$l['language']) ?></div>
                <div class="text-muted small"><?= (int)($l['song_count'] ?? 0) ?> songs</div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($l['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
