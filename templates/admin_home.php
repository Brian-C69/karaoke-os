<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Admin</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/songs">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i>Songs</div>
        <div class="text-muted small">Add/edit songs and Drive links.</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/users">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-people me-2" aria-hidden="true"></i>Users</div>
        <div class="text-muted small">Create user/admin accounts.</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/analytics">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Analytics</div>
        <div class="text-muted small">Top songs and artists by plays.</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/email">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-envelope-at me-2" aria-hidden="true"></i>Email</div>
        <div class="text-muted small">Configure verification emails.</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/artists">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Artists</div>
        <div class="text-muted small">Manage artist images.</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="card h-100 shadow-sm text-decoration-none" href="<?= e(APP_BASE) ?>/?r=/admin/tools">
      <div class="card-body">
        <div class="fw-semibold"><i class="bi bi-tools me-2" aria-hidden="true"></i>Tools</div>
        <div class="text-muted small">Import/export and backups.</div>
      </div>
    </a>
  </div>
</div>
