<?php

declare(strict_types=1);

namespace App;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * data/reminders.log: what the reminder task and the push library did, readable by admins
 * from Impostazioni. Kept small by dropping the oldest half when it grows.
 */
final class ReminderLog extends AbstractLogger
{
    private const MAX_BYTES = 200_000;

    public function __construct(private readonly string $file, private readonly string $heartbeatFile)
    {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (is_file($this->file) && filesize($this->file) > self::MAX_BYTES) {
            $content = (string) file_get_contents($this->file);
            $content = substr($content, intdiv(strlen($content), 2));
            file_put_contents($this->file, substr($content, strpos($content, "\n") + 1), LOCK_EX);
        }
        file_put_contents($this->file, date('Y-m-d H:i:s') . " [$level] $message\n", FILE_APPEND | LOCK_EX);
    }

    /** Called on every run of the scheduled task, so admins can see it is alive. */
    public function heartbeat(): void
    {
        touch($this->heartbeatFile);
    }

    public function lastRun(): ?int
    {
        clearstatcache(true, $this->heartbeatFile);

        return is_file($this->heartbeatFile) ? (int) filemtime($this->heartbeatFile) : null;
    }

    /** @return list<string> the newest $count lines, newest first */
    public function tail(int $count): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_reverse(array_slice($lines, -$count));
    }
}
