<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

require APP_ROOT . '/src/helpers.php';

date_default_timezone_set(config('timezone'));

if (PHP_SAPI !== 'cli') {
    $sessionPath = config('session_path');
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }
    $lifetime = config('session_days') * 86400;

    session_save_path($sessionPath);
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => config('base_url') ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('pausa');
    session_start();

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
