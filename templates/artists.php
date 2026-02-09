<?php
/** @var array $artists */
/** @var string $sort */
/** @var string $q */
/** @var SimplePager $pager */
$q = (string)($q ?? '');
$sort = strtolower(trim((string)($sort ?? 'latest')));
if (!in_array($sort, ['plays', 'songs', 'name', 'latest'], true)) {
  $sort = 'latest';
}
/** @var string $view */
$view = strtolower(trim((string)($view ?? ($_GET['view'] ?? 'tile'))));
if (!in_array($view, ['tile', 'list'], true)) {
  $view = 'tile';
}

$cancelParams = ['r' => '/artists'];
if ($q !== '') {
  // cancel clears q only
}
if ($sort !== 'latest') $cancelParams['sort'] = $sort;
if ((int)$pager->perPage !== 20) $cancelParams['per_page'] = (int)$pager->perPage;
if ($view !== 'tile') $cancelParams['view'] = $view;

function artists_page_href(array $params, int $page): string {
  $params['page'] = $page;
  return e(APP_BASE) . '/?' . http_build_query($params);
}

function artists_href(array $params): string {
  return e(APP_BASE) . '/?' . http_build_query($params);
}
?>
<div class="mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</h1>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(APP_BASE) ?>/" id="artistsSearchForm" class="row g-2 align-items-end">
      <input type="hidden" name="r" value="/artists">
      <input type="hidden" name="view" id="artistsView" value="<?= e($view) ?>">

      <div class="col-12 col-lg-5">
        <label class="form-label">Search Artist</label>
        <input class="form-control" name="q" id="artistsQ" placeholder="Search artist" value="<?= e($q) ?>" autocomplete="off">
      </div>
      <div class="col-12 col-lg-2">
        <label class="form-label">Sort</label>
        <select class="form-select" name="sort" id="artistsSort">
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="plays" <?= $sort === 'plays' ? 'selected' : '' ?>>Most plays</option>
          <option value="songs" <?= $sort === 'songs' ? 'selected' : '' ?>>Most songs</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>A–Z</option>
        </select>
      </div>
      <div class="col-12 col-lg-2">
        <label class="form-label">Per page</label>
        <select class="form-select" name="per_page" id="artistsPerPage">
          <?php foreach ([20, 40, 60, 80, 100] as $n): ?>
            <option value="<?= (int)$n ?>" <?= ((int)$pager->perPage === (int)$n) ? 'selected' : '' ?>><?= (int)$n ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="page" id="artistsPage" value="<?= (int)$pager->page ?>">
      <div class="col-12 col-lg-3 d-flex gap-2">
        <button class="btn btn-outline-primary flex-grow-1" id="artistsGo"><i class="bi bi-search me-1" aria-hidden="true"></i>Go</button>
        <div class="btn-group" role="group" aria-label="View mode">
          <button type="button" class="btn btn-outline-secondary" data-artists-view="tile" title="Tile view" aria-label="Tile view" aria-pressed="<?= $view === 'tile' ? 'true' : 'false' ?>">
            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-outline-secondary" data-artists-view="list" title="List view" aria-label="List view" aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>">
            <i class="bi bi-list-ul" aria-hidden="true"></i>
          </button>
        </div>
        <a class="btn btn-outline-secondary" id="artistsCancel" href="<?= artists_href($cancelParams) ?>" title="Cancel">
          <i class="bi bi-x-lg" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Cancel</span>
        </a>
      </div>
    </form>
  </div>
</div>

<?php
$pageParams = [
  'r' => '/artists',
  'q' => $q,
  'sort' => $sort,
  'per_page' => (int)$pager->perPage,
];
if ($view !== 'tile') $pageParams['view'] = $view;
if ($pageParams['sort'] === 'latest') unset($pageParams['sort']);
if ($pageParams['per_page'] === 20) unset($pageParams['per_page']);
if ($pageParams['q'] === '') unset($pageParams['q']);
?>

<div id="artistsResults">
  <?php if (!$artists): ?>
    <div class="alert alert-info">No artists found.</div>
  <?php else: ?>
    <?php if ($view === 'list'): ?>
      <div class="list-group shadow-sm" id="artistsList">
        <?php foreach ($artists as $a): ?>
          <?php
            $img = trim((string)($a['image_url'] ?? ''));
            $imgIsExternal = $img !== '' && is_safe_external_url($img);
            if ($img !== '' && !$imgIsExternal) $img = e(APP_BASE) . '/' . ltrim($img, '/');
          ?>
          <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$a['id'] ?>">
            <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
              <?php if ($img !== ''): ?>
                <img src="<?= $img ?>" alt="" style="width:44px;height:44px;object-fit:cover;">
              <?php else: ?>
                <i class="bi bi-person-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1 text-truncate">
              <div class="fw-semibold text-truncate"><?= e((string)$a['name']) ?></div>
              <div class="text-muted small text-truncate"><?= (int)($a['song_count'] ?? 0) ?> songs · <?= (int)($a['play_count'] ?? 0) ?> plays</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="row g-3" id="artistsGrid">
        <?php foreach ($artists as $a): ?>
          <?php
            $img = trim((string)($a['image_url'] ?? ''));
            $imgIsExternal = $img !== '' && is_safe_external_url($img);
            if ($img !== '' && !$imgIsExternal) $img = e(APP_BASE) . '/' . ltrim($img, '/');
          ?>
          <div class="col-6 col-md-4 col-lg-3">
            <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/artist&id=<?= (int)$a['id'] ?>">
              <div class="ratio ratio-1x1 bg-dark overflow-hidden">
                <?php if ($img !== ''): ?>
                  <img src="<?= $img ?>" alt="" style="object-fit:cover; width:100%; height:100%;">
                <?php else: ?>
                  <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50">
                    <i class="bi bi-person-circle fs-1" aria-hidden="true"></i>
                  </div>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <div class="fw-semibold text-dark text-truncate"><?= e((string)$a['name']) ?></div>
                <div class="text-muted small"><?= (int)($a['song_count'] ?? 0) ?> songs · <?= (int)($a['play_count'] ?? 0) ?> plays</div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div id="artistsPager" class="d-flex justify-content-center mt-4">
  <?php if ($pager->pages > 1): ?>
    <nav aria-label="Artists pages">
      <ul class="pagination mb-0">
        <li class="page-item <?= $pager->hasPrev() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= artists_page_href($pageParams, $pager->prevPage()) ?>" data-page="<?= (int)$pager->prevPage() ?>">Prev</a>
        </li>
        <?php foreach ($pager->window(2) as $p): ?>
          <li class="page-item <?= $p === $pager->page ? 'active' : '' ?>">
            <a class="page-link" href="<?= artists_page_href($pageParams, (int)$p) ?>" data-page="<?= (int)$p ?>"><?= (int)$p ?></a>
          </li>
        <?php endforeach; ?>
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
          <a class="page-link" href="<?= artists_page_href($pageParams, $pager->nextPage()) ?>" data-page="<?= (int)$pager->nextPage() ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script src="<?= e(APP_BASE) ?>/assets/js/artists-search.js"></script>
