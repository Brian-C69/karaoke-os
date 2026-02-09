<?php /** @var array $artist */ ?>
<?php
  $img = trim((string)($artist['image_url'] ?? ''));
  $imgIsExternal = $img !== '' && is_safe_external_url($img);
  if ($img !== '' && !$imgIsExternal) $img = e(APP_BASE) . '/' . ltrim($img, '/');
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-image me-2" aria-hidden="true"></i>Admin · Artist</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/artists"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
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
        <div class="fw-semibold"><?= e((string)$artist['name']) ?></div>
        <div class="text-muted small"><?= (int)($artist['song_count'] ?? 0) ?> songs · <?= (int)($artist['play_count'] ?? 0) ?> plays</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/artist-edit&id=<?= (int)$artist['id'] ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

          <div class="mb-3">
            <label class="form-label">Artist name</label>
            <input class="form-control" value="<?= e((string)$artist['name']) ?>" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label">Image URL</label>
            <input class="form-control" name="image_url" value="<?= e((string)($artist['image_url'] ?? '')) ?>" placeholder="https://...">
            <div class="text-muted small mt-1">Leave blank if you upload an image instead.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Upload image</label>
            <input class="form-control" type="file" name="image_file" accept="image/*">
            <div class="text-muted small mt-1">Max 3MB. Stored locally in `assets/uploads/artists/`.</div>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remove_image" id="rm">
            <label class="form-check-label" for="rm">Remove image</label>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>Save</button>
            <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$artist['id'] ?>"><i class="bi bi-eye me-1" aria-hidden="true"></i>View artist</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

