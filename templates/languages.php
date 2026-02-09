<?php /** @var array $languages */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-translate me-2" aria-hidden="true"></i>Languages</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Browse songs</a>
</div>

<?php if (!$languages): ?>
  <div class="alert alert-info">No languages yet.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($languages as $l): ?>
      <?php
        $lang = (string)($l['language'] ?? '');
        $flag = language_flag_url($lang);
      ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/songs&language=<?= urlencode($lang) ?>">
          <div class="ratio ratio-1x1 bg-dark overflow-hidden d-flex align-items-center justify-content-center">
            <?php if ($flag): ?>
              <img class="lang-flag-card" src="<?= e($flag) ?>" alt="<?= e($lang) ?>" title="<?= e($lang) ?>">
            <?php else: ?>
              <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50">
                <i class="bi bi-question-circle fs-1" aria-hidden="true"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="fw-semibold text-dark text-truncate"><?= e($lang) ?></div>
            <div class="text-muted small"><?= (int)($l['song_count'] ?? 0) ?> songs · <?= (int)($l['play_count'] ?? 0) ?> plays</div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
