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

<div class="card shadow-sm mb-3">
  <div class="card-header bg-body d-flex align-items-center justify-content-between">
    <div class="fw-semibold"><i class="bi bi-upload me-1" aria-hidden="true"></i>Bulk insert (paste)</div>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#bulkInsertSongs" aria-expanded="false" aria-controls="bulkInsertSongs">
      <i class="bi bi-chevron-down me-1" aria-hidden="true"></i>Expand
    </button>
  </div>
  <div id="bulkInsertSongs" class="collapse">
    <div class="card-body">
      <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/songs&view=<?= e($view) ?>" class="mb-0">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="bulk_insert">
        <textarea class="form-control font-monospace" name="bulk_lines" rows="6" placeholder="Title | Artist | Drive URL/ID | Language (optional)&#10;Bohemian Rhapsody | Queen | https://drive.google.com/file/d/.../view | EN"></textarea>
        <div class="text-muted small mt-1">Separator: <code>|</code> or <code>TAB</code>. Lines starting with <code>#</code> are ignored. Duplicates are skipped.</div>
        <div class="mt-2 d-flex gap-2">
          <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Insert lines</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/songs&view=<?= e($view) ?>" data-bulk-root>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_update">

  <div class="card shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <div class="fw-semibold">Bulk action</div>
        <select class="form-select form-select-sm" name="bulk_action" style="width: 180px;">
          <option value="">Choose…</option>
          <option value="enable">Enable selected</option>
          <option value="disable">Disable selected</option>
        </select>
        <button class="btn btn-sm btn-primary" type="submit" data-bulk-apply disabled onclick="return confirm('Apply bulk action to selected songs?');">
          Apply (<span data-bulk-count>0</span>)
        </button>
      </div>
      <div class="text-muted small">Select is before row # (as requested).</div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th class="text-center" style="width: 44px;">
              <input class="form-check-input" type="checkbox" data-bulk-all aria-label="Select all">
            </th>
            <th class="text-end" style="width: 60px;">#</th>
            <th>Title</th>
            <th>Artist</th>
            <th>Language</th>
            <th class="text-center">Active</th>
            <th style="width: 110px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($songs as $s): $n++; ?>
          <tr>
            <td class="text-center">
              <input class="form-check-input" type="checkbox" name="song_ids[]" value="<?= (int)$s['id'] ?>" data-bulk-item aria-label="Select song">
            </td>
            <td class="text-end text-muted"><?= (int)$n ?></td>
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
              <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/song-edit&id=<?= (int)$s['id'] ?>&return_view=<?= e($view) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<script src="<?= e(APP_BASE) ?>/assets/js/admin-bulk.js"></script>
