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
          <th>Role</th>
          <th>Created</th>
          <th>Last login</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="fw-semibold"><?= e((string)$u['username']) ?></td>
          <td class="text-muted small"><?= e((string)($u['email'] ?? '—')) ?></td>
          <td class="text-center"><?= !empty($u['email_verified_at']) ? '✅' : '—' ?></td>
          <td class="text-center"><?= ((int)($u['is_paid'] ?? 0) === 1) ? '✅' : '—' ?></td>
          <td><span class="badge text-bg-<?= ($u['role'] === 'admin') ? 'danger' : 'secondary' ?>"><?= e((string)$u['role']) ?></span></td>
          <td class="text-muted small"><?= e((string)$u['created_at']) ?></td>
          <td class="text-muted small"><?= e((string)($u['last_login_at'] ?? '—')) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_BASE) ?>/?r=/admin/user-edit&id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
