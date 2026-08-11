<?php
declare(strict_types=1);

define('APP_NAME', 'PageShift');
define('APP_COPYRIGHT_YEAR', '2026');
define('APP_COPYRIGHT_OWNER', 'Eng Choon Hao');
define('APP_COPYRIGHT_NOTICE', 'Copyright © 2026 Eng Choon Hao. All Rights Reserved.');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
define('OUTPUT_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'outputs');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20 MB
define('ALLOWED_MIME', ['application/pdf']);
define('FILE_TTL_HOURS', 24);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'pdf_to_word');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0755, true);
}
