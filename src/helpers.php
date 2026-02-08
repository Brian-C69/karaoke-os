<?php
declare(strict_types=1);

function now_db(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES);
}

function redirect(string $to): void
{
    if (defined('APP_BASE') && APP_BASE !== '' && strncmp($to, '/?', 2) === 0) {
        $to = APP_BASE . $to;
    }
    header('Location: ' . $to, true, 302);
    exit;
}

function is_safe_external_url(string $url): bool
{
    if (preg_match('/[\\r\\n]/', $url)) {
        return false;
    }
    return (bool)preg_match('#^https?://#i', $url);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function csrf_verify(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if (!$token || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
        http_response_code(400);
        echo 'Bad Request (CSRF)';
        exit;
    }
}

function flash(string $level, string $message): void
{
    $_SESSION['flash'][] = ['level' => $level, 'message' => $message];
}

function consume_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function render(string $template, array $vars = []): void
{
    $pageTitle = $vars['pageTitle'] ?? ($GLOBALS['pageTitle'] ?? 'Karaoke OS');
    $templateFile = APP_ROOT . '/templates/' . $template . '.php';
    if (!is_file($templateFile)) {
        http_response_code(500);
        echo 'Template not found.';
        exit;
    }

    $flash = consume_flash();
    $user = current_user() ? current_user_full(db()) : null;
    extract($vars, EXTR_SKIP);

    require APP_ROOT . '/templates/layout.php';
}
