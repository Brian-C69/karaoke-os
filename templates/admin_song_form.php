<?php
/** @var array|null $song */
/** @var string|null $return_view */
$returnView = strtolower(trim((string)($return_view ?? '')));
if (!in_array($returnView, ['active', 'disabled', 'all'], true)) {
  $returnView = '';
}
$backParams = ['r' => '/admin/songs'];
if ($returnView !== '') $backParams['view'] = $returnView;
$backHref = e(APP_BASE) . '/?' . http_build_query($backParams);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i><?= $song ? 'Admin · Edit Song' : 'Admin · Add Song' ?></h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $backHref ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=<?= $song ? '/admin/song-edit&id=' . (int)$song['id'] : '/admin/song-new' ?>" data-song-form="1">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title *</label>
          <div class="position-relative">
            <input class="form-control" name="title" required value="<?= e((string)($song['title'] ?? '')) ?>" autocomplete="off" placeholder="Start typing…">
            <div class="list-group position-absolute w-100 shadow-sm d-none" id="titleSuggest" style="z-index: 5;"></div>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Artist *</label>
          <div class="position-relative">
            <input class="form-control" name="artist" required value="<?= e((string)($song['artist'] ?? '')) ?>" autocomplete="off" placeholder="Type once, then pick from suggestions">
            <div class="list-group position-absolute w-100 shadow-sm d-none" id="artistSuggest" style="z-index: 5;"></div>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Google Drive File URL/ID *</label>
          <input class="form-control" name="drive" required value="<?= e((string)($song['drive_file_id'] ?? ($song['drive_url'] ?? ''))) ?>" placeholder="Paste Drive link here">
          <div class="text-muted small mt-1">Play opens the saved Drive link in a new tab (login required).</div>
        </div>
        <div class="col-12">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-semibold">Auto metadata</div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge text-bg-secondary" id="metaBadge">Meta: idle</span>
              <?php if ($song): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="pickCoverBtn"><i class="bi bi-images me-1" aria-hidden="true"></i>Pick cover</button>
              <?php endif; ?>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-3">
              <div class="ratio ratio-1x1 rounded bg-dark overflow-hidden">
                <div id="coverPlaceholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50">
                  <i class="bi bi-image fs-1" aria-hidden="true"></i>
                </div>
                <img id="coverPreview" src="<?= e((string)($song['cover_url'] ?? '')) ?>" alt="" style="object-fit:cover; display:none;">
              </div>
            </div>
            <div class="col-md-9">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Language (auto)</label>
                  <input class="form-control" name="language" value="<?= e((string)($song['language'] ?? '')) ?>" placeholder="EN / ZH / JA / KO">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Album (auto)</label>
                  <input class="form-control" name="album" value="<?= e((string)($song['album'] ?? '')) ?>" placeholder="Auto-filled when possible">
                </div>
                <?php if ($song): ?>
                  <div class="col-12">
                    <label class="form-label">Cover URL (auto)</label>
                    <input class="form-control" name="cover_url" value="<?= e((string)($song['cover_url'] ?? '')) ?>" placeholder="Auto-filled when possible">
                  </div>
                <?php else: ?>
                  <input type="hidden" name="cover_url" value="<?= e((string)($song['cover_url'] ?? '')) ?>">
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="active" <?= ((int)($song['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Active (visible in library)</label>
          </div>
        </div>

        <div class="col-12 d-none" id="dupBox">
          <div class="alert alert-warning mb-0">
            <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Duplicate detected</div>
            <ul class="mb-0" id="dupList"></ul>
            <div class="text-muted small mt-2">Checked by Title + Artist (case-insensitive) and Drive link. Submit is disabled until you change the fields.</div>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>Save</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/admin/songs"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Cancel</a>
      </div>
    </form>
  </div>
</div>

<script src="<?= e(APP_BASE) ?>/assets/js/admin-song-form.js"></script>

<div class="modal fade" id="coverPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-images me-2" aria-hidden="true"></i>Select cover</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3">Pick the closest match. If not found, leave it blank and edit later.</div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="text-muted small" id="coverPickerStatus">Idle</div>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshCoverBtn"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Refresh</button>
        </div>
        <div class="list-group" id="coverCandidates"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
