<?php
declare(strict_types=1);

function drive_is_configured(): bool
{
    return is_string(DRIVE_SERVICE_ACCOUNT_JSON)
        && DRIVE_SERVICE_ACCOUNT_JSON !== ''
        && is_file(DRIVE_SERVICE_ACCOUNT_JSON);
}

function drive_extract_file_id(string $urlOrId): ?string
{
    $v = trim($urlOrId);
    if ($v === '') {
        return null;
    }

    // Looks like a file id already.
    if (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $v)) {
        return $v;
    }

    // https://drive.google.com/file/d/<id>/view
    if (preg_match('#/file/d/([^/]+)#', $v, $m)) {
        return $m[1];
    }

    // https://drive.google.com/open?id=<id>
    if (preg_match('#[?&]id=([^&]+)#', $v, $m)) {
        return $m[1];
    }

    return null;
}

function drive_preview_url(string $fileId): string
{
    return 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/preview';
}

function drive_view_url(string $fileId): string
{
    return 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view';
}

function drive_service_account(): array
{
    $json = @file_get_contents(DRIVE_SERVICE_ACCOUNT_JSON);
    if ($json === false) {
        throw new RuntimeException('Drive service account JSON not readable.');
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('Drive service account JSON invalid.');
    }
    return $data;
}

function drive_access_token(): string
{
    static $cached = null;
    if (is_array($cached) && isset($cached['token'], $cached['exp']) && time() < (int)$cached['exp']) {
        return (string)$cached['token'];
    }

    $sa = drive_service_account();
    $clientEmail = (string)($sa['client_email'] ?? '');
    $privateKey = (string)($sa['private_key'] ?? '');
    $tokenUri = (string)($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');

    if ($clientEmail === '' || $privateKey === '') {
        throw new RuntimeException('Drive service account missing client_email/private_key.');
    }

    $iat = time();
    $exp = $iat + 3600;
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $payload = [
        'iss' => $clientEmail,
        'scope' => DRIVE_SCOPE,
        'aud' => $tokenUri,
        'iat' => $iat,
        'exp' => $exp,
    ];

    $jwt = drive_jwt_encode($header, $payload, $privateKey);
    $resp = http_form_post($tokenUri, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        throw new RuntimeException('OAuth token error: HTTP ' . $resp['status'] . ' ' . substr($resp['body'], 0, 300));
    }

    $data = json_decode($resp['body'], true);
    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('OAuth token response invalid.');
    }

    $token = (string)$data['access_token'];
    $ttl = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
    $cached = [
        'token' => $token,
        'exp' => time() + max(60, $ttl - 60),
    ];

    return $token;
}

function drive_grant_viewer(string $fileId, string $userEmail): void
{
    $token = drive_access_token();
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/permissions?sendNotificationEmail=false';
    $resp = http_json('POST', $url, [
        'Authorization: Bearer ' . $token,
    ], [
        'type' => 'user',
        'role' => 'reader',
        'emailAddress' => $userEmail,
    ]);

    // 409/400 for duplicates varies; treat as ok if permission already exists.
    if ($resp['status'] === 409) {
        return;
    }
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        throw new RuntimeException('Drive permission error: HTTP ' . $resp['status'] . ' ' . substr($resp['body'], 0, 300));
    }
}

function drive_try_harden_view_only(string $fileId): void
{
    // Best-effort: some Drive settings (disable download/print/copy) are not reliably enforceable via API for all accounts.
    try {
        $token = drive_access_token();
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?fields=id';
        http_json('PATCH', $url, [
            'Authorization: Bearer ' . $token,
        ], [
            'copyRequiresWriterPermission' => true,
            'viewersCanCopyContent' => false,
        ]);
    } catch (Throwable $e) {
        // ignore
    }
}

function drive_jwt_encode(array $header, array $payload, string $privateKeyPem): string
{
    $segments = [];
    $segments[] = drive_base64url(json_encode($header, JSON_UNESCAPED_SLASHES));
    $segments[] = drive_base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signingInput = implode('.', $segments);

    $sig = '';
    $ok = openssl_sign($signingInput, $sig, $privateKeyPem, 'sha256WithRSAEncryption');
    if (!$ok) {
        throw new RuntimeException('JWT signing failed.');
    }

    $segments[] = drive_base64url($sig);
    return implode('.', $segments);
}

function drive_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function http_form_post(string $url, array $fields): array
{
    $body = http_build_query($fields);
    return http_raw('POST', $url, [
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);
}

function http_json(string $method, string $url, array $headers, array $data): array
{
    $headers[] = 'Content-Type: application/json';
    return http_raw($method, $url, $headers, json_encode($data, JSON_UNESCAPED_SLASHES));
}

function http_raw(string $method, string $url, array $headers, ?string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $respBody = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($respBody === '' && curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP error: ' . $err);
        }
        curl_close($ch);
        return ['status' => $status, 'body' => $respBody];
    }

    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 20,
            'ignore_errors' => true,
            'content' => $body ?? '',
        ],
    ];
    $ctx = stream_context_create($opts);
    $respBody = (string)file_get_contents($url, false, $ctx);
    $status = 0;
    foreach (($http_response_header ?? []) as $h) {
        if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $h, $m)) {
            $status = (int)$m[1];
            break;
        }
    }
    return ['status' => $status, 'body' => $respBody];
}
