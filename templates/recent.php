<?php
/** @var array $filters */
/** @var array $songs */
/** @var SimplePager $pager */
/** @var string|null $view */
/** @var array $favoriteIds */
/** @var string $mode */

$pager = $pager ?? new SimplePager(count($songs), 1, 20);
$view = strtolower(trim((string)($view ?? ($_GET['view'] ?? 'tile'))));
if (!in_array($view, ['tile', 'list'], true)) {
  $view = 'tile';
}
$favoriteIds = is_array($favoriteIds ?? null) ? $favoriteIds : [];
$mode = strtolower(trim((string)($mode ?? ($filters['mode'] ?? 'unique'))));
if (!in_array($mode, ['unique', 'history'], true)) {
  $mode = 'unique';
}
$sort = strtolower(trim((string)($filters['sort'] ?? 'recent')));
if (!in_array($sort, ['recent', 'plays', 'title'], true)) {
  $sort = 'recent';
}

$params = [
  'r' => '/recent',
  'q' => (string)($filters['q'] ?? ''),
  'sort' => $sort,
  'mode' => $mode,
  'per_page' => (int)$pager->perPage,
];
if ($view !== 'tile') $params['view'] = $view;

function recent_page_href(array $params, int $page): string {
  $params['page'] = $page;
  return e(APP_BASE) . '/?' . http_build_query($params);
}

function played_at_text(array $s, string $mode): string {
  $raw = $mode === 'history' ? (string)($s['played_at'] ?? '') : (string)($s['last_played_at'] ?? '');
  $raw = trim($raw);
  if ($raw === '') return '—';
  return substr($raw, 0, 16);
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Recently played</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Browse songs</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(APP_BASE) ?>/" id="songsSearchForm"
          class="row g-2 align-items-end"
          data-page-route="/recent"
          data-api-route="/api/recent"
          data-render-mode="recent">
      <input type="hidden" name="r" value="/recent">
      <input type="hidden" name="view" id="songsView" value="<?= e($view) ?>">

      <div class="col-12 col-lg-4 position-relative">
        <label class="form-label">Search</label>
        <input class="form-control" name="q" id="songsQ" placeholder="Search title/artist" value="<?= e((string)($filters['q'] ?? '')) ?>" autocomplete="off">
        <div class="list-group position-absolute w-100 shadow-sm d-none" id="songsSuggest" style="z-index: 5;"></div>
      </div>

      <div class="col-12 col-lg-2">
        <label class="form-label">Mode</label>
        <select class="form-select" name="mode" id="recentMode">
          <option value="unique" <?= $mode === 'unique' ? 'selected' : '' ?>>Unique songs</option>
          <option value="history" <?= $mode === 'history' ? 'selected' : '' ?>>History</option>
        </select>
      </div>

      <div class="col-12 col-lg-2">
        <label class="form-label">Sort</label>
        <select class="form-select" name="sort" id="songsSort">
          <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most recent</option>
          <option value="plays" <?= $sort === 'plays' ? 'selected' : '' ?> <?= $mode === 'history' ? 'disabled' : '' ?>>Most played (you)</option>
          <option value="title" <?= $sort === 'title' ? 'selected' : '' ?> <?= $mode === 'history' ? 'disabled' : '' ?>>Title A–Z</option>
        </select>
      </div>

      <div class="col-12 col-lg-2">
        <label class="form-label">Per page</label>
        <select class="form-select" name="per_page" id="songsPerPage">
          <?php foreach ([20, 40, 60, 80, 100] as $n): ?>
            <option value="<?= (int)$n ?>" <?= ((int)$pager->perPage === (int)$n) ? 'selected' : '' ?>><?= (int)$n ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="page" id="songsPage" value="<?= (int)$pager->page ?>">
      <div class="col-12 col-lg-2 d-flex gap-2">
        <button class="btn btn-outline-primary flex-grow-1" id="songsGo"><i class="bi bi-search me-1" aria-hidden="true"></i>Go</button>
        <div class="btn-group" role="group" aria-label="View mode">
          <button type="button" class="btn btn-outline-secondary" data-songs-view="tile" title="Tile view" aria-label="Tile view" aria-pressed="<?= $view === 'tile' ? 'true' : 'false' ?>">
            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-outline-secondary" data-songs-view="list" title="List view" aria-label="List view" aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>">
            <i class="bi bi-list-ul" aria-hidden="true"></i>
          </button>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="songsCancel" title="Cancel">
          <i class="bi bi-x-lg" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Cancel</span>
        </button>
      </div>
    </form>
  </div>
</div>

<div id="songsResults">
  <?php if (!$songs): ?>
    <div class="alert alert-info">No plays yet.</div>
  <?php else: ?>
    <?php if ($view === 'list'): ?>
      <div class="list-group shadow-sm" id="songsList">
        <?php foreach ($songs as $s): ?>
          <?php $flag = language_flag_url((string)($s['language'] ?? '')); ?>
          <?php $isFav = !empty($favoriteIds[(int)$s['id']]); ?>
          <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 position-relative song-item">
            <a class="stretched-link" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>" aria-label="Open song"></a>
            <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
              <?php if (!empty($s['cover_url'])): ?>
                <img src="<?= e((string)$s['cover_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
              <?php else: ?>
                <i class="bi bi-image" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1 text-truncate">
              <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                <button type="button"
                        class="btn btn-sm btn-link p-0 fav-btn song-action js-fav-toggle <?= $isFav ? 'text-danger' : 'text-muted' ?>"
                        data-song-id="<?= (int)$s['id'] ?>"
                        data-favorited="<?= $isFav ? '1' : '0' ?>"
                        aria-label="Favorite"
                        aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                        title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">
                  <i class="bi <?= $isFav ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                </button>
              </div>
              <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
            </div>
            <div class="d-flex align-items-center gap-2 text-muted small text-nowrap">
              <?php if ($flag): ?>
                <img class="lang-flag lang-flag-xs lang-flag-circle" src="<?= e($flag) ?>" alt="<?= e((string)($s['language'] ?? '')) ?>" title="<?= e((string)($s['language'] ?? '')) ?>">
              <?php endif; ?>
              <span><i class="bi bi-clock-history me-1" aria-hidden="true"></i><?= e(played_at_text($s, $mode)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="row g-3" id="songsGrid">
        <?php foreach ($songs as $s): ?>
          <?php $flag = language_flag_url((string)($s['language'] ?? '')); ?>
          <?php $isFav = !empty($favoriteIds[(int)$s['id']]); ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="card song-card h-100 shadow-sm position-relative song-item">
              <a class="stretched-link" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>" aria-label="Open song"></a>
              <div class="cover">
                <?php if (!empty($s['cover_url'])): ?>
                  <img src="<?= e((string)$s['cover_url']) ?>" alt="">
                <?php else: ?>
                  <div class="placeholder"><i class="bi bi-image me-1" aria-hidden="true"></i>No cover</div>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div class="fw-semibold text-truncate"><?= e((string)$s['title']) ?></div>
                  <button type="button"
                          class="btn btn-sm btn-link p-0 fav-btn song-action js-fav-toggle <?= $isFav ? 'text-danger' : 'text-muted' ?>"
                          data-song-id="<?= (int)$s['id'] ?>"
                          data-favorited="<?= $isFav ? '1' : '0' ?>"
                          aria-label="Favorite"
                          aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                          title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">
                    <i class="bi <?= $isFav ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                  </button>
                </div>
                <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
                <div class="d-flex align-items-center justify-content-between text-muted small">
                  <div><i class="bi bi-clock-history me-1" aria-hidden="true"></i><?= e(played_at_text($s, $mode)) ?></div>
                  <div>
                    <?php if ($flag): ?>
                      <img class="lang-flag lang-flag-md lang-flag-circle" src="<?= e($flag) ?>" alt="<?= e((string)($s['language'] ?? '')) ?>" title="<?= e((string)($s['language'] ?? '')) ?>">
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div id="songsPager" class="d-flex justify-content-center mt-4">
  <?php if ($pager->pages > 1): ?>
    <nav aria-label="Recent pages">
      <ul class="pagination mb-0">
        <li class="page-item <?= $pager->hasPrev() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= recent_page_href($params, $pager->prevPage()) ?>" data-page="<?= (int)$pager->prevPage() ?>">Prev</a>
        </li>
        <?php foreach ($pager->window(2) as $p): ?>
          <li class="page-item <?= $p === $pager->page ? 'active' : '' ?>">
            <a class="page-link" href="<?= recent_page_href($params, (int)$p) ?>" data-page="<?= (int)$p ?>"><?= (int)$p ?></a>
          </li>
        <?php endforeach; ?>
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= recent_page_href($params, $pager->nextPage()) ?>" data-page="<?= (int)$pager->nextPage() ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script src="<?= e(APP_BASE) ?>/assets/js/songs-search.js"></script>

