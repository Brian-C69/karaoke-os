<?php /** @var array $users */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-people me-2" aria-hidden="true"></i>Admin · Users</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/user-new"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add user</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin">Admin home</a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="fw-semibold mb-2"><i class="bi bi-upload me-1" aria-hidden="true"></i>Bulk insert (paste)</div>
    <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/users" class="mb-0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="bulk_insert">
      <textarea class="form-control font-monospace" name="bulk_lines" rows="6" placeholder="Username | Password | Role(user/admin) | Email(optional) | Paid(0/1 optional) | PaidUntil(YYYY-MM-DD optional)&#10;user2 | pass1234 | user | user2@example.com | 0 |"></textarea>
      <div class="text-muted small mt-1">Separator: <code>|</code> or <code>TAB</code>. Lines starting with <code>#</code> are ignored.</div>
      <div class="mt-2 d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Insert users</button>
      </div>
    </form>
  </div>
</div>

<form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/users" data-bulk-root>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="bulk_update">

  <div class="card shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="fw-semibold">Bulk action</div>
        <select class="form-select form-select-sm" name="bulk_action" style="width: 230px;">
          <option value="">Choose…</option>
          <option value="revoke">Revoke access</option>
          <option value="restore">Restore access</option>
          <option value="set_paid">Set paid</option>
          <option value="unset_paid">Unset paid</option>
          <option value="mark_verified">Mark email verified</option>
          <option value="clear_verified">Clear verification</option>
          <option value="set_paid_until">Set paid until…</option>
          <option value="clear_paid_until">Clear paid until</option>
        </select>
        <input class="form-control form-control-sm" name="paid_until" placeholder="YYYY-MM-DD (for set paid until)" style="width: 260px;">
        <button class="btn btn-sm btn-primary" type="submit" data-bulk-apply disabled onclick="return confirm('Apply bulk action to selected users?');">
          Apply (<span data-bulk-count>0</span>)
        </button>
      </div>
      <div class="text-muted small">Select is before row # (as requested).</div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th class="text-center" style="width:44px;">
              <input class="form-check-input" type="checkbox" data-bulk-all aria-label="Select all users">
            </th>
            <th class="text-end" style="width:60px;">#</th>
            <th>Username</th>
            <th>Email</th>
            <th class="text-center">Verified</th>
            <th class="text-center">Paid</th>
            <th class="text-center">Access</th>
            <th>Role</th>
            <th>Created</th>
            <th>Last login</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($users as $u): $n++; ?>
          <?php $revoked = ((int)($u['is_revoked'] ?? 0) === 1); ?>
          <tr>
            <td class="text-center">
              <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-bulk-item aria-label="Select user">
            </td>
            <td class="text-end text-muted"><?= (int)$n ?></td>
            <td class="fw-semibold"><?= e((string)$u['username']) ?></td>
            <td class="text-muted small"><?= e((string)($u['email'] ?? '—')) ?></td>
            <td class="text-center">
              <?php if (!empty($u['email_verified_at'])): ?>
                <i class="bi bi-patch-check-fill text-success" title="Verified" aria-label="Verified"></i>
              <?php else: ?>
                <i class="bi bi-dash-lg text-muted" aria-hidden="true"></i>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if (((int)($u['is_paid'] ?? 0) === 1)): ?>
                <i class="bi bi-check-circle-fill text-success" title="Paid" aria-label="Paid"></i>
              <?php else: ?>
                <i class="bi bi-dash-lg text-muted" aria-hidden="true"></i>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($revoked): ?>
                <i class="bi bi-shield-lock-fill text-danger" title="Revoked" aria-label="Revoked"></i>
              <?php else: ?>
                <i class="bi bi-shield-check text-success" title="Active" aria-label="Active"></i>
              <?php endif; ?>
            </td>
            <td><span class="badge text-bg-<?= ($u['role'] === 'admin') ? 'danger' : 'secondary' ?>"><?= e((string)$u['role']) ?></span></td>
            <td class="text-muted small"><?= e((string)$u['created_at']) ?></td>
            <td class="text-muted small"><?= e((string)($u['last_login_at'] ?? '—')) ?></td>
            <td class="text-end text-nowrap">
              <div class="d-inline-flex flex-nowrap gap-1">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/user-edit&id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/admin/user-usage&id=<?= (int)$u['id'] ?>"><i class="bi bi-activity" aria-hidden="true"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<script src="<?= e(APP_BASE) ?>/assets/js/admin-bulk.js"></script>
