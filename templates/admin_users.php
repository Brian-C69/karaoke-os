<?php /** @var array $users */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-people me-2" aria-hidden="true"></i>Admin · Users</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin/user-new"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add user</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/admin">Admin home</a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
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
      <?php foreach ($users as $u): ?>
        <?php
          $revoked = ((int)($u['is_revoked'] ?? 0) === 1);
          $isSelf = current_user() && ((int)current_user()['id'] === (int)($u['id'] ?? 0));
        ?>
        <tr>
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
              <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/user-edit&id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
              <?php if ($revoked): ?>
                <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/user-restore" class="m-0">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-outline-success"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Restore</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= e(APP_BASE) ?>/?r=/admin/user-revoke" class="m-0" onsubmit="return confirm('Revoke login access for this user?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" <?= $isSelf ? 'disabled' : '' ?> title="<?= $isSelf ? 'You cannot revoke yourself' : 'Revoke login access' ?>">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i>Revoke
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
