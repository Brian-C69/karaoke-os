<?php /** @var array $rows */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-trophy me-2" aria-hidden="true"></i>Top 100</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs&sort=plays">All songs (most played)</a>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th style="width:80px;">#</th>
          <th>Song</th>
          <th class="text-end">Plays</th>
        </tr>
      </thead>
      <tbody>
      <?php $i = 1; foreach ($rows as $r): ?>
        <tr>
          <td class="text-muted"><?= $i++ ?></td>
          <td>
            <a href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$r['id'] ?>" class="text-decoration-none">
              <div class="fw-semibold"><?= e((string)$r['title']) ?></div>
              <div class="text-muted small"><?= e((string)$r['artist']) ?></div>
            </a>
          </td>
          <td class="text-end"><?= (int)$r['play_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
