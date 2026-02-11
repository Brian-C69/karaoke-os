<?php /** @var array $tools */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-tools me-2" aria-hidden="true"></i>Admin · Tools</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Admin home</a>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><i class="bi bi-download me-1" aria-hidden="true"></i>Export CSV</div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/export-songs"><i class="bi bi-music-note-list me-1" aria-hidden="true"></i>Songs</a>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/export-artists"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Artists</a>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/export-plays"><i class="bi bi-activity me-1" aria-hidden="true"></i>Plays</a>
        </div>
        <div class="text-muted small mt-2">CSV is UTF-8 with BOM (Excel-friendly).</div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-body d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><i class="bi bi-upload me-1" aria-hidden="true"></i>Import Songs (CSV)</div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#importSongsCsv" aria-expanded="false" aria-controls="importSongsCsv">
          <i class="bi bi-chevron-down me-1" aria-hidden="true"></i>Expand
        </button>
      </div>
      <div id="importSongsCsv" class="collapse">
        <div class="card-body">
          <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/tools" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_songs_csv">

            <div class="mb-2">
              <input class="form-control" type="file" name="csv" accept=".csv,text/csv">
              <div class="text-muted small mt-1">Max 10MB.</div>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="lookup_meta" id="lm">
              <label class="form-check-label" for="lm">Auto lookup metadata (slower; calls external services)</label>
            </div>

            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1" aria-hidden="true"></i>Import</button>
          </form>

          <div class="text-muted small mt-3">
            Supported columns (header row optional): <code>title</code>, <code>artist</code>, <code>drive</code>/<code>drive_url</code>/<code>drive_file_id</code>, <code>language</code>, <code>album</code>, <code>cover_url</code>, <code>genre</code>, <code>year</code>, <code>is_active</code>.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="fw-semibold"><i class="bi bi-database-down me-1" aria-hidden="true"></i>Backup Database</div>
          <div class="text-muted small">Downloads a consistent SQLite snapshot (VACUUM INTO).</div>
        </div>
        <a class="btn btn-outline-danger btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/backup-db" onclick="return confirm('Download DB backup?');">
          <i class="bi bi-download me-1" aria-hidden="true"></i>Download backup
        </a>
      </div>
    </div>
  </div>
</div>

