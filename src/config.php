<?php
declare(strict_types=1);

/**
 * Copy `config.sample.php` to `config.local.php` (in the app root) to override.
 */

// App
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
}
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', 'http://localhost/karaoke-os');
}

// Email verification
if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', 'no-reply@localhost');
}
if (!defined('DEV_SHOW_VERIFICATION_LINK')) {
    define('DEV_SHOW_VERIFICATION_LINK', true);
}

// Google Drive (service account)
// Tip: share the Drive folder/files with the service account email as Viewer/Editor as needed.
if (!defined('DRIVE_SERVICE_ACCOUNT_JSON')) {
    define('DRIVE_SERVICE_ACCOUNT_JSON', ''); // e.g. APP_ROOT . '/data/service-account.json'
}
if (!defined('DRIVE_SCOPE')) {
    define('DRIVE_SCOPE', 'https://www.googleapis.com/auth/drive');
}
if (!defined('DRIVE_ENFORCE_PERMISSION_ON_PLAY')) {
    define('DRIVE_ENFORCE_PERMISSION_ON_PLAY', true);
}
