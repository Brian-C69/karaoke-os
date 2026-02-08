<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

// Safety: only allow setup from localhost.
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remote, ['127.0.0.1', '::1'], true);
if (!$isLocal) {
    http_response_code(403);
    echo 'Setup is restricted to localhost.';
    exit;
}

$db = db();
ensure_schema($db);

$created = [];

// Seed only if empty.
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
    $stmt = $db->prepare('INSERT INTO users (username, email, email_verified_at, password_hash, role, is_paid, created_at) VALUES (:u, :e, :v, :p, :r, :ip, :t)');

    $stmt->execute([
        ':u' => 'admin',
        ':e' => 'admin@example.com',
        ':v' => now_db(),
        ':p' => password_hash('admin12345', PASSWORD_DEFAULT),
        ':r' => 'admin',
        ':ip' => 1,
        ':t' => now_db(),
    ]);
    $created[] = 'admin / admin12345 (admin)';

    $stmt->execute([
        ':u' => 'user',
        ':e' => 'user@example.com',
        ':v' => null,
        ':p' => password_hash('user12345', PASSWORD_DEFAULT),
        ':r' => 'user',
        ':ip' => 0,
        ':t' => now_db(),
    ]);
    $created[] = 'user / user12345 (user; not verified; not paid)';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Karaoke OS Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Karaoke OS Setup</h1>
        <p class="mb-2">Database initialized at <code><?= htmlspecialchars(DB_PATH, ENT_QUOTES) ?></code>.</p>

        <?php if ($created): ?>
          <div class="alert alert-success">
            <div class="fw-semibold mb-2">Seeded accounts:</div>
            <ul class="mb-0">
              <?php foreach ($created as $c): ?>
                <li><code><?= htmlspecialchars($c, ENT_QUOTES) ?></code></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-3">Users already exist; no changes made.</div>
        <?php endif; ?>

        <a class="btn btn-primary" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/?r=/login">Go to Login</a>
        <div class="text-muted small mt-3">Tip: once done, you can keep <code>setup.php</code> for convenience (localhost-only) or delete it.</div>
      </div>
    </div>
  </div>
</body>
</html>
