<?php
declare(strict_types=1);

// HTTP-tietoturvaotsakkeet (Security Headers)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ThemeManager.php';
require_once __DIR__ . '/BlockRenderer.php';

use OlliCMS\Storage;
use OlliCMS\Auth;
use OlliCMS\ThemeManager;

$baseDir = dirname(__DIR__);
$storage = new Storage($baseDir);
$auth = new Auth($storage);
$themeManager = new ThemeManager($storage);
