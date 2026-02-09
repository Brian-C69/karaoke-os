<?php
/** @var array $artists */
/** @var string $sort */
/** @var SimplePager $pager */
$sort = strtolower(trim((string)($sort ?? 'plays')));
if (!in_array($sort, ['plays', 'songs', 'name', 'latest'], true)) {
  $sort = 'plays';
}
?>
<div class="mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</h1>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(APP_BASE) ?>/" class="row g-2 align-items-end">
      <input type="hidden" name="r" value="/artists">
      <div class="col-12 col-lg-4">
        <label class="form-label">Sort</label>
        <select class="form-select" name="sort">
          <option value="plays" <?= $sort === 'plays' ? 'selected' : '' ?>>Most plays</option>
          <option value="songs" <?= $sort === 'songs' ? 'selected' : '' ?>>Most songs</option>
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Recently updated</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>A–Z</option>
        </select>
      </div>
      <div class="col-12 col-lg-4 d-flex gap-2">
        <button class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1" aria-hidden="true"></i>Go</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Songs</a>
      </div>
    </form>
  </div>
</div>

<?php if (!$artists): ?>
  <div class="alert alert-info">No artists yet.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($artists as $a): ?>
      <?php
        $img = trim((string)($a['image_url'] ?? ''));
        $imgIsExternal = $img !== '' && is_safe_external_url($img);
        if ($img !== '' && !$imgIsExternal) $img = e(APP_BASE) . '/' . ltrim($img, '/');
      ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$a['id'] ?>">
          <div class="ratio ratio-1x1 bg-dark overflow-hidden">
            <?php if ($img !== ''): ?>
              <img src="<?= $img ?>" alt="" style="object-fit:cover; width:100%; height:100%;">
            <?php else: ?>
              <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50">
                <i class="bi bi-person-circle fs-1" aria-hidden="true"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="fw-semibold text-dark text-truncate"><?= e((string)$a['name']) ?></div>
            <div class="text-muted small"><?= (int)($a['song_count'] ?? 0) ?> songs · <?= (int)($a['play_count'] ?? 0) ?> plays</div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pager->pages > 1): ?>
    <div class="d-flex justify-content-center mt-4">
      <nav aria-label="Artists pages">
        <ul class="pagination mb-0">
          <li class="page-item <?= $pager->hasPrev() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/artists&page=<?= (int)$pager->prevPage() ?>&sort=<?= urlencode($sort) ?>">Prev</a>
          </li>
          <?php foreach ($pager->window(2) as $p): ?>
            <li class="page-item <?= $p === $pager->page ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/artists&page=<?= (int)$p ?>&sort=<?= urlencode($sort) ?>"><?= (int)$p ?></a>
            </li>
          <?php endforeach; ?>
          <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/artists&page=<?= (int)$pager->nextPage() ?>&sort=<?= urlencode($sort) ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
<?php endif; ?>
