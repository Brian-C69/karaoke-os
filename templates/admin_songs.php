<?php
/** @var array $songs */
/** @var string $view */
$view = strtolower(trim((string)($view ?? 'active')));
if (!in_array($view, ['active', 'disabled', 'all'], true)) {
  $view = 'active';
}
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i>Admin · Songs</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/song-new"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add song</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin">Admin home</a>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $view === 'active' ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/admin/songs&view=active"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Active</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $view === 'disabled' ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/admin/songs&view=disabled"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Disabled</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $view === 'all' ? 'active' : '' ?>" href="<?= e(APP_BASE) ?>/?r=/admin/songs&view=all"><i class="bi bi-collection me-1" aria-hidden="true"></i>All</a>
  </li>
</ul>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th>Title</th>
          <th>Artist</th>
          <th>Language</th>
          <th class="text-center">Active</th>
          <th style="width: 160px;"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($songs as $s): ?>
        <tr>
          <td class="fw-semibold"><?= e((string)$s['title']) ?></td>
          <td><?= e((string)$s['artist']) ?></td>
          <td><?= e((string)($s['language'] ?? '')) ?></td>
          <td class="text-center">
            <?php if ((int)$s['is_active'] === 1): ?>
              <i class="bi bi-check-circle-fill text-success" title="Active" aria-label="Active"></i>
            <?php else: ?>
              <i class="bi bi-slash-circle text-muted" title="Disabled" aria-label="Disabled"></i>
            <?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <div class="d-inline-flex flex-nowrap gap-1">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/song-edit&id=<?= (int)$s['id'] ?>&return_view=<?= e($view) ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
              <?php if ((int)$s['is_active'] === 1): ?>
                <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/song-delete" class="m-0" onsubmit="return confirm('Disable this song?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Disable</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/song-enable" class="m-0">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-sm btn-outline-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Enable</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
