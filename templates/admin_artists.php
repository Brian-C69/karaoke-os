<?php
/** @var array $artists */
/** @var string $sort */
/** @var SimplePager $pager */
$sort = strtolower(trim((string)($sort ?? 'latest')));
if (!in_array($sort, ['plays', 'songs', 'name', 'latest'], true)) {
  $sort = 'latest';
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Admin · Artists</h1>
  <div class="d-flex align-items-center gap-2">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/artists" class="d-inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="cache_external_images">
      <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-download me-1" aria-hidden="true"></i>Cache images</button>
    </form>
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/artists" class="d-inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="cleanup_unused_artist_images">
      <input type="hidden" name="dry_run" value="1">
      <button class="btn btn-outline-secondary btn-sm" type="submit" title="Preview cleanup (no delete)">
        <i class="bi bi-broom me-1" aria-hidden="true"></i>Preview cleanup
      </button>
    </form>
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/artists" class="d-inline" onsubmit="return confirm('Delete unused artist image files from assets/uploads/artists/?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="cleanup_unused_artist_images">
      <button class="btn btn-outline-danger btn-sm" type="submit" title="Delete unused local artist images">
        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Cleanup images
      </button>
    </form>
    <form method="get" action="<?= e(APP_BASE) ?>/" class="d-flex align-items-center gap-2">
      <input type="hidden" name="r" value="/admin/artists">
      <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()">
        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Recently updated</option>
        <option value="plays" <?= $sort === 'plays' ? 'selected' : '' ?>>Most plays</option>
        <option value="songs" <?= $sort === 'songs' ? 'selected' : '' ?>>Most songs</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>A–Z</option>
      </select>
    </form>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
  </div>
</div>

<form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/artists" data-bulk-root>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_artist_images">

  <div class="card shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <div class="fw-semibold">Bulk action</div>
        <select class="form-select form-select-sm" name="bulk_action" style="width: 230px;">
          <option value="">Choose…</option>
          <option value="fetch">Fetch image (missing only)</option>
          <option value="refresh">Force refresh image</option>
        </select>
        <button class="btn btn-sm btn-secondary" type="submit" data-bulk-apply disabled onclick="return confirm('Apply bulk artist image action? This may call external services.');">
          Apply (<span data-bulk-count>0</span>)
        </button>
      </div>
      <div class="text-muted small">Select is before row # (as requested).</div>
    </div>

  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th class="text-center" style="width:44px;">
            <input class="form-check-input" type="checkbox" data-bulk-all aria-label="Select all artists">
          </th>
          <th class="text-end" style="width:60px;">#</th>
          <th>Artist</th>
          <th class="text-end">Songs</th>
          <th class="text-end">Plays</th>
          <th class="text-end" style="width: 140px;"></th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($artists as $a): $n++; ?>
        <?php
          $img = trim((string)($a['image_url'] ?? ''));
          $imgIsExternal = $img !== '' && is_safe_external_url($img);
          if ($img !== '' && !$imgIsExternal) $img = e(APP_BASE) . '/' . ltrim($img, '/');
        ?>
        <tr>
          <td class="text-center">
            <input class="form-check-input" type="checkbox" name="artist_ids[]" value="<?= (int)$a['id'] ?>" data-bulk-item aria-label="Select artist">
          </td>
          <td class="text-end text-muted"><?= (int)$n ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:36px;height:36px;">
                <?php if ($img !== ''): ?>
                  <img src="<?= $img ?>" alt="" style="width:36px;height:36px;object-fit:cover;">
                <?php else: ?>
                  <i class="bi bi-person-circle" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="fw-semibold"><?= e((string)$a['name']) ?></div>
            </div>
          </td>
          <td class="text-end"><?= (int)($a['song_count'] ?? 0) ?></td>
          <td class="text-end"><?= (int)($a['play_count'] ?? 0) ?></td>
          <td class="text-end text-nowrap">
            <div class="d-inline-flex flex-nowrap gap-1">
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$a['id'] ?>"><i class="bi bi-eye me-1" aria-hidden="true"></i>View</a>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/artist-edit&id=<?= (int)$a['id'] ?>"><i class="bi bi-image me-1" aria-hidden="true"></i>Edit</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

<?php if ($pager->pages > 1): ?>
  <div class="d-flex justify-content-center mt-4">
    <nav aria-label="Artists pages">
      <ul class="pagination mb-0">
        <li class="page-item <?= $pager->hasPrev() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/admin/artists&page=<?= (int)$pager->prevPage() ?>&sort=<?= urlencode($sort) ?>">Prev</a>
        </li>
        <?php foreach ($pager->window(2) as $p): ?>
          <li class="page-item <?= $p === $pager->page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/admin/artists&page=<?= (int)$p ?>&sort=<?= urlencode($sort) ?>"><?= (int)$p ?></a>
          </li>
        <?php endforeach; ?>
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= e(APP_BASE) ?>/?r=/admin/artists&page=<?= (int)$pager->nextPage() ?>&sort=<?= urlencode($sort) ?>">Next</a>
        </li>
      </ul>
    </nav>
  </div>
<?php endif; ?>

<script src="<?= e(APP_BASE) ?>/assets/js/admin-bulk.js"></script>
