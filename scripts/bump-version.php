<?php
declare(strict_types=1);

/**
 * Usage:
 *   php scripts/bump-version.php [patch|minor|major]
 */

$part = strtolower((string)($argv[1] ?? 'patch'));
if (!in_array($part, ['patch', 'minor', 'major'], true)) {
    fwrite(STDERR, "Invalid part: {$part}\n");
    fwrite(STDERR, "Usage: php scripts/bump-version.php [patch|minor|major]\n");
    exit(2);
}

$root = dirname(__DIR__);
$versionFile = $root . DIRECTORY_SEPARATOR . 'VERSION';
$readmeFile = $root . DIRECTORY_SEPARATOR . 'README.md';

$current = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '0.1.0';
if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $current, $m)) {
    $current = '0.1.0';
    $m = [null, 0, 1, 0];
}

$major = (int)$m[1];
$minor = (int)$m[2];
$patch = (int)$m[3];

if ($part === 'major') {
    $major++;
    $minor = 0;
    $patch = 0;
} elseif ($part === 'minor') {
    $minor++;
    $patch = 0;
} else {
    $patch++;
}

$next = $major . '.' . $minor . '.' . $patch;
file_put_contents($versionFile, $next . PHP_EOL);

if (is_file($readmeFile)) {
    $readme = (string)file_get_contents($readmeFile);
    $updated = preg_replace('/^\*\*Version:\*\*\s*.+$/m', '**Version:** ' . $next, $readme, 1, $count);
    if ($count === 0) {
        $lines = preg_split("/\r\n|\n|\r/", $readme, 2);
        $head = $lines[0] ?? '# Karaoke OS';
        $tail = $lines[1] ?? '';
        $sep = str_contains($readme, "\r\n") ? "\r\n" : "\n";
        $updated = $head . $sep . $sep . '**Version:** ' . $next . $sep . ltrim($tail);
    }
    file_put_contents($readmeFile, $updated);
}

fwrite(STDOUT, $next . PHP_EOL);

