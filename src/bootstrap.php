<?php
declare(strict_types=1);

if (is_file(dirname(__DIR__) . '/config.local.php')) {
    require_once dirname(__DIR__) . '/config.local.php';
}
require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);
session_start();

define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'karaoke.sqlite');
define('APP_BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/'));

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/queries.php';
require __DIR__ . '/google_drive.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/smtp_mailer.php';
require __DIR__ . '/music_metadata.php';
require APP_ROOT . '/lib/SimplePager.php';
