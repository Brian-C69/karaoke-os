<?php
/** @var array $latestSongs */
/** @var array $topSongs */
/** @var array $topLikedSongs */
/** @var array $topArtists */
/** @var array $topLanguages */

function href_with(array $params): string {
  return e(APP_BASE) . '/?' . http_build_query($params);
}
?>

<div class="row g-3">
  <div class="col-12">
    <div class="p-4 bg-body rounded shadow-sm">
      <h1 class="h4 mb-2"><i class="bi bi-compass me-2" aria-hidden="true"></i>Browse & Rankings</h1>
      <div class="text-muted">Public can browse. Logged-in users can play, and every play is tracked.</div>

      <div class="home-tiles mt-3">
        <a class="home-tile tile-songs" href="<?= e(APP_BASE) ?>/?r=/songs">
          <div class="home-tile-icon"><i class="bi bi-music-note-list" aria-hidden="true"></i></div>
          <div class="home-tile-label">Songs</div>
        </a>
        <a class="home-tile tile-artists" href="<?= e(APP_BASE) ?>/?r=/artists">
          <div class="home-tile-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></div>
          <div class="home-tile-label">Artists</div>
        </a>
        <a class="home-tile tile-languages" href="<?= e(APP_BASE) ?>/?r=/languages">
          <div class="home-tile-icon"><i class="bi bi-translate" aria-hidden="true"></i></div>
          <div class="home-tile-label">Languages</div>
        </a>
        <a class="home-tile tile-top" href="<?= e(APP_BASE) ?>/?r=/top">
          <div class="home-tile-icon"><i class="bi bi-trophy" aria-hidden="true"></i></div>
          <div class="home-tile-label">Top 100</div>
        </a>
        <a class="home-tile tile-liked" href="<?= e(APP_BASE) ?>/?r=/liked">
          <div class="home-tile-icon"><i class="bi bi-heart-fill" aria-hidden="true"></i></div>
          <div class="home-tile-label">Most liked</div>
        </a>

        <?php if (!empty($user)): ?>
          <a class="home-tile tile-recent" href="<?= e(APP_BASE) ?>/?r=/recent">
            <div class="home-tile-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
            <div class="home-tile-label">Recent</div>
          </a>
          <a class="home-tile tile-favorites" href="<?= e(APP_BASE) ?>/?r=/favorites">
            <div class="home-tile-icon"><i class="bi bi-heart" aria-hidden="true"></i></div>
            <div class="home-tile-label">Favorites</div>
          </a>
          <a class="home-tile tile-playlists" href="<?= e(APP_BASE) ?>/?r=/playlists">
            <div class="home-tile-icon"><i class="bi bi-collection-play" aria-hidden="true"></i></div>
            <div class="home-tile-label">Playlists</div>
          </a>
          <a class="home-tile tile-account" href="<?= e(APP_BASE) ?>/?r=/account">
            <div class="home-tile-icon"><i class="bi bi-person-circle" aria-hidden="true"></i></div>
            <div class="home-tile-label">Account</div>
          </a>
          <?php if (($user['role'] ?? '') === 'admin'): ?>
            <a class="home-tile tile-admin" href="<?= e(APP_BASE) ?>/?r=/admin">
              <div class="home-tile-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>
              <div class="home-tile-label">Admin</div>
            </a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Latest songs</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/songs">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$latestSongs): ?>
          <div class="p-3 text-muted small">No songs yet.</div>
        <?php else: ?>
          <?php foreach ($latestSongs as $s): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
              <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
                <?php if (!empty($s['cover_url'])): ?>
                  <img src="<?= e((string)$s['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
                <?php else: ?>
                  <i class="bi bi-image" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 text-truncate">
                <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($s['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-trophy me-2" aria-hidden="true"></i>Top 100 (preview)</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/top">View top 100</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topSongs): ?>
          <div class="p-3 text-muted small">No plays yet.</div>
        <?php else: ?>
          <?php foreach ($topSongs as $i => $s): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
              <div class="d-flex align-items-center gap-3 text-truncate">
                <div class="badge text-bg-dark"><?= (int)($i + 1) ?></div>
                <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
                  <?php if (!empty($s['cover_url'])): ?>
                    <img src="<?= e((string)$s['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
                  <?php else: ?>
                    <i class="bi bi-image" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>
                <div class="text-truncate">
                  <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                  <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
                </div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($s['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-heart-fill me-2 text-danger" aria-hidden="true"></i>Most liked</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/liked">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topLikedSongs): ?>
          <div class="p-3 text-muted small">No likes yet.</div>
        <?php else: ?>
          <?php foreach ($topLikedSongs as $i => $s): ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
              <div class="d-flex align-items-center gap-3 text-truncate">
                <div class="badge text-bg-dark"><?= (int)($i + 1) ?></div>
                <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
                  <?php if (!empty($s['cover_url'])): ?>
                    <img src="<?= e((string)$s['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
                  <?php else: ?>
                    <i class="bi bi-image" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>
                <div class="text-truncate">
                  <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                  <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
                </div>
              </div>
              <div class="text-muted small text-nowrap"><i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i><?= (int)($s['like_count'] ?? 0) ?></div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Top artists</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/artists">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topArtists): ?>
          <div class="p-3 text-muted small">No artists yet.</div>
        <?php else: ?>
          <?php foreach ($topArtists as $a): ?>
            <?php
              $aImg = trim((string)($a['image_url'] ?? ''));
              $aImgIsExternal = $aImg !== '' && is_safe_external_url($aImg);
              if ($aImg !== '' && !$aImgIsExternal) $aImg = e(APP_BASE) . '/' . ltrim($aImg, '/');
            ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3"
               href="<?= href_with(['r' => '/artist', 'id' => (int)($a['id'] ?? 0)]) ?>">
              <div class="d-flex align-items-center gap-3 text-truncate">
                <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
                  <?php if ($aImg !== ''): ?>
                    <img src="<?= $aImg ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
                  <?php else: ?>
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>
                <div class="text-truncate">
                  <div class="fw-semibold text-truncate"><?= e((string)$a['name']) ?></div>
                  <div class="text-muted small text-truncate"><?= (int)($a['song_count'] ?? 0) ?> songs · <?= (int)($a['play_count'] ?? 0) ?> plays</div>
                </div>
              </div>
              <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-translate me-2" aria-hidden="true"></i>Languages</div>
        <a class="small text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/languages">View all</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$topLanguages): ?>
          <div class="p-3 text-muted small">No languages yet.</div>
        <?php else: ?>
          <?php foreach ($topLanguages as $l): ?>
            <?php $flag = language_flag_url((string)($l['language'] ?? '')); ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-3"
               href="<?= href_with(['r' => '/songs', 'language' => (string)$l['language']]) ?>">
              <div class="text-truncate">
                <div class="fw-semibold text-truncate d-flex align-items-center gap-2">
                  <?php if ($flag): ?>
                    <img class="lang-flag lang-flag-xs lang-flag-circle" src="<?= e($flag) ?>" alt="<?= e((string)$l['language']) ?>" title="<?= e((string)$l['language']) ?>">
                  <?php endif; ?>
                  <span><?= e((string)$l['language']) ?></span>
                </div>
                <div class="text-muted small"><?= (int)($l['song_count'] ?? 0) ?> songs</div>
              </div>
              <div class="text-muted small text-nowrap"><?= (int)($l['play_count'] ?? 0) ?> plays</div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
