<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

require APP_ROOT . '/src/helpers.php';

// Warnings become exceptions: a stray warning printed before a JSON reply would break it,
// and a failed write must stop the request instead of carrying on half-done.
set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level) || in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false; // silenced with @, or a deprecation: leave it to PHP's normal logging
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

// Unexpected errors: full details go to the server log, people get a plain message.
set_exception_handler(function (Throwable $e): void {
    error_log('[pausa] ' . $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    $message = $e instanceof App\SetupError
        ? $e->getMessage()
        : 'Errore del server: l\'operazione non è riuscita. Riprova tra poco; se continua, avvisa un amministratore.';
    if (is_fetch() && !headers_sent()) {
        json_response(['ok' => false, 'message' => $message], 500);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Errore · ' . e(config('app_name')) . '</title><link rel="stylesheet" href="' . asset('app.css') . '">'
        . '<main class="main"><div class="panel panel--narrow"><h1 class="page-title">Qualcosa non ha funzionato</h1>'
        . '<p>' . e($message) . '</p><p><a href="' . url('/') . '">Riprova</a></p></div></main></html>';
});

date_default_timezone_set(config('timezone'));

if (PHP_SAPI !== 'cli') {
    $sessionPath = config('session_path');
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        throw new App\SetupError(
            'Il server non può salvare gli accessi: la cartella data/sessions non è scrivibile. '
            . 'Un amministratore deve dare i permessi di scrittura alla cartella data/ e a tutto il suo contenuto.'
        );
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
