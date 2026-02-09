<?php
/** @var array $song */
/** @var int $playCount */
/** @var array|null $artistRow */
/** @var array|null $user */
/** @var bool|null $favorited */
$favorited = !empty($favorited);

$artistImg = '';
if (!empty($artistRow) && is_array($artistRow)) {
  $artistImg = trim((string)($artistRow['image_url'] ?? ''));
  $artistImgIsExternal = $artistImg !== '' && is_safe_external_url($artistImg);
  if ($artistImg !== '' && !$artistImgIsExternal) {
    $artistImg = e(APP_BASE) . '/' . ltrim($artistImg, '/');
  }
}
?>
<div class="d-flex align-items-center justify-content-end mb-3">
  <a class="btn btn-outline-secondary btn-sm"
     href="<?= e(APP_BASE) ?>/?r=/songs"
     onclick="if (history.length > 1) { event.preventDefault(); history.back(); }">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back
  </a>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="cover-lg">
        <?php if (!empty($song['cover_url'])): ?>
          <img src="<?= e((string)$song['cover_url']) ?>" alt="">
        <?php else: ?>
          <div class="placeholder">No cover</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <h1 class="h4 m-0 text-truncate">
            <i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i><?= e((string)$song['title']) ?>
          </h1>
          <?php if (!empty($user)): ?>
            <button type="button"
                    class="btn btn-link p-1 fav-btn fav-btn-lg song-action js-fav-toggle <?= $favorited ? 'text-danger' : 'text-muted' ?>"
                    data-song-id="<?= (int)$song['id'] ?>"
                    data-favorited="<?= $favorited ? '1' : '0' ?>"
                    aria-label="Favorite"
                    aria-pressed="<?= $favorited ? 'true' : 'false' ?>"
                    title="<?= $favorited ? 'Remove from favorites' : 'Add to favorites' ?>">
              <i class="bi <?= $favorited ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
            </button>
          <?php endif; ?>
        </div>
        <div class="text-muted mb-3">
          <?php if (!empty($artistRow['id'])): ?>
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:36px;height:36px;">
                <?php if ($artistImg !== ''): ?>
                  <img src="<?= $artistImg ?>" alt="" style="width:36px;height:36px;object-fit:cover;">
                <?php else: ?>
                  <i class="bi bi-person-circle fs-3" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <a class="link-secondary text-decoration-none"
                 href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$artistRow['id'] ?>">
                <span class="fs-5 fw-semibold"><?= e((string)$song['artist']) ?></span>
              </a>
            </div>
          <?php elseif (!empty($song['artist'])): ?>
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:36px;height:36px;">
                <i class="bi bi-person-circle fs-3" aria-hidden="true"></i>
              </div>
              <a class="link-secondary text-decoration-none"
                 href="<?= e(APP_BASE) ?>/?r=/songs&artist=<?= urlencode((string)$song['artist']) ?>">
                <span class="fs-5 fw-semibold"><?= e((string)$song['artist']) ?></span>
              </a>
            </div>
          <?php else: ?>
            —
          <?php endif; ?>
        </div>
        <div class="row g-2 small mb-3">
          <div class="col-6">
            <span class="text-muted">Language:</span>
            <?php $flag = language_flag_url((string)($song['language'] ?? '')); ?>
            <?php if ($flag): ?>
              <img class="lang-flag lang-flag-xs lang-flag-circle" src="<?= e($flag) ?>" alt="<?= e((string)($song['language'] ?? '')) ?>" title="<?= e((string)($song['language'] ?? '')) ?>">
            <?php else: ?>
              <?= e((string)($song['language'] ?? '')) ?: '—' ?>
            <?php endif; ?>
          </div>
          <div class="col-6"><span class="text-muted">Album:</span> <?= e((string)($song['album'] ?? '')) ?: '—' ?></div>
          <div class="col-6"><span class="text-muted">Plays:</span> <?= (int)$playCount ?></div>
        </div>

        <?php if (!$user): ?>
          <div class="alert alert-warning mb-0">
            Login required to access the MP4. You can still browse the library.
            <a href="<?= e(APP_BASE) ?>/?r=/login" class="alert-link">Login</a>
          </div>
        <?php else: ?>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="post" action="<?= e(APP_BASE) ?>/?r=/play" class="d-inline" target="_blank">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$song['id'] ?>">
            <button class="btn btn-primary"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>Play</button>
            </form>
            <button type="button" class="btn btn-outline-secondary" id="addToPlaylistBtn" data-song-id="<?= (int)$song['id'] ?>">
              <i class="bi bi-collection-play me-1" aria-hidden="true"></i>Add to playlist
            </button>
          </div>
          <?php if (empty($song['drive_url']) && empty($song['drive_file_id'])): ?>
            <div class="text-muted small mt-2">Admin hasn’t added the link yet.</div>
          <?php else: ?>
            <div class="text-muted small mt-2">Opens in a new tab.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($user)): ?>
  <div class="modal fade" id="playlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-collection-play me-2" aria-hidden="true"></i>Add to playlist</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
          <div class="modal-body">
          <div class="mb-2">
            <div class="list-group" data-playlist-list></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Choose playlist</label>
            <select class="form-select" data-playlist-select></select>
          </div>
          <div class="border-top pt-3">
            <label class="form-label">Or create a new playlist</label>
            <div class="d-flex gap-2">
              <input class="form-control playlist-create-name" placeholder="Playlist name" data-playlist-create-name>
              <button type="button" class="btn btn-outline-primary flex-shrink-0" data-playlist-create-btn>
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create & add
              </button>
            </div>
          </div>
          <div class="mt-2" data-playlist-msg></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" data-playlist-add-btn>
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Add
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
