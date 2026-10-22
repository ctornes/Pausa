<?php

// Sends break reminders. Run it every minute from a scheduled task (Plesk: Operazioni pianificate),
// e.g. "* * * * *  php /path/to/app/bin/send-reminders.php".

require dirname(__DIR__) . '/src/bootstrap.php';

use App\PushService;

if (!PushService::available()) {
    fwrite(STDERR, "Web push libraries missing: run `composer install` in the app folder.\n");
    exit(1);
}

// A slow run must not overlap with the next minute's one.
$lock = fopen(dirname(config('db_path')) . '/reminders.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

reminder_log()->heartbeat();
try {
    foreach (reminders()->run(new DateTimeImmutable()) as $line) {
        reminder_log()->info($line);
        echo date('H:i:s'), ' ', $line, "\n";
    }
} catch (Throwable $e) {
    reminder_log()->error('run failed: ' . $e->getMessage());
    throw $e;
}
