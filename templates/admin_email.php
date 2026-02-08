<?php /** @var array $smtp */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-envelope-at me-2" aria-hidden="true"></i>Admin · Email</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin">Admin home</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/email">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="smtp_enabled" id="en" <?= !empty($smtp['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="en">Enable SMTP</label>
          </div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Host</label>
          <input class="form-control" name="smtp_host" value="<?= e((string)$smtp['host']) ?>" placeholder="smtp.gmail.com">
        </div>
        <div class="col-md-2">
          <label class="form-label">Port</label>
          <input class="form-control" name="smtp_port" value="<?= e((string)$smtp['port']) ?>" placeholder="587">
        </div>
        <div class="col-md-2">
          <label class="form-label">Encryption</label>
          <select class="form-select" name="smtp_encryption">
            <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>tls</option>
            <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>ssl</option>
            <option value="none" <?= ($smtp['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>none</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input class="form-control" name="smtp_username" value="<?= e((string)$smtp['username']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="smtp_password" value="<?= e((string)$smtp['password']) ?>">
          <div class="text-muted small mt-1">Stored in SQLite for now (local app). Keep the DB file private.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">From email</label>
          <input class="form-control" name="smtp_from_email" value="<?= e((string)$smtp['from_email']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">From name</label>
          <input class="form-control" name="smtp_from_name" value="<?= e((string)$smtp['from_name']) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Send test email to</label>
          <div class="input-group">
            <input class="form-control" name="test_to" placeholder="you@example.com">
            <button class="btn btn-outline-primary" name="send_test" value="1"><i class="bi bi-send me-1" aria-hidden="true"></i>Send test</button>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>Save</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/admin">Cancel</a>
      </div>
    </form>
  </div>
</div>
