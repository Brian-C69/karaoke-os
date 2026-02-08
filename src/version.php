<?php
declare(strict_types=1);

function app_version(): string
{
    $path = APP_ROOT . DIRECTORY_SEPARATOR . 'VERSION';
    if (!is_file($path)) {
        return '0.0.0';
    }

    $raw = trim((string)file_get_contents($path));
    if (!preg_match('/^\d+\.\d+\.\d+$/', $raw)) {
        return '0.0.0';
    }

    return $raw;
}

