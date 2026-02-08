<?php
/** @var array $song */
/** @var int $playCount */
/** @var array|null $user */
?>
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
        <h1 class="h4 mb-1"><i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i><?= e((string)$song['title']) ?></h1>
        <div class="text-muted mb-3"><?= e((string)$song['artist']) ?></div>
        <div class="row g-2 small mb-3">
          <div class="col-6"><span class="text-muted">Language:</span> <?= e((string)($song['language'] ?? '')) ?: '—' ?></div>
          <div class="col-6"><span class="text-muted">Album:</span> <?= e((string)($song['album'] ?? '')) ?: '—' ?></div>
          <div class="col-6"><span class="text-muted">Plays:</span> <?= (int)$playCount ?></div>
        </div>

        <?php if (!$user): ?>
          <div class="alert alert-warning mb-0">
            Login required to access the MP4. You can still browse the library.
            <a href="<?= e(APP_BASE) ?>/?r=/login" class="alert-link">Login</a>
          </div>
        <?php else: ?>
          <form method="post" action="<?= e(APP_BASE) ?>/?r=/play" class="d-inline" target="_blank">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$song['id'] ?>">
            <button class="btn btn-primary"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>Play</button>
          </form>
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
