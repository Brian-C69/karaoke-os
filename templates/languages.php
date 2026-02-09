<?php /** @var array $languages */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-translate me-2" aria-hidden="true"></i>Languages</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs">Browse songs</a>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th>Language</th>
          <th class="text-end">Songs</th>
          <th class="text-end">Plays</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($languages as $l): ?>
        <?php $flag = language_flag_url((string)($l['language'] ?? '')); ?>
        <tr>
          <td>
            <a href="<?= e(APP_BASE) ?>/?r=/songs&language=<?= urlencode((string)$l['language']) ?>" class="text-decoration-none d-inline-flex align-items-center gap-2">
              <?php if ($flag): ?>
                <img class="lang-flag lang-flag-sm" src="<?= e($flag) ?>" alt="<?= e((string)$l['language']) ?>" title="<?= e((string)$l['language']) ?>">
              <?php endif; ?>
              <span><?= e((string)$l['language']) ?></span>
            </a>
          </td>
          <td class="text-end"><?= (int)$l['song_count'] ?></td>
          <td class="text-end"><?= (int)$l['play_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
