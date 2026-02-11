<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img src="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png" alt="" width="40" height="40" style="object-fit:contain;">
          <div class="lh-sm">
            <div class="fw-bold">Karaoke <span style="color:#db4143;">OS</span></div>
            <div class="text-muted small">Sign in to play</div>
          </div>
        </div>
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
