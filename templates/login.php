<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 10rem);">
  <div class="w-100" style="max-width: 420px;">
    <div class="text-center mb-3">
      <img src="<?= e(APP_BASE) ?>/assets/img/karaoke_os_icon.png" alt="" width="56" height="56" style="object-fit:contain;">
      <div class="fw-bold mt-2" style="font-size:1.35rem;">Karaoke <span style="color:#db4143;">OS</span></div>
      <div class="text-muted small">Sign in to play</div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" action="<?= e(APP_BASE) ?>/?r=/login">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Username or Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
              <input class="form-control" name="username" autocomplete="username" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
              <input id="login-password" class="form-control" type="password" name="password" autocomplete="current-password" required>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle="#login-password" aria-label="Show password" title="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-key me-1" aria-hidden="true"></i>Login</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const button = document.querySelector('[data-password-toggle]');
  if (!button) return;
  const selector = button.getAttribute('data-password-toggle');
  const input = selector ? document.querySelector(selector) : null;
  if (!input) return;

  const update = () => {
    const isPassword = input.type === 'password';
    button.setAttribute('aria-label', isPassword ? 'Show password' : 'Hide password');
    button.title = isPassword ? 'Show password' : 'Hide password';
    const icon = button.querySelector('i');
    if (icon) icon.className = `bi ${isPassword ? 'bi-eye' : 'bi-eye-slash'}`;
  };

  update();
  button.addEventListener('click', () => {
    input.type = input.type === 'password' ? 'text' : 'password';
    update();
    input.focus();
  });
})();
</script>
