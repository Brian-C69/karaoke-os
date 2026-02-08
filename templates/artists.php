<?php /** @var array $artists */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs">Browse songs</a>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th>Artist</th>
          <th class="text-end">Songs</th>
          <th class="text-end">Plays</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($artists as $a): ?>
        <tr>
          <td><a href="<?= e(APP_BASE) ?>/?r=/songs&artist=<?= urlencode((string)$a['artist']) ?>"><?= e((string)$a['artist']) ?></a></td>
          <td class="text-end"><?= (int)$a['song_count'] ?></td>
          <td class="text-end"><?= (int)$a['play_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
