<?php /** @var array $target */ ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>Admin · Edit User</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/users"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/user-edit&id=<?= (int)$target['id'] ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <div class="text-muted small">Username</div>
          <div class="fw-semibold"><?= e((string)$target['username']) ?></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <option value="user" <?= ($target['role'] === 'user') ? 'selected' : '' ?>>user</option>
            <option value="admin" <?= ($target['role'] === 'admin') ? 'selected' : '' ?>>admin</option>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" value="<?= e((string)($target['email'] ?? '')) ?>">
          <div class="text-muted small mt-2">
            Verification:
            <?php if (!empty($target['email_verified_at'])): ?>
              <span class="badge text-bg-success">Verified</span>
              <span class="text-muted small">at <?= e((string)$target['email_verified_at']) ?></span>
            <?php else: ?>
              <span class="badge text-bg-warning">Not verified</span>
            <?php endif; ?>
          </div>
          <div class="mt-2 d-flex gap-2 flex-wrap">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="mark_verified" id="mv">
              <label class="form-check-label" for="mv">Mark verified now</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="clear_verified" id="cv">
              <label class="form-check-label" for="cv">Clear verification</label>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Paid until (optional)</label>
          <input class="form-control" name="paid_until" value="<?= e((string)($target['paid_until'] ?? '')) ?>" placeholder="YYYY-MM-DD">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_paid" id="paid" <?= ((int)($target['is_paid'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="paid">Paid active</label>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Reset password (optional)</label>
          <input class="form-control" type="password" name="password" placeholder="New password">
          <div class="text-muted small mt-1">Leave blank to keep current password.</div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2 flex-wrap">
        <button class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>Save</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/admin/users">Cancel</a>
      </div>
    </form>

    <hr class="my-4">

  </div>
</div>
