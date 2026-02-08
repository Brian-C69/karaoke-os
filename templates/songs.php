<?php
/** @var array $filters */
/** @var array $songs */
/** @var SimplePager $pager */
$pager = $pager ?? new SimplePager(count($songs), 1, 20);
$params = [
  'r' => '/songs',
  'q' => (string)($filters['q'] ?? ''),
  'sort' => (string)($filters['sort'] ?? 'latest'),
  'per_page' => (int)$pager->perPage,
];
if (!empty($filters['artist'])) $params['artist'] = (string)$filters['artist'];
if (!empty($filters['language'])) $params['language'] = (string)$filters['language'];

function songs_page_href(array $params, int $page): string {
  $params['page'] = $page;
  return e(APP_BASE) . '/?' . http_build_query($params);
}
?>
<div class="mb-3">
  <h1 class="h4 m-0"><i class="bi bi-music-note-list me-2" aria-hidden="true"></i>Songs</h1>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(APP_BASE) ?>/" id="songsSearchForm" class="row g-2 align-items-end">
      <input type="hidden" name="r" value="/songs">
      <div class="col-12 col-lg-6 position-relative">
        <label class="form-label">Search</label>
        <input class="form-control" name="q" id="songsQ" placeholder="Search title/artist" value="<?= e((string)$filters['q']) ?>" autocomplete="off">
        <div class="list-group position-absolute w-100 shadow-sm d-none" id="songsSuggest" style="z-index: 5;"></div>
      </div>
      <div class="col-12 col-lg-2">
        <label class="form-label">Sort</label>
        <select class="form-select" name="sort" id="songsSort">
          <option value="latest" <?= ($filters['sort'] ?? '') === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="plays" <?= ($filters['sort'] ?? '') === 'plays' ? 'selected' : '' ?>>Most played</option>
          <option value="title" <?= ($filters['sort'] ?? '') === 'title' ? 'selected' : '' ?>>Title A–Z</option>
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
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/songs" title="Clear"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
      </div>
      <?php if (($filters['artist'] ?? '') !== ''): ?>
        <div class="col-12">
          <span class="badge text-bg-secondary">Artist: <?= e((string)$filters['artist']) ?></span>
          <a class="ms-2 small" href="<?= e(APP_BASE) ?>/?r=/songs">Clear</a>
        </div>
      <?php endif; ?>
      <?php if (($filters['language'] ?? '') !== ''): ?>
        <div class="col-12">
          <span class="badge text-bg-secondary">Language: <?= e((string)$filters['language']) ?></span>
          <a class="ms-2 small" href="<?= e(APP_BASE) ?>/?r=/songs">Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div id="songsResults">
  <?php if (!$songs): ?>
    <div class="alert alert-info">No songs found.</div>
  <?php else: ?>
    <div class="row g-3" id="songsGrid">
      <?php foreach ($songs as $s): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a class="card song-card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/song&id=<?= (int)$s['id'] ?>">
            <div class="cover">
              <?php if (!empty($s['cover_url'])): ?>
                <img src="<?= e((string)$s['cover_url']) ?>" alt="">
              <?php else: ?>
                <div class="placeholder"><i class="bi bi-image me-1" aria-hidden="true"></i>No cover</div>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <div class="fw-semibold text-dark text-truncate"><?= e((string)$s['title']) ?></div>
              <div class="text-muted small text-truncate"><?= e((string)$s['artist']) ?></div>
              <div class="text-muted small"><?= (int)$s['play_count'] ?> plays</div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="songsPager" class="d-flex justify-content-center mt-4">
  <?php if ($pager->pages > 1): ?>
    <nav aria-label="Songs pages">
      <ul class="pagination mb-0">
        <li class="page-item <?= $pager->hasPrev() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= songs_page_href($params, $pager->prevPage()) ?>" data-page="<?= (int)$pager->prevPage() ?>">Prev</a>
        </li>
        <?php foreach ($pager->window(2) as $p): ?>
          <li class="page-item <?= $p === $pager->page ? 'active' : '' ?>">
            <a class="page-link" href="<?= songs_page_href($params, (int)$p) ?>" data-page="<?= (int)$p ?>"><?= (int)$p ?></a>
          </li>
        <?php endforeach; ?>
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= songs_page_href($params, $pager->nextPage()) ?>" data-page="<?= (int)$pager->nextPage() ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script src="<?= e(APP_BASE) ?>/assets/js/songs-search.js"></script>
