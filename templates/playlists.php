<?php
/** @var array $playlists */
$playlists = is_array($playlists ?? null) ? $playlists : [];
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-collection-play me-2" aria-hidden="true"></i>My playlists</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Browse songs</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/playlists" class="d-flex gap-2 flex-wrap">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="flex-grow-1" style="min-width: 240px;">
        <input class="form-control" name="name" placeholder="New playlist name" autocomplete="off">
      </div>
      <button class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create</button>
    </form>
    <div class="text-muted small mt-2">Tip: On a song page, use “Add to playlist” for a quicker flow.</div>
  </div>
</div>

<?php if (!$playlists): ?>
  <div class="alert alert-info">No playlists yet.</div>
<?php else: ?>
  <div class="list-group shadow-sm">
    <?php foreach ($playlists as $p): ?>
      <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3"
         href="<?= e(APP_BASE) ?>/?r=/playlist&id=<?= (int)($p['id'] ?? 0) ?>">
        <div class="text-truncate">
          <div class="fw-semibold text-truncate"><?= e((string)($p['name'] ?? '')) ?></div>
          <div class="text-muted small"><?= (int)($p['song_count'] ?? 0) ?> songs</div>
        </div>
        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

