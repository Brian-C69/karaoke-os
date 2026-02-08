<?php
/** @var array $topSongs */
/** @var array $topArtists */
/** @var array $playsByDay */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Admin · Analytics</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin">Admin home</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Top Songs</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Song</th><th class="text-end">Plays</th></tr></thead>
            <tbody>
              <?php foreach ($topSongs as $s): ?>
                <tr>
                  <td><a href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>"><?= e((string)$s['title']) ?></a><div class="text-muted small"><?= e((string)$s['artist']) ?></div></td>
                  <td class="text-end"><?= (int)$s['play_count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Top Artists</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Artist</th><th class="text-end">Plays</th><th class="text-end">Songs</th></tr></thead>
            <tbody>
              <?php foreach ($topArtists as $a): ?>
                <tr>
                  <td><?= e((string)$a['artist']) ?></td>
                  <td class="text-end"><?= (int)$a['play_count'] ?></td>
                  <td class="text-end"><?= (int)$a['song_count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Plays (Last 14 days)</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Day</th><th class="text-end">Plays</th></tr></thead>
            <tbody>
              <?php foreach ($playsByDay as $d): ?>
                <tr>
                  <td><?= e((string)$d['day']) ?></td>
                  <td class="text-end"><?= (int)$d['play_count'] ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$playsByDay): ?>
                <tr><td colspan="2" class="text-muted">No plays yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
