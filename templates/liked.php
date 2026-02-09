<?php
/** @var array $rows */
/** @var string|null $view */
$view = strtolower(trim((string)($view ?? ($_GET['view'] ?? 'tile'))));
if (!in_array($view, ['tile', 'list'], true)) {
  $view = 'tile';
}
$baseParams = ['r' => '/liked'];
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-heart-fill me-2 text-danger" aria-hidden="true"></i>Most liked</h1>
  <div class="d-flex align-items-center gap-2">
    <div class="btn-group" role="group" aria-label="View mode">
      <a class="btn btn-outline-secondary btn-sm <?= $view === 'tile' ? 'active' : '' ?>"
         href="<?= e(APP_BASE) ?>/?<?= http_build_query($baseParams + ['view' => 'tile']) ?>"
         aria-pressed="<?= $view === 'tile' ? 'true' : 'false' ?>"
         title="Tile view" aria-label="Tile view">
        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
      </a>
      <a class="btn btn-outline-secondary btn-sm <?= $view === 'list' ? 'active' : '' ?>"
         href="<?= e(APP_BASE) ?>/?<?= http_build_query($baseParams + ['view' => 'list']) ?>"
         aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>"
         title="List view" aria-label="List view">
        <i class="bi bi-list-ul" aria-hidden="true"></i>
      </a>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>All songs</a>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="alert alert-info">No likes yet.</div>
<?php else: ?>
  <?php if ($view === 'list'): ?>
    <div class="list-group shadow-sm">
      <?php $rank = 1; foreach ($rows as $r): ?>
        <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$r['id'] ?>">
          <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
            <?php if (!empty($r['cover_url'])): ?>
              <img src="<?= e((string)$r['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
            <?php else: ?>
              <i class="bi bi-image" aria-hidden="true"></i>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1 text-truncate">
            <div class="fw-semibold text-truncate">
              <span class="text-muted me-2">#<?= $rank ?></span><?= e((string)$r['title']) ?>
            </div>
            <div class="text-muted small text-truncate"><?= e((string)$r['artist']) ?></div>
          </div>
          <div class="text-muted small text-nowrap">
            <i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i><?= (int)($r['like_count'] ?? 0)
            ?>
          </div>
        </a>
      <?php $rank++; endforeach; ?>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php $i = 1; foreach ($rows as $r): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a class="card song-card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$r['id'] ?>">
            <div class="cover position-relative">
              <?php if (!empty($r['cover_url'])): ?>
                <img src="<?= e((string)$r['cover_url']) ?>" alt="">
              <?php else: ?>
                <div class="placeholder"><i class="bi bi-image me-1" aria-hidden="true"></i>No cover</div>
              <?php endif; ?>
              <div class="position-absolute top-0 start-0 m-2">
                <span class="badge text-bg-dark">#<?= $i ?></span>
              </div>
            </div>
            <div class="card-body">
              <div class="fw-semibold text-dark text-truncate">
                <span class="text-muted me-2">#<?= $i ?></span><?= e((string)$r['title']) ?>
              </div>
              <div class="text-muted small text-truncate"><?= e((string)$r['artist']) ?></div>
              <div class="text-muted small">
                <i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i><?= (int)($r['like_count'] ?? 0) ?> likes
              </div>
            </div>
          </a>
        </div>
      <?php $i++; endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

