<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Admin · Add User</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/users"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/user-new">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Username *</label>
          <input class="form-control" name="username" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" placeholder="user@example.com">
        </div>
        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <input class="form-control" type="password" name="password" required>
          <div class="text-muted small mt-1">Minimum 6 characters.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Role</label>
          <select class="form-select" name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Paid until (optional)</label>
          <input class="form-control" name="paid_until" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_paid" id="paid">
            <label class="form-check-label" for="paid">Paid active</label>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Create</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/admin/users">Cancel</a>
      </div>
    </form>
  </div>
</div>
