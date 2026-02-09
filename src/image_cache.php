<?php
declare(strict_types=1);

function ensure_dir(string $dir): void
{
    if (is_dir($dir)) {
        return;
    }
    if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create directory: ' . $dir);
    }
}

function image_ext_from_content_type(string $ct): ?string
{
    $ct = strtolower(trim(explode(';', $ct)[0] ?? ''));
    return match ($ct) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => null,
    };
}

function maybe_wikimedia_thumb_url(string $url, int $width = 512): string
{
    if (!is_safe_external_url($url)) {
        return $url;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return $url;
    }
    $host = strtolower($host);
    if (str_contains($host, 'wikimedia.org') || str_contains($host, 'wikipedia.org')) {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        $query = [];
        if (!empty($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        if (!isset($query['width'])) {
            $query['width'] = (string)max(64, min(2048, $width));
        }
        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . $host . ($parts['path'] ?? '');
        if (!empty($query)) {
            $rebuilt .= '?' . http_build_query($query);
        }
        return $rebuilt;
    }
    return $url;
}

function download_image_to_file(string $url, string $destPath, int $timeoutSeconds = 6, int $maxBytes = 5_000_000, ?string &$mimeOut = null): bool
{
    if (!is_safe_external_url($url)) {
        return false;
    }

    $tmp = $destPath . '.tmp';
    @unlink($tmp);

    if (function_exists('curl_init')) {
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => max(1, (int)$timeoutSeconds),
            CURLOPT_TIMEOUT => max(2, (int)$timeoutSeconds),
            CURLOPT_USERAGENT => 'KaraokeOS/1.0',
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $downloaded = 0;
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $dlTotal, $dlNow) use (&$downloaded, $maxBytes) {
            $downloaded = (int)$dlNow;
            if ($downloaded > $maxBytes) {
                return 1; // abort
            }
            return 0;
        });

        $ok = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok !== true) {
            @unlink($tmp);
            return false;
        }
        if (filesize($tmp) === 0 || filesize($tmp) > $maxBytes) {
            @unlink($tmp);
            return false;
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            return false;
        }
        if (!empty($info['mime']) && is_string($info['mime'])) {
            $mimeOut = $info['mime'];
        }

        if (!@rename($tmp, $destPath)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => max(2, (int)$timeoutSeconds),
            'follow_location' => 1,
            'max_redirects' => 3,
            'user_agent' => 'KaraokeOS/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $in = @fopen($url, 'rb', false, $ctx);
    if ($in === false) {
        return false;
    }
    $out = fopen($tmp, 'wb');
    if ($out === false) {
        fclose($in);
        return false;
    }
    $total = 0;
    while (!feof($in)) {
        $buf = fread($in, 64 * 1024);
        if ($buf === false) {
            break;
        }
        $total += strlen($buf);
        if ($total > $maxBytes) {
            fclose($in);
            fclose($out);
            @unlink($tmp);
            return false;
        }
        fwrite($out, $buf);
    }
    fclose($in);
    fclose($out);

    if ($total === 0) {
        @unlink($tmp);
        return false;
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        @unlink($tmp);
        return false;
    }
    if (!empty($info['mime']) && is_string($info['mime'])) {
        $mimeOut = $info['mime'];
    }
    if (!@rename($tmp, $destPath)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function cache_artist_image_url(int $artistId, string $artistName, string $remoteUrl): ?string
{
    $remoteUrl = trim($remoteUrl);
    if ($artistId <= 0 || $remoteUrl === '' || !is_safe_external_url($remoteUrl)) {
        return null;
    }

    $remoteUrl = maybe_wikimedia_thumb_url($remoteUrl, 512);

    $uploadsDir = APP_ROOT . '/assets/uploads/artists/auto';
    ensure_dir($uploadsDir);

    $base = sprintf('artist_%d', $artistId);

    foreach (['jpg', 'png', 'webp', 'gif'] as $ext) {
        $existing = $uploadsDir . '/' . $base . '.' . $ext;
        if (is_file($existing) && filesize($existing) > 0) {
            return 'assets/uploads/artists/auto/' . $base . '.' . $ext;
        }
    }

    $mime = null;
    $tmpAbs = $uploadsDir . '/' . $base . '.bin';
    if (!download_image_to_file($remoteUrl, $tmpAbs, 6, 5_000_000, $mime)) {
        @unlink($tmpAbs);
        return null;
    }

    $ext = $mime ? (image_ext_from_content_type($mime) ?? 'jpg') : 'jpg';
    $finalAbs = $uploadsDir . '/' . $base . '.' . $ext;
    if (!@rename($tmpAbs, $finalAbs)) {
        @unlink($tmpAbs);
        return null;
    }

    return 'assets/uploads/artists/auto/' . $base . '.' . $ext;
}
