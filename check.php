<?php
// Temporary diagnostics. DELETE this file after use.
header('Content-Type: text/plain; charset=utf-8');
echo "PHP version: " . PHP_VERSION . "\n";
echo "mbstring: " . (function_exists('mb_substr') ? 'yes' : 'no (ok, handled)') . "\n";
echo "sessions: " . (function_exists('session_start') ? 'yes' : 'no') . "\n";
echo "data/ writable: " . (is_writable(__DIR__ . '/data') ? 'yes' : 'NO - set permission 755/775') . "\n";
echo "private/ writable: " . (is_writable(__DIR__ . '/private') ? 'yes' : 'NO - set permission 755/775') . "\n";
echo "request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "https: " . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'yes' : 'no') . "\n";
echo "host: " . ($_SERVER['HTTP_HOST'] ?? '?') . "\n";
require __DIR__ . '/config.php';
$pw = trim((string)$ADMIN_PASSWORD);
echo "admin password in config.php: " . ($pw === 'change-me-now' ? 'STILL THE DEFAULT (change it and re-upload config.php)' : ($pw === '' ? 'EMPTY' : 'changed, ' . strlen($pw) . ' characters long')) . "\n";
echo "config.php last modified: " . date('Y-m-d H:i', filemtime(__DIR__ . '/config.php')) . "\n";
echo "server time now: " . date('Y-m-d H:i') . "\n";
