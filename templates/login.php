<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h5 mb-3"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Login</h1>
        <form method="post" action="<?= e(APP_BASE) ?>/?r=/login">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Username or Email</label>
            <input class="form-control" name="username" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" autocomplete="current-password" required>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-key me-1" aria-hidden="true"></i>Login</button>
        </form>
        <div class="text-muted small mt-3">Admin creates user accounts.</div>
      </div>
    </div>
  </div>
</div>
