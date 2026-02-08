<?php
declare(strict_types=1);

function current_user(): ?array
{
    $u = $_SESSION['user'] ?? null;
    return is_array($u) ? $u : null;
}

function current_user_full(PDO $db): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, username, role, email, email_verified_at, is_paid, paid_until, created_at, last_login_at FROM users WHERE id = :id');
    $stmt->execute([':id' => (int)($u['id'] ?? 0)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_login(): void
{
    if (!current_user()) {
        flash('warning', 'Please login to continue.');
        redirect('/?r=/login');
    }
}

function require_admin(): void
{
    require_login();
    $user = current_user_full(db());
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        render('error', ['message' => 'Forbidden (admin only).']);
        exit;
    }
}

function require_paid_verified(PDO $db): void
{
    require_login();
    $user = current_user_full($db);
    if (!$user) {
        logout_now();
        flash('warning', 'Please login again.');
        redirect('/?r=/login');
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('warning', 'Add a valid email to your account before playing.');
        redirect('/?r=/account');
    }
    if (empty($user['email_verified_at'])) {
        flash('warning', 'Verify your email before playing.');
        redirect('/?r=/account');
    }
    if (!user_is_paid($user)) {
        flash('warning', 'Paid access required to play songs.');
        redirect('/?r=/account');
    }
}

function user_is_paid(array $user): bool
{
    if ((int)($user['is_paid'] ?? 0) !== 1) {
        return false;
    }
    $until = trim((string)($user['paid_until'] ?? ''));
    if ($until === '') {
        return true;
    }
    // Date-only compare (YYYY-MM-DD) or ISO-like strings.
    return substr($until, 0, 10) >= (new DateTimeImmutable('now'))->format('Y-m-d');
}

function login_attempt(PDO $db, string $username, string $password): bool
{
    $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :u OR email = :u');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if (!password_verify($password, (string)$row['password_hash'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'role' => (string)$row['role'],
    ];

    $stmt = $db->prepare('UPDATE users SET last_login_at = :t WHERE id = :id');
    $stmt->execute([':t' => now_db(), ':id' => (int)$row['id']]);

    return true;
}

function logout_now(): void
{
    unset($_SESSION['user']);
}
