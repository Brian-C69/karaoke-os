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

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function request_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $val = (string)($_SERVER[$key] ?? '');
    if ($val !== '') {
        return $val;
    }
    // Some servers pass Authorization here.
    if (strtolower($name) === 'authorization') {
        $val = (string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($val !== '') {
            return $val;
        }
    }
    return '';
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

function language_to_flag_code(string $language): ?string
{
    $language = strtoupper(trim($language));
    if ($language === '' || $language === 'UNKNOWN') {
        return null;
    }

    // Normalize separators (e.g. en-US, en_US).
    $language = str_replace('_', '-', $language);

    // Prefer region if present.
    $parts = explode('-', $language);
    $lang = $parts[0] ?? $language;
    $region = $parts[1] ?? '';

    // Common mappings: language -> representative flag.
    $map = [
        'EN' => 'GB',
        'ZH' => 'CN',
        'JA' => 'JP',
        'KO' => 'KR',
        'MS' => 'MY',
        'BM' => 'MY',
        'ID' => 'ID',
        'TH' => 'TH',
        'VI' => 'VN',
        'TL' => 'PH',
        'FIL' => 'PH',
        'AR' => 'SA',
        'HI' => 'IN',
    ];

    if ($region !== '') {
        // If the region looks like a country code, use it directly.
        if (preg_match('/^[A-Z]{2}$/', $region)) {
            return $region;
        }
    }

    if (isset($map[$lang])) {
        return $map[$lang];
    }

    // If it already looks like a country code, pass through.
    if (preg_match('/^[A-Z]{2}$/', $language)) {
        return $language;
    }

    return null;
}

function language_flag_url(string $language): ?string
{
    $code = language_to_flag_code($language);
    if (!$code) {
        return null;
    }
    $code = strtolower($code);
    static $exists = [];
    if (!array_key_exists($code, $exists)) {
        $exists[$code] = is_file(APP_ROOT . '/assets/vendor/square-flags/flags/' . $code . '.svg');
    }
    if (!$exists[$code]) return null;
    return APP_BASE . '/assets/vendor/square-flags/flags/' . $code . '.svg';
}
