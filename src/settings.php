<?php
declare(strict_types=1);

function get_setting(PDO $db, string $key, ?string $default = null): ?string
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    return isset($row['value']) ? (string)$row['value'] : $default;
}

function set_setting(PDO $db, string $key, ?string $value): void
{
    $stmt = $db->prepare('INSERT INTO settings (key, value, updated_at) VALUES (:k, :v, :t)
                          ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at');
    $stmt->execute([
        ':k' => $key,
        ':v' => $value,
        ':t' => now_db(),
    ]);
}

function get_smtp_settings(PDO $db): array
{
    return [
        'enabled' => get_setting($db, 'smtp_enabled', '0') === '1',
        'host' => (string)(get_setting($db, 'smtp_host', '') ?? ''),
        'port' => (int)(get_setting($db, 'smtp_port', '587') ?? '587'),
        'encryption' => (string)(get_setting($db, 'smtp_encryption', 'tls') ?? 'tls'), // tls|ssl|none
        'username' => (string)(get_setting($db, 'smtp_username', '') ?? ''),
        'password' => (string)(get_setting($db, 'smtp_password', '') ?? ''),
        'from_email' => (string)(get_setting($db, 'smtp_from_email', EMAIL_FROM) ?? EMAIL_FROM),
        'from_name' => (string)(get_setting($db, 'smtp_from_name', 'Karaoke OS') ?? 'Karaoke OS'),
    ];
}

